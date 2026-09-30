<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PostResource;
use App\Models\Club;
use App\Models\Post;
use App\Models\SportSkill;
use App\Models\Team;
use App\Models\User;
use App\Services\MediaOptimizer;
use App\Services\ModerationService;
use App\Services\PostService;
use App\Support\AppNotification;
use App\Support\ClubPermissions;
use App\Support\Roles;
use App\Support\UploadStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FeedController extends Controller
{
    private const POST_DELETED = 'Post deleted.';

    use AuthorizesRequests;

    public function __construct(
        private PostService $service,
        private MediaOptimizer $mediaOptimizer,
        private ModerationService $moderation,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $posts = $this->postsVisibleTo($user)
            ->with(['user', 'club', 'team', 'sport', 'sportSkills', 'attachments.file'])
            ->withCount(['comments', 'likes', 'helpfuls'])
            ->withExists([
                'likes as liked_by_me' => fn ($query) => $query->where('user_id', $user->id),
                'helpfuls as helpful_by_me' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->latest()
            ->paginate($this->perPage($request));

        return PostResource::collection($posts);
    }

    public function userPosts(Request $request, User $user)
    {
        $viewer = $request->user();
        $query = $user->isProfileVisibleTo($viewer)
            ? $this->postsVisibleTo($viewer)->where('user_id', $user->id)
            : Post::query()->whereKey(-1);

        $posts = $query
            ->with(['user', 'club', 'team', 'sport', 'sportSkills', 'attachments.file'])
            ->withCount([
                'comments' => fn ($query) => $query->where('moderation_status', 'approved'),
                'likes',
                'helpfuls',
            ])
            ->withExists([
                'likes as liked_by_me' => fn ($query) => $query->where('user_id', $viewer->id),
                'helpfuls as helpful_by_me' => fn ($query) => $query->where('user_id', $viewer->id),
            ])
            ->latest()
            ->paginate($this->perPage($request));

        return PostResource::collection($posts);
    }

    public function show(Request $request, Post $post)
    {
        $this->authorize('view', $post);

        $post->load([
            'user',
            'club',
            'team',
            'sport',
            'sportSkills',
            'attachments.file',
        ])->loadCount(['comments', 'likes', 'helpfuls'])
            ->loadExists([
                'likes as liked_by_me' => fn ($query) => $query->where('user_id', $request->user()->id),
                'helpfuls as helpful_by_me' => fn ($query) => $query->where('user_id', $request->user()->id),
            ]);

        return new PostResource($post);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'content' => ['nullable', 'required_without_all:image,attachments', 'string', 'max:5000'],
            'visibility' => ['required', Rule::in(Post::VISIBILITIES)],
            'post_type' => ['required', Rule::in(Post::TYPES)],
            'content_origin' => ['nullable', Rule::in(Post::CONTENT_ORIGINS)],
            'club_id' => ['nullable', 'integer', 'exists:clubs,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'sport_id' => ['nullable', 'integer', 'exists:sports,id'],
            'sport_skill_ids' => ['nullable', 'array', 'max:8'],
            'sport_skill_ids.*' => ['integer', 'exists:sport_skills,id'],
            'image' => $this->imageValidationRules($request),
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,mp4,mov,webm,ogg,pdf,doc,docx,xls,xlsx,txt,zip', 'max:51200'],
        ]);

        $user = $request->user();
        if ($data['visibility'] !== 'team') {
            $data['team_id'] = null;
        }
        if (in_array($data['visibility'], ['public', 'private'], true)) {
            $data['club_id'] = null;
        }

        $clubId = $data['club_id'] ?? null;
        $teamId = $data['team_id'] ?? null;

        if ($teamId) {
            $team = Team::query()->with('club')->findOrFail($teamId);
            abort_unless($this->canUseTeam($user, $team), 403);

            $clubId = $team->club_id;
        }

        if ($clubId && ! $teamId) {
            $club = Club::query()->findOrFail($clubId);
            abort_unless($this->canUseClub($user, $club), 403);
        }

        if ($data['visibility'] === 'team') {
            abort_unless((bool) $teamId, 422, 'Team posts require a team_id.');
        }

        if ($data['visibility'] === 'organization') {
            abort_unless((bool) $clubId, 422, 'Organization posts require a club_id or team_id.');
        }

        if ($request->hasFile('image')) {
            $data['image'] = $this->mediaOptimizer->store($request->file('image'), 'posts')['path'];
        } elseif (! empty($data['image'])) {
            $data['image'] = $this->normalizePostImagePath($data['image']);
        }

        $data['content'] ??= '';
        $data['club_id'] = $clubId;
        $data['team_id'] = $teamId;
        $data['sport_id'] = $data['sport_id'] ?? null;
        $data['content_origin'] = $data['content_origin'] ?? 'self';
        $data['attachments'] = $request->file('attachments', []);
        $data['sport_skill_ids'] = $this->validSkillIdsForSport($data['sport_skill_ids'] ?? [], $data['sport_id']);

        $post = $this->service->create($user, $data);
        $this->moderation->flagIfNeeded($post, $post->content, $user->id);

        return (new PostResource(
            $post->load(['user', 'club', 'team', 'sport', 'sportSkills', 'attachments.file'])->loadCount(['comments', 'likes', 'helpfuls'])
        ))->response()->setStatusCode(201);
    }

    public function update(Request $request, Post $post)
    {
        $this->authorize('update', $post);

        $data = $request->validate([
            'content' => ['nullable', 'required_without_all:image,attachments', 'string', 'max:5000'],
            'visibility' => ['required', Rule::in(Post::VISIBILITIES)],
            'post_type' => ['required', Rule::in(Post::TYPES)],
            'content_origin' => ['required', Rule::in(Post::CONTENT_ORIGINS)],
            'club_id' => ['nullable', 'integer', 'exists:clubs,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'sport_id' => ['nullable', 'integer', 'exists:sports,id'],
            'sport_skill_ids' => ['nullable', 'array', 'max:8'],
            'sport_skill_ids.*' => ['integer', 'exists:sport_skills,id'],
            'image' => $this->imageValidationRules($request),
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,mp4,mov,webm,ogg,pdf,doc,docx,xls,xlsx,txt,zip', 'max:51200'],
        ]);

        $user = $request->user();
        if ($data['visibility'] !== 'team') {
            $data['team_id'] = null;
        }
        if (in_array($data['visibility'], ['public', 'private'], true)) {
            $data['club_id'] = null;
        }

        $clubId = $data['club_id'] ?? null;
        $teamId = $data['team_id'] ?? null;

        if ($teamId) {
            $team = Team::query()->with('club')->findOrFail($teamId);
            abort_unless($this->canUseTeam($user, $team), 403);

            $clubId = $team->club_id;
        }

        if ($clubId && ! $teamId) {
            $club = Club::query()->findOrFail($clubId);
            abort_unless($this->canUseClub($user, $club), 403);
        }

        if ($data['visibility'] === 'team') {
            abort_unless((bool) $teamId, 422, 'Team posts require a team_id.');
        }

        if ($data['visibility'] === 'organization') {
            abort_unless((bool) $clubId, 422, 'Organization posts require a club_id or team_id.');
        }

        if ($request->hasFile('image')) {
            if ($post->image) {
                Storage::disk(UploadStorage::disk($post->image))->delete($post->image);
            }

            $data['image'] = $this->mediaOptimizer->store($request->file('image'), 'posts')['path'];
        } elseif (! empty($data['image'])) {
            $data['image'] = $this->normalizePostImagePath($data['image']);
        } else {
            unset($data['image']);
        }

        $skillIds = $this->validSkillIdsForSport($data['sport_skill_ids'] ?? [], $data['sport_id'] ?? null);
        unset($data['attachments'], $data['sport_skill_ids']);

        $data['content'] ??= '';
        $data['club_id'] = $clubId;
        $data['team_id'] = $teamId;
        $data['sport_id'] = $data['sport_id'] ?? null;

        $post->update($data);
        $this->moderation->flagIfNeeded($post, $post->content, $user->id);
        $post->sportSkills()->sync($skillIds);
        $this->service->attachFiles($post, $user, $request->file('attachments', []));

        return new PostResource(
            $post->fresh(['user', 'club', 'team', 'sport', 'sportSkills', 'attachments.file'])
                ->loadCount(['comments', 'likes', 'helpfuls'])
                ->loadExists([
                    'likes as liked_by_me' => fn ($query) => $query->where('user_id', $user->id),
                    'helpfuls as helpful_by_me' => fn ($query) => $query->where('user_id', $user->id),
                ])
        );
    }

    public function toggleLike(Request $request, Post $post)
    {
        $this->authorize('view', $post);

        $like = $post->likes()->where('user_id', $request->user()->id)->first();

        if ($like) {
            $like->delete();
        } else {
            $post->likes()->create([
                'user_id' => $request->user()->id,
            ]);

            if ($post->user_id !== $request->user()->id) {
                AppNotification::send($post->user_id, 'post.like', [
                    'title' => $request->user()->name.' gefällt dein Beitrag',
                    'body' => str($post->content)->limit(120)->toString(),
                    'url' => '/feed',
                    'actor_id' => $request->user()->id,
                    'actor_name' => $request->user()->name,
                    'post_id' => $post->id,
                ]);
            }
        }

        return new PostResource(
            $post->fresh(['user', 'club', 'team', 'sport', 'sportSkills', 'attachments.file'])
                ->loadCount(['comments', 'likes', 'helpfuls'])
                ->loadExists([
                    'likes as liked_by_me' => fn ($query) => $query->where('user_id', $request->user()->id),
                    'helpfuls as helpful_by_me' => fn ($query) => $query->where('user_id', $request->user()->id),
                ])
        );
    }

    public function toggleHelpful(Request $request, Post $post)
    {
        $this->authorize('view', $post);

        $helpful = $post->helpfuls()->where('user_id', $request->user()->id)->first();

        if ($helpful) {
            $helpful->delete();
        } else {
            $post->helpfuls()->create([
                'user_id' => $request->user()->id,
                'context' => 'helpful',
            ]);
        }

        return new PostResource(
            $post->fresh(['user', 'club', 'team', 'sport', 'sportSkills', 'attachments.file'])
                ->loadCount(['comments', 'likes', 'helpfuls'])
                ->loadExists([
                    'likes as liked_by_me' => fn ($query) => $query->where('user_id', $request->user()->id),
                    'helpfuls as helpful_by_me' => fn ($query) => $query->where('user_id', $request->user()->id),
                ])
        );
    }

    public function destroy(Request $request, Post $post)
    {
        abort_unless($post->user_id === $request->user()->id || $request->user()->can('delete', $post), 403);

        $post->load('attachments.file');

        foreach ($post->attachments as $attachment) {
            if ($attachment->file) {
                Storage::disk(UploadStorage::disk($attachment->file->path))->delete(array_filter([
                    $attachment->file->path,
                    $attachment->file->thumbnail_path,
                ]));
                $attachment->file->delete();
            }
        }

        if ($post->image) {
            Storage::disk(UploadStorage::disk($post->image))->delete($post->image);
        }

        $post->delete();

        return response()->json([
            'message' => self::POST_DELETED,
            'message_text' => __('platform.social.post_deleted'),
        ]);
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 50);
    }

    private function postsVisibleTo(User $user): Builder
    {
        $clubIds = $user->clubs()->pluck('clubs.id')->all();
        $teamIds = $user->teams()->pluck('teams.id')->all();

        return Post::query()
            ->where(function ($query) use ($user, $clubIds, $teamIds) {
                $query
                    ->where('visibility', 'public')
                    ->orWhere('user_id', $user->id)
                    ->orWhere(function ($query) use ($clubIds) {
                        $query
                            ->where('visibility', 'organization')
                            ->whereIn('club_id', $clubIds);
                    })
                    ->orWhere(function ($query) use ($teamIds) {
                        $query
                            ->where('visibility', 'team')
                            ->whereIn('team_id', $teamIds);
                    });
            })
            ->where(function ($query) use ($user) {
                $query
                    ->where('moderation_status', 'approved')
                    ->orWhere('user_id', $user->id);
            });
    }

    private function imageValidationRules(Request $request): array
    {
        return $request->hasFile('image')
            ? ['nullable', 'file', 'max:51200']
            : ['nullable', 'string', 'max:2048'];
    }

    private function normalizePostImagePath(string $image): string
    {
        $path = trim($image);

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $path = parse_url($path, PHP_URL_PATH) ?: '';
        }

        $path = ltrim($path, '/');

        foreach (['airmius-storage/', 'storage/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
            }
        }

        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '.')) {
            throw ValidationException::withMessages([
                'image' => 'Der Bildpfad ist ungültig.',
            ]);
        }

        return $path;
    }

    private function validSkillIdsForSport(array $skillIds, mixed $sportId): array
    {
        if (! $sportId || empty($skillIds)) {
            return [];
        }

        return SportSkill::query()
            ->where('sport_id', $sportId)
            ->whereIn('id', $skillIds)
            ->pluck('id')
            ->values()
            ->all();
    }

    private function canUseClub(User $user, Club $club): bool
    {
        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return true;
        }

        if (ClubPermissions::allows($club, $user, ClubPermissions::CONTENT_MANAGE)) {
            return true;
        }

        if (! $club->members_can_post_to_club) {
            throw ValidationException::withMessages([
                'club_id' => 'Mitglieder dürfen für diesen Verein keine Beiträge erstellen.',
            ]);
        }

        return $club->users()->where('users.id', $user->id)->exists()
            || $club->teams()->whereHas('users', fn ($query) => $query->where('users.id', $user->id))->exists();
    }

    private function canUseTeam(User $user, Team $team): bool
    {
        $team->loadMissing('club');

        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return true;
        }

        if (ClubPermissions::allowsForTeam($team, $user, ClubPermissions::CONTENT_MANAGE)) {
            return true;
        }

        if ($user->can('update', $team)) {
            return true;
        }

        if (! $team->club?->members_can_post_to_teams) {
            throw ValidationException::withMessages([
                'team_id' => 'Mitglieder dürfen für Teams dieses Vereins keine Beiträge erstellen.',
            ]);
        }

        return $team->users()->where('users.id', $user->id)->exists();
    }
}
