<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Post;
use App\Models\Sport;
use App\Models\UserSportSkill;
use App\Services\GamificationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private GamificationService $gamification) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $search = trim((string) $request->input('search', ''));

        $users = User::query()
            ->select(['id', 'name', 'email', 'created_at'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();
        return Inertia::render('Auth/Dashboard/Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, User $user)
    {
        $this->authorize('view', $user);

        $viewer = $request->user();
        $canManageRoles = $viewer->can('assignRoles', $user);
        $profileVisible = $user->isProfileVisibleTo($viewer);
        $user->loadCount(['followers', 'following', 'posts']);
        $user->load([
            'clubs' => fn ($query) => $query->select('clubs.id', 'name')->orderBy('name')->limit(8),
            'teams' => fn ($query) => $query->select('teams.id', 'club_id', 'name', 'sport_type')->orderBy('name')->limit(8),
            'sportProfiles.sport:id,name,slug,category',
            'sportSkills' => fn ($query) => $query
                ->where('is_visible', true)
                ->with([
                    'sport:id,name,slug,category',
                    'skill:id,sport_id,key,name,description,sort_order',
                    'endorsements.endorser:id,name,profile_photo_path',
                ])
                ->withCount('endorsements')
                ->orderBy('sport_id')
                ->orderBy('sport_skill_id'),
            'recommendationsReceived' => fn ($query) => $query
                ->where(function ($query) use ($viewer, $user) {
                    $query->where('status', 'approved')
                        ->when($viewer->is($user), fn ($query) => $query->orWhere('status', 'pending'));
                })
                ->with('author:id,name,profile_photo_path')
                ->latest('id')
                ->limit(8),
        ]);

        $gamification = $this->gamification->summaryFor($user);

        $posts = Post::query()
            ->where('user_id', $user->id)
            ->where('moderation_status', '!=', 'removed')
            ->where(function ($query) use ($viewer) {
                $query->where('visibility', 'public')
                    ->orWhere('user_id', $viewer->id)
                    ->orWhere(function ($query) use ($viewer) {
                        $query->where('visibility', 'organization')
                            ->whereHas('club.users', fn ($q) => $q->where('users.id', $viewer->id));
                    })
                    ->orWhere(function ($query) use ($viewer) {
                        $query->where('visibility', 'team')
                            ->whereHas('team.users', fn ($q) => $q->where('users.id', $viewer->id));
                    });
            })
            ->with(['club:id,name', 'team:id,name,club_id'])
            ->withCount([
                'comments' => fn ($query) => $query->where('moderation_status', '!=', 'removed'),
                'likes',
            ])
            ->latest('id')
            ->limit(8)
            ->get();

        return Inertia::render('Auth/Dashboard/Users/Profile', [
            'profileUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $profileVisible || $canManageRoles ? $user->email : null,
                'athlete_license_number' => $profileVisible ? $user->athlete_license_number : null,
                'bio' => $profileVisible ? $user->bio : null,
                'profile_visibility' => $user->profile_visibility,
                'profile_photo_url' => $user->profile_photo_url,
                'followers_count' => $user->followers_count,
                'following_count' => $user->following_count,
                'posts_count' => $user->posts_count,
                'clubs' => $profileVisible ? $user->clubs : [],
                'teams' => $profileVisible ? $user->teams : [],
                'sport_profiles' => $profileVisible ? $user->sportProfiles->map(fn ($profile) => [
                    'id' => $profile->id,
                    'status' => $profile->status,
                    'experience_level' => $profile->experience_level,
                    'sport' => $profile->sport,
                ])->values() : [],
                'sport_skills' => $profileVisible ? $user->sportSkills->map(fn (UserSportSkill $userSkill) => [
                    'id' => $userSkill->id,
                    'self_level' => $userSkill->self_level,
                    'notes' => $userSkill->notes,
                    'sport' => $userSkill->sport,
                    'skill' => $userSkill->skill,
                    'endorsements_count' => $userSkill->endorsements_count,
                    'viewer_has_endorsed' => $userSkill->endorsements->contains('endorser_id', $viewer->id),
                    'endorsements' => $userSkill->endorsements->take(4)->map(fn ($endorsement) => [
                        'id' => $endorsement->id,
                        'relationship' => $endorsement->relationship,
                        'level' => $endorsement->level,
                        'comment' => $endorsement->comment,
                        'endorser' => $endorsement->endorser,
                    ])->values(),
                ])->values() : [],
                'recommendations' => $profileVisible ? $user->recommendationsReceived->map(fn ($recommendation) => [
                    'id' => $recommendation->id,
                    'relationship' => $recommendation->relationship,
                    'body' => $recommendation->body,
                    'status' => $recommendation->status,
                    'author' => $recommendation->author,
                    'created_at' => $recommendation->created_at,
                ])->values() : [],
                'gamification' => [
                    'xp' => $gamification['xp'],
                    'level' => $gamification['level'],
                    'rank' => $gamification['rank'],
                    'title' => $gamification['title'],
                    'next_level_xp' => $gamification['next_level_xp'],
                    'current_level_xp' => $gamification['current_level_xp'],
                    'progress' => $gamification['progress'],
                    'trust_score' => $gamification['trust_score'],
                    'trust_multiplier' => $gamification['trust_multiplier'],
                    'streak_days' => $gamification['streak_days'],
                ],
                'roles' => $canManageRoles ? $user->getRoleNames()->values()->all() : [],
                'permissions' => $canManageRoles
                    ? $user->getAllPermissions()->pluck('name')->values()->all()
                    : [],
            ],
            'posts' => $profileVisible ? $posts : [],
            'viewer' => [
                'is_self' => $viewer->is($user),
                'is_following' => $user->isFollowedBy($viewer),
                'can_follow' => ! $viewer->is($user) && $viewer->can('follow.user'),
                'can_manage_roles' => $canManageRoles,
                'can_view_private_profile' => $profileVisible,
            ],
            'sports' => Sport::query()
                ->where('is_active', true)
                ->select(['id', 'name', 'slug', 'category'])
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
