<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Post;
use App\Models\Sport;
use App\Models\SportSkill;
use App\Models\Team;
use App\Models\User;
use App\Services\GamificationService;
use App\Services\MediaOptimizer;
use App\Services\ModerationService;
use App\Services\PostService;
use App\Support\ClubRoles;
use App\Support\UploadStorage;
use App\Support\Roles;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PostController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private PostService $service,
        private MediaOptimizer $mediaOptimizer,
        private GamificationService $gamification,
        private ModerationService $moderation,
    ) {}

    public function index()
    {
        $user = auth()->user();
        $feedUserIds = collect([$user->id])
            ->merge($user->friendships()->pluck('friend_id'))
            ->merge($user->following()->pluck('followed_id'))
            ->unique()
            ->values()
            ->all();

        $posts = Post::query()
            ->where('moderation_status', '!=', 'removed')
            ->where(function ($query) use ($user, $feedUserIds) {
                $query->where('user_id', $user->id)
                    ->orWhere(function ($query) use ($feedUserIds) {
                        $query->where('visibility', 'public')
                            ->whereIn('user_id', $feedUserIds);
                    })
                    ->orWhere(function ($query) use ($user) {
                        $query->where('visibility', 'organization')
                            ->whereHas('club', fn ($clubQuery) => $clubQuery
                                ->where('is_listed', true)
                                ->whereHas('users', fn ($q) => $q->where('users.id', $user->id)));
                    })
                    ->orWhere(function ($query) use ($user) {
                        $query->where('visibility', 'team')
                            ->whereHas('team', fn ($teamQuery) => $teamQuery
                                ->whereHas('club', fn ($clubQuery) => $clubQuery
                                    ->where('is_listed', true)
                                    ->where('teams_are_listed', true))
                                ->whereHas('users', fn ($q) => $q->where('users.id', $user->id)));
                    });
            })
            ->where(function ($query) {
                $query->whereNull('club_id')
                    ->orWhere('visibility', '!=', 'organization')
                    ->orWhereHas('club', fn ($clubQuery) => $clubQuery->where('is_listed', true));
            })
            ->where(function ($query) {
                $query->whereNull('team_id')
                    ->orWhere('visibility', '!=', 'team')
                    ->orWhereHas('team.club', fn ($clubQuery) => $clubQuery
                        ->where('is_listed', true)
                        ->where('teams_are_listed', true));
            })
            ->with([
                'user:id,name,profile_photo_path',
                'club' => fn ($query) => $query->select('id', 'name'),
                'team' => fn ($query) => $query->select('id', 'name', 'club_id'),
                'sport:id,name,slug,category',
                'sportSkills:id,sport_id,key,name',
                'attachments.file:id,display_name,path,type,size',
                'comments' => fn ($query) => $query
                    ->where('moderation_status', '!=', 'removed')
                    ->with('user:id,name,profile_photo_path')
                    ->withCount('likes')
                    ->latest('id')
                    ->limit(3),
            ])
            ->withCount(['comments' => fn ($query) => $query->where('moderation_status', '!=', 'removed')])
            ->withCount('likes')
            ->withCount('helpfuls')
            ->withExists([
                'likes as liked_by_me' => fn ($query) => $query->where('user_id', $user->id),
                'helpfuls as helpful_by_me' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(function (Post $post) use ($user) {
                $post->setAttribute('can_update', $user->can('update', $post));
                $post->setAttribute('can_delete', $user->can('delete', $post));

                return $post;
            });

        return Inertia::render('Auth/Dashboard/Feed/Index', [
            'posts' => $posts,
            'clubs' => Club::query()
                ->where('is_listed', true)
                ->when(
                    ! $user->hasAnyRole(Roles::FULL_ACCESS),
                    fn ($query) => $query->where(function ($query) use ($user) {
                        $query->where(function ($query) use ($user) {
                            $query->where('members_can_post_to_club', true)
                                ->where(function ($query) use ($user) {
                                    $query->whereHas('users', fn ($userQuery) => $userQuery->where('users.id', $user->id))
                                        ->orWhereHas('teams.users', fn ($userQuery) => $userQuery->where('users.id', $user->id));
                                });
                        })->orWhereHas('users', function ($userQuery) use ($user) {
                            $userQuery->where('users.id', $user->id);
                            ClubRoles::whereAny($userQuery, ClubRoles::ELEVATED);
                        });
                    }),
                )
                ->select(['id', 'name', 'is_listed', 'teams_are_listed', 'members_can_post_to_club', 'members_can_post_to_teams'])
                ->orderBy('name')
                ->get(),
            'teams' => Team::query()
                ->whereHas('club', fn ($clubQuery) => $clubQuery
                    ->where('is_listed', true)
                    ->where('teams_are_listed', true))
                ->when(
                    ! $user->hasAnyRole(Roles::FULL_ACCESS),
                    fn ($query) => $query->where(function ($query) use ($user) {
                        $query->where(function ($query) use ($user) {
                            $query->whereHas('club', fn ($clubQuery) => $clubQuery->where('members_can_post_to_teams', true))
                                ->whereHas('users', fn ($userQuery) => $userQuery->where('users.id', $user->id));
                        })->orWhereHas('club.users', function ($userQuery) use ($user) {
                            $userQuery->where('users.id', $user->id);
                            ClubRoles::whereAny($userQuery, ClubRoles::ELEVATED);
                        });
                    }),
                )
                ->select(['id', 'club_id', 'name'])
                ->orderBy('name')
                ->get(),
            'visibilities' => Post::VISIBILITIES,
            'postTypes' => Post::TYPES,
            'sports' => Sport::query()
                ->where('is_active', true)
                ->with(['skills' => fn ($query) => $query->select('id', 'sport_id', 'key', 'name')->orderBy('sort_order')])
                ->select(['id', 'name', 'slug', 'category'])
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Post::class);
        $user = $request->user();

        $data = $request->validate([
            'club_id' => ['nullable', 'exists:clubs,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'visibility' => ['required', Rule::in(Post::VISIBILITIES)],
            'post_type' => ['required', Rule::in(Post::TYPES)],
            'content_origin' => ['required', Rule::in(Post::CONTENT_ORIGINS)],
            'sport_id' => ['nullable', 'exists:sports,id'],
            'sport_skill_ids' => ['nullable', 'array', 'max:8'],
            'sport_skill_ids.*' => ['integer', 'exists:sport_skills,id'],
            'content' => ['nullable', 'required_without_all:image,attachments', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,mp4,mov,webm,ogg,pdf,doc,docx,xls,xlsx,txt,zip', 'max:51200'],
        ]);

        if (! empty($data['team_id'])) {
            $team = Team::findOrFail($data['team_id']);
            abort_unless($this->canUseTeam($user, $team), 403);
            $data['club_id'] = $team->club_id;
        }

        if (! empty($data['club_id'])) {
            abort_unless($this->canUseClub($user, Club::findOrFail($data['club_id'])), 403);
        }

        abort_if($data['visibility'] === 'team' && empty($data['team_id']), 422, 'Team posts brauchen ein Team.');
        abort_if($data['visibility'] === 'organization' && empty($data['club_id']), 422, 'Organization posts brauchen eine Organization.');

        if ($request->hasFile('image')) {
            $data['image'] = $this->mediaOptimizer->store($request->file('image'), 'posts')['path'];
        }

        $data['attachments'] = $request->file('attachments', []);

        $data['content'] ??= '';
        $data['club_id'] = $data['club_id'] ?? null;
        $data['team_id'] = $data['team_id'] ?? null;
        $data['sport_id'] = $data['sport_id'] ?? null;
        $data['sport_skill_ids'] = $this->validSkillIdsForSport($data['sport_skill_ids'] ?? [], $data['sport_id']);

        $post = $this->service->create(auth()->user(), $data);
        $this->moderation->flagIfNeeded($post, $post->content, auth()->id());

        if (in_array($post->post_type, ['knowledge', 'training_drill', 'tactic', 'analysis', 'experience'], true)) {
            $this->gamification->grant(auth()->user(), 'content_created', $post, [
                'post_type' => $post->post_type,
                'sport_id' => $post->sport_id,
            ]);
        }

        if ($post->club_id && in_array($post->post_type, ['knowledge', 'training_drill', 'tactic', 'analysis', 'experience', 'club_update'], true)) {
            $this->gamification->grantToClub(auth()->user(), $post->club, 'club_informative_post', $post, [
                'post_type' => $post->post_type,
                'sport_id' => $post->sport_id,
            ]);
        }

        if (auth()->user()->hasAnyRole(['coach', 'assistant_coach', 'performance_coach', 'fitness_coach'])
            && in_array($post->post_type, ['knowledge', 'training_drill', 'tactic', 'analysis'], true)
        ) {
            $this->gamification->grantToTrainer(auth()->user(), 'coach_knowledge_shared', $post, [
                'post_type' => $post->post_type,
                'sport_id' => $post->sport_id,
            ]);
        }

        return back()->with('success', 'Beitrag erstellt.');
    }

    public function update(Request $request, Post $post)
    {
        $this->authorize('update', $post);
        $user = $request->user();

        $data = $request->validate([
            'club_id' => ['nullable', 'exists:clubs,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'visibility' => ['required', Rule::in(Post::VISIBILITIES)],
            'post_type' => ['required', Rule::in(Post::TYPES)],
            'content_origin' => ['required', Rule::in(Post::CONTENT_ORIGINS)],
            'sport_id' => ['nullable', 'exists:sports,id'],
            'sport_skill_ids' => ['nullable', 'array', 'max:8'],
            'sport_skill_ids.*' => ['integer', 'exists:sport_skills,id'],
            'content' => ['nullable', 'required_without_all:image,attachments', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,mp4,mov,webm,ogg,pdf,doc,docx,xls,xlsx,txt,zip', 'max:51200'],
        ]);

        if (! empty($data['team_id'])) {
            $team = Team::findOrFail($data['team_id']);
            abort_unless($this->canUseTeam($user, $team), 403);
            $data['club_id'] = $team->club_id;
        }

        if (! empty($data['club_id'])) {
            abort_unless($this->canUseClub($user, Club::findOrFail($data['club_id'])), 403);
        }

        abort_if($data['visibility'] === 'team' && empty($data['team_id']), 422, 'Team posts brauchen ein Team.');
        abort_if($data['visibility'] === 'organization' && empty($data['club_id']), 422, 'Organization posts brauchen eine Organization.');

        if ($request->hasFile('image')) {
            if ($post->image) {
                Storage::disk(UploadStorage::disk())->delete($post->image);
            }

            $data['image'] = $this->mediaOptimizer->store($request->file('image'), 'posts')['path'];
        } else {
            unset($data['image']);
        }

        $data['content'] ??= '';
        $data['club_id'] = $data['club_id'] ?? null;
        $data['team_id'] = $data['team_id'] ?? null;
        $data['sport_id'] = $data['sport_id'] ?? null;
        $skillIds = $this->validSkillIdsForSport($data['sport_skill_ids'] ?? [], $data['sport_id']);
        unset($data['attachments']);
        unset($data['sport_skill_ids']);

        $post->update($data);
        $this->moderation->flagIfNeeded($post, $post->content, auth()->id());
        $post->sportSkills()->sync($skillIds);
        $this->service->attachFiles($post, auth()->user(), $request->file('attachments', []));
        $this->service->recordActivity($post, 'post.updated', auth()->user());

        return back()->with('success', 'Beitrag aktualisiert.');
    }

    public function destroy(Post $post)
    {
        $this->authorize('delete', $post);

        $post->load('attachments.file');

        foreach ($post->attachments as $attachment) {
            if ($attachment->file) {
                Storage::disk(UploadStorage::disk())->delete(array_filter([
                    $attachment->file->path,
                    $attachment->file->thumbnail_path,
                ]));
                $attachment->file->delete();
            }
        }

        $post->delete();

        return back()->with('success', 'Beitrag gelöscht.');
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

        if ($user->can('update', $club)) {
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
