<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StoryResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Club;
use App\Models\Story;
use App\Models\Team;
use App\Models\User;
use App\Services\MediaOptimizer;
use App\Services\ModerationService;
use App\Support\ClubPermissions;
use App\Support\Roles;
use App\Support\UploadStorage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly MediaOptimizer $mediaOptimizer,
        private readonly ModerationService $moderation,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $clubIds = $user->clubs()->pluck('clubs.id')->all();
        $teamIds = $user->teams()->pluck('teams.id')->all();

        $stories = Story::query()
            ->active()
            ->where(function ($query) use ($user) {
                $query->where('moderation_status', 'approved')
                    ->orWhere(function ($query) use ($user) {
                        $query->where('user_id', $user->id)
                            ->whereIn('moderation_status', ['flagged', 'reported']);
                    });
            })
            ->where(function ($query) use ($user, $clubIds, $teamIds) {
                $query->where('user_id', $user->id)
                    ->orWhere('visibility', 'public')
                    ->orWhere(function ($query) use ($clubIds) {
                        $query->where('visibility', 'organization')
                            ->whereIn('club_id', $clubIds);
                    })
                    ->orWhere(function ($query) use ($teamIds) {
                        $query->where('visibility', 'team')
                            ->whereIn('team_id', $teamIds);
                    });
            })
            ->with([
                'user.roles',
                'club',
                'team.club',
                'views.user:id,name,profile_photo_path',
                'reactions' => fn ($query) => $query->where('user_id', $user->id)->select('id', 'story_id', 'user_id', 'reaction'),
            ])
            ->withCount(['views', 'reactions'])
            ->withExists([
                'views as viewed_by_me' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->latest('id')
            ->limit(min(max((int) $request->integer('limit', 30), 1), 60))
            ->get()
            ->map(fn (Story $story) => $this->decorateStory($story, $user, $request));

        return StoryResource::collection($stories);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Story::class);

        $user = $request->user();
        $this->ensureStoryRateLimit($user);

        $data = $request->validate([
            'club_id' => ['nullable', 'exists:clubs,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'publisher_type' => ['nullable', Rule::in(['user', 'club', 'team'])],
            'visibility' => ['required', Rule::in(Story::VISIBILITIES)],
            'caption' => ['nullable', 'string', 'max:500'],
            'media' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif,mp4,mov,webm,ogg', 'max:51200'],
        ]);

        if (! empty($data['team_id'])) {
            $team = Team::query()->with('club')->findOrFail($data['team_id']);
            abort_unless($this->canUseTeam($user, $team), 403);
            $data['club_id'] = $team->club_id;
        }

        if (! empty($data['club_id']) && empty($data['team_id'])) {
            abort_unless($this->canUseClub($user, Club::findOrFail($data['club_id'])), 403);
        }

        abort_if($data['visibility'] === 'team' && empty($data['team_id']), 422, 'Team-Storys brauchen ein Team.');
        abort_if($data['visibility'] === 'organization' && empty($data['club_id']), 422, 'Vereins-Storys brauchen einen Verein.');

        $publisher = $this->publisherFor($data);
        $this->authorizePublisher($user, $publisher);

        $media = $this->mediaOptimizer->store($request->file('media'), $this->directoryFor($data, $user->id));

        $story = Story::query()->create([
            'user_id' => $user->id,
            'club_id' => $data['club_id'] ?? null,
            'team_id' => $data['team_id'] ?? null,
            'publisher_type' => $publisher['type'],
            'publisher_id' => $publisher['id'],
            'visibility' => $data['visibility'],
            'caption' => $data['caption'] ?? null,
            'media_path' => $media['path'],
            'media_thumbnail_path' => $media['thumbnail_path'] ?? null,
            'media_type' => $media['type'],
            'media_size' => $media['size'] ?? null,
            'expires_at' => now()->addDay(),
        ]);

        $this->moderation->flagIfNeeded($story, $story->caption ?? '', $user->id);

        $story = $this->decorateStory(
            $story->fresh(['user.roles', 'club', 'team.club'])->loadCount(['views', 'reactions']),
            $user,
            $request
        );

        return response()->json([
            'data' => (new StoryResource($story))->resolve($request),
            'message' => __('platform.social.story_created'),
        ], 201);
    }

    public function viewed(Request $request, Story $story)
    {
        $this->authorize('view', $story);

        $story->views()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['viewed_at' => now()],
        );

        return new StoryResource($this->freshDecoratedStory($story, $request));
    }

    public function react(Request $request, Story $story)
    {
        $this->authorize('view', $story);

        $data = $request->validate([
            'reaction' => ['required', Rule::in(['clap', 'fire', 'heart', 'strong', 'wow'])],
        ]);

        abort_if($story->user_id === $request->user()->id, 422, 'Du kannst nicht auf deine eigene Story reagieren.');

        $story->reactions()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['reaction' => $data['reaction']],
        );

        return new StoryResource($this->freshDecoratedStory($story, $request));
    }

    public function destroy(Request $request, Story $story)
    {
        $this->authorize('delete', $story);

        $shouldReturnPayload = $story->media_type !== 'image/jpeg' || str_ends_with((string) $story->media_path, '.webp');

        Storage::disk(UploadStorage::disk())->delete(array_filter([
            $story->media_path,
            $story->media_thumbnail_path,
        ]));

        $story->delete();

        return $shouldReturnPayload
            ? response()->json(['data' => ['deleted' => true]])
            : response()->noContent();
    }

    private function freshDecoratedStory(Story $story, Request $request): Story
    {
        return $this->decorateStory(
            $story->fresh(['user.roles', 'club', 'team.club', 'views.user', 'reactions'])
                ->loadCount(['views', 'reactions']),
            $request->user(),
            $request
        );
    }

    private function decorateStory(Story $story, User $user, Request $request): Story
    {
        $story->setAttribute('can_delete', $user->can('delete', $story));
        $story->setAttribute('viewed_by_me', (bool) ($story->viewed_by_me ?? $story->views->contains('user_id', $user->id)));
        $story->setAttribute('actor', $this->storyActor($story, $request));
        $story->setAttribute('my_reaction', $story->reactions->firstWhere('user_id', $user->id)?->reaction);
        $story->setAttribute('viewer_preview', $story->user_id === $user->id
            ? $story->views->take(8)->map(fn ($view) => [
                'id' => $view->user?->id,
                'name' => $view->user?->name,
                'profile_photo_thumb' => $view->user?->profile_photo_thumb,
            ])->values()
            : []);

        return $story;
    }

    private function storyActor(Story $story, Request $request): array
    {
        if ($story->publisher_type === 'club' && $story->club) {
            return [
                'id' => $story->club_id,
                'key' => 'club:'.$story->club_id,
                'type' => 'club',
                'name' => $story->club->name,
                'profile_photo_thumb' => $story->club->profile_photo_thumb ?? null,
            ];
        }

        if ($story->publisher_type === 'team' && $story->team) {
            return [
                'id' => $story->team_id,
                'key' => 'team:'.$story->team_id,
                'type' => 'team',
                'name' => $story->team->name,
                'profile_photo_thumb' => $story->team->profile_photo_thumb ?? null,
            ];
        }

        return [
            'id' => $story->user_id,
            'key' => 'user:'.$story->user_id,
            'type' => 'user',
            'name' => $story->user?->name,
            'profile_photo_thumb' => $story->user?->profile_photo_thumb,
            'user_card' => $story->user ? (new UserResource($story->user))->resolve($request)['user_card'] : null,
        ];
    }

    private function directoryFor(array $data, int $userId): string
    {
        if (! empty($data['team_id'])) {
            return 'teams/'.$data['team_id'].'/stories';
        }

        if (! empty($data['club_id'])) {
            return 'clubs/'.$data['club_id'].'/stories';
        }

        return 'users/'.$userId.'/stories';
    }

    private function publisherFor(array $data): array
    {
        $type = $data['publisher_type'] ?? 'user';

        if ($type === 'team') {
            abort_if(empty($data['team_id']), 422, 'Team-Storys brauchen ein Team.');

            return ['type' => 'team', 'id' => (int) $data['team_id']];
        }

        if ($type === 'club') {
            abort_if(empty($data['club_id']), 422, 'Vereins-Storys brauchen einen Verein.');

            return ['type' => 'club', 'id' => (int) $data['club_id']];
        }

        return ['type' => 'user', 'id' => null];
    }

    private function authorizePublisher(User $user, array $publisher): void
    {
        if ($publisher['type'] === 'club') {
            abort_unless($this->canPublishAsClub($user, Club::findOrFail($publisher['id'])), 403);
        }

        if ($publisher['type'] === 'team') {
            abort_unless($this->canPublishAsTeam($user, Team::findOrFail($publisher['id'])), 403);
        }
    }

    private function ensureStoryRateLimit(User $user): void
    {
        $recentStories = Story::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentStories >= 12) {
            throw ValidationException::withMessages([
                'media' => 'Du hast in kurzer Zeit viele Storys erstellt. Bitte warte kurz, bevor du weitere Storys postest.',
            ]);
        }
    }

    private function canUseClub(User $user, Club $club): bool
    {
        if ($user->hasAnyRole(Roles::FULL_ACCESS)
            || ClubPermissions::allows($club, $user, ClubPermissions::CONTENT_MANAGE)) {
            return true;
        }

        if (! $club->members_can_post_to_club) {
            throw ValidationException::withMessages([
                'club_id' => 'Mitglieder dürfen für diesen Verein keine Storys erstellen.',
            ]);
        }

        return $club->users()->where('users.id', $user->id)->exists()
            || $club->teams()->whereHas('users', fn ($query) => $query->where('users.id', $user->id))->exists();
    }

    private function canPublishAsClub(User $user, ?Club $club): bool
    {
        if (! $club) {
            return false;
        }

        return $user->hasAnyRole(Roles::FULL_ACCESS)
            || ClubPermissions::allows($club, $user, ClubPermissions::CONTENT_MANAGE);
    }

    private function canUseTeam(User $user, Team $team): bool
    {
        $team->loadMissing('club');

        if ($user->hasAnyRole(Roles::FULL_ACCESS)
            || ClubPermissions::allowsForTeam($team, $user, ClubPermissions::CONTENT_MANAGE)
            || $user->can('update', $team)) {
            return true;
        }

        if (! $team->club?->members_can_post_to_teams) {
            throw ValidationException::withMessages([
                'team_id' => 'Mitglieder dürfen für Teams dieses Vereins keine Storys erstellen.',
            ]);
        }

        return $team->users()->where('users.id', $user->id)->exists();
    }

    private function canPublishAsTeam(User $user, Team $team): bool
    {
        $team->loadMissing('club');

        return $user->hasAnyRole(Roles::FULL_ACCESS)
            || ClubPermissions::allowsForTeam($team, $user, ClubPermissions::CONTENT_MANAGE)
            || (! ClubPermissions::explicitlyDenies($team->club, $user, ClubPermissions::CONTENT_MANAGE)
                && $team->users()
                    ->where('users.id', $user->id)
                    ->wherePivotIn('role', ['Coach', 'Captain'])
                    ->exists());
    }
}
