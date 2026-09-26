<?php

namespace App\Http\Controllers;

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
        private MediaOptimizer $mediaOptimizer,
        private ModerationService $moderation,
    ) {}

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
            $team = Team::findOrFail($data['team_id']);
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

        $story = Story::create([
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

        return back()->with('success', __('platform.social.story_created'));
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

        return back();
    }

    public function viewed(Request $request, Story $story)
    {
        $this->authorize('view', $story);

        if ($story->user_id !== $request->user()->id) {
            $story->views()->updateOrCreate(
                ['user_id' => $request->user()->id],
                ['viewed_at' => now()],
            );
        }

        return back();
    }

    public function destroy(Story $story)
    {
        $this->authorize('delete', $story);

        Storage::disk(UploadStorage::disk())->delete(array_filter([
            $story->media_path,
            $story->media_thumbnail_path,
        ]));

        $story->delete();

        return back()->with('success', 'Story gelöscht.');
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
