<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\FriendInvitation;
use App\Models\Friendship;
use App\Models\Post;
use App\Models\Sport;
use App\Models\UserBlock;
use App\Models\UserSport;
use App\Models\UserBadge;
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

        $activeTab = (string) $request->query('tab', 'overview');
        $allowedTabs = ['overview', 'sports', 'skills', 'posts', 'network', 'recommendations'];

        if (! in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'overview';
        }

        $viewer = $request->user();
        $canManageRoles = $viewer->can('assignRoles', $user);
        $profileVisible = $user->isProfileVisibleTo($viewer);
        $friendship = null;
        $pendingFriendInvitation = null;
        $viewerHasBlocked = false;
        $viewerIsBlocked = false;

        if (! $viewer->is($user)) {
            $viewerHasBlocked = $viewer->hasBlocked($user);
            $viewerIsBlocked = $user->hasBlocked($viewer);

            $friendship = Friendship::query()
                ->where('user_id', $viewer->id)
                ->where('friend_id', $user->id)
                ->first();

            $pendingFriendInvitation = FriendInvitation::query()
                ->where('status', 'pending')
                ->where(function ($query) use ($viewer, $user) {
                    $query->where(function ($query) use ($viewer, $user) {
                        $query->where('sender_id', $viewer->id)
                            ->where('recipient_id', $user->id);
                    })->orWhere(function ($query) use ($viewer, $user) {
                        $query->where('sender_id', $user->id)
                            ->where('recipient_id', $viewer->id);
                    });
                })
                ->first();
        }

        $friendshipStatus = 'none';

        if ($friendship) {
            $friendshipStatus = 'friends';
        } elseif ($pendingFriendInvitation?->sender_id === $viewer->id) {
            $friendshipStatus = 'sent';
        } elseif ($pendingFriendInvitation?->recipient_id === $viewer->id) {
            $friendshipStatus = 'received';
        }

        $user->loadCount(['followers', 'following', 'posts']);
        $user->load([
            'clubs' => fn ($query) => $query->select('clubs.id', 'name')->orderBy('name')->limit(8),
            'teams' => fn ($query) => $query->select('teams.id', 'club_id', 'name', 'sport_type')->orderBy('name')->limit(8),
            'sportProfiles.sport:id,name,slug,category',
        ]);

        $gamification = $this->gamification->summaryFor($user);

        $sportSkills = collect();
        $recommendations = collect();
        $posts = collect();

        if ($profileVisible && $activeTab === 'skills') {
            $sportSkills = $user->sportSkills()
                ->where('is_visible', true)
                ->with([
                    'sport:id,name,slug,category',
                    'skill:id,sport_id,key,name,description,sort_order',
                    'endorsements.endorser:id,name,profile_photo_path',
                ])
                ->withCount('endorsements')
                ->orderBy('sport_id')
                ->orderBy('sport_skill_id')
                ->get();
        }

        if ($profileVisible && $activeTab === 'recommendations') {
            $recommendations = $user->recommendationsReceived()
                ->where(function ($query) use ($viewer, $user) {
                    $query->where('status', 'approved')
                        ->when($viewer->is($user), fn ($query) => $query->orWhere('status', 'pending'));
                })
                ->orWhere(function ($query) use ($viewer, $user) {
                    $query->where('profile_user_id', $user->id)
                        ->where('author_id', $viewer->id)
                        ->where('status', 'pending');
                })
                ->with('author:id,name,profile_photo_path')
                ->latest('id')
                ->limit(12)
                ->get();
        }

        if ($profileVisible && $activeTab === 'posts') {
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
                    'comments' => fn ($query) => $query->where('moderation_status', 'approved'),
                    'likes',
                ])
                ->latest('id')
                ->limit(12)
                ->get();
        }

        return Inertia::render('Auth/Dashboard/Users/Profile', [
            'profileUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $profileVisible || $canManageRoles ? $user->email : null,
                'athlete_license_number' => $profileVisible ? $user->athlete_license_number : null,
                'bio' => $profileVisible ? $user->bio : null,
                'profile_visibility' => $user->profile_visibility,
                'profile_photo_url' => $user->profile_photo_url,
                'direct_message_privacy' => $user->direct_message_privacy ?? 'everyone',
                'friend_request_privacy' => $user->friend_request_privacy ?? 'everyone',
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
                    'performance_metrics' => $this->visiblePerformanceMetrics($profile, $viewer, $user),
                ])->values() : [],
                'sport_skills' => $profileVisible ? $sportSkills->map(fn (UserSportSkill $userSkill) => [
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
                'recommendations' => $profileVisible ? $recommendations->map(fn ($recommendation) => [
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
                    'xp_to_next_level' => $gamification['xp_to_next_level'],
                    'earned_today' => $gamification['earned_today'],
                    'trust_score' => $gamification['trust_score'],
                    'trust_multiplier' => $gamification['trust_multiplier'],
                    'streak_days' => $gamification['streak_days'],
                    'health_label' => $gamification['health_label'],
                ],
                'badges' => UserBadge::query()
                    ->where('awardable_type', User::class)
                    ->where('awardable_id', $user->id)
                    ->with('badge:id,key,name,description,icon')
                    ->latest('id')
                    ->limit(12)
                    ->get()
                    ->pluck('badge')
                    ->values(),
                'roles' => $canManageRoles ? $user->getRoleNames()->values()->all() : [],
                'permissions' => $canManageRoles
                    ? $user->getAllPermissions()->pluck('name')->values()->all()
                    : [],
            ],
            'posts' => $profileVisible ? $posts : [],
            'activeTab' => $activeTab,
            'viewer' => [
                'is_self' => $viewer->is($user),
                'is_following' => $user->isFollowedBy($viewer),
                'can_follow' => ! $viewer->is($user) && $viewer->can('follow.user'),
                'friendship_status' => $friendshipStatus,
                'friend_invitation_id' => $pendingFriendInvitation?->id,
                'can_send_friend_request' => ! $viewer->is($user)
                    && $friendshipStatus === 'none'
                    && ! $viewerHasBlocked
                    && ! $viewerIsBlocked
                    && $user->allowsFriendRequestsFrom($viewer),
                'can_send_message' => ! $viewer->is($user)
                    && ! $viewerHasBlocked
                    && ! $viewerIsBlocked
                    && $user->allowsDirectMessagesFrom($viewer),
                'has_blocked' => $viewerHasBlocked,
                'is_blocked' => $viewerIsBlocked,
                'can_manage_roles' => $canManageRoles,
                'can_view_private_profile' => $profileVisible,
            ],
            'sports' => $profileVisible && $viewer->is($user) && $activeTab === 'sports'
                ? Sport::query()
                    ->where('is_active', true)
                    ->select(['id', 'name', 'slug', 'category'])
                    ->orderBy('sort_order')
                    ->get()
                : [],
        ]);
    }

    public function block(Request $request, User $user)
    {
        abort_if($request->user()->is($user), 422, 'Du kannst dich nicht selbst blockieren.');

        UserBlock::firstOrCreate([
            'user_id' => $request->user()->id,
            'blocked_user_id' => $user->id,
        ]);

        Friendship::query()
            ->where(function ($query) use ($request, $user) {
                $query->where('user_id', $request->user()->id)
                    ->where('friend_id', $user->id);
            })
            ->orWhere(function ($query) use ($request, $user) {
                $query->where('user_id', $user->id)
                    ->where('friend_id', $request->user()->id);
            })
            ->delete();

        FriendInvitation::query()
            ->where('status', 'pending')
            ->where(function ($query) use ($request, $user) {
                $query->where(function ($query) use ($request, $user) {
                    $query->where('sender_id', $request->user()->id)
                        ->where('recipient_id', $user->id);
                })->orWhere(function ($query) use ($request, $user) {
                    $query->where('sender_id', $user->id)
                        ->where('recipient_id', $request->user()->id);
                });
            })
            ->update([
                'status' => 'declined',
                'responded_at' => now(),
            ]);

        return back()->with('success', 'Person wurde blockiert.');
    }

    public function unblock(Request $request, User $user)
    {
        UserBlock::query()
            ->where('user_id', $request->user()->id)
            ->where('blocked_user_id', $user->id)
            ->delete();

        return back()->with('success', 'Blockierung wurde aufgehoben.');
    }

    private function visiblePerformanceMetrics(UserSport $profile, User $viewer, User $profileUser): array
    {
        $metrics = $profile->performance_metrics ?? [];
        $visibility = $profile->performance_visibility ?? [];
        $isSelf = (int) $viewer->id === (int) $profileUser->id;

        return collect($metrics)
            ->filter(fn ($value, $key) => $value !== null && $value !== '' && ($isSelf || ($visibility[$key] ?? 'private') === 'public'))
            ->map(fn ($value, $key) => [
                'key' => $key,
                'label' => $this->performanceMetricLabel((string) $key),
                'value' => $value,
                'visibility' => $visibility[$key] ?? 'private',
            ])
            ->values()
            ->all();
    }

    private function performanceMetricLabel(string $key): string
    {
        return [
            'weekly_km' => 'Aktuelle Wochen-km',
            'longest_run_km' => 'Längster Lauf',
            'run_best_100m_time' => '100-m-Bestzeit',
            'run_best_200m_time' => '200-m-Bestzeit',
            'run_best_400m_time' => '400-m-Bestzeit',
            'run_best_800m_time' => '800-m-Bestzeit',
            'run_best_1500m_time' => '1500-m-Bestzeit',
            'run_best_3000m_time' => '3000-m-Bestzeit',
            'best_100m_time' => '100-m-Bestzeit',
            'best_200m_time' => '200-m-Bestzeit',
            'best_400m_time' => '400-m-Bestzeit',
            'best_800m_time' => '800-m-Bestzeit',
            'best_1500m_time' => '1500-m-Bestzeit',
            'best_3000m_time' => '3000-m-Bestzeit',
            'best_5k_time' => '5-km-Bestzeit',
            'best_10k_time' => '10-km-Bestzeit',
            'best_half_marathon_time' => 'Halbmarathon-Bestzeit',
            'best_marathon_time' => 'Marathon-Bestzeit',
            'vma_kmh' => 'VMA',
            'training_experience_months' => 'Trainingserfahrung',
            'weekly_sessions' => 'Einheiten pro Woche',
            'training_goal' => 'Trainingsziel',
            'bodyweight_kg' => 'Körpergewicht',
            'bench_press_1rm_kg' => 'Bankdrücken 1RM',
            'squat_1rm_kg' => 'Kniebeuge 1RM',
            'deadlift_1rm_kg' => 'Kreuzheben 1RM',
            'overhead_press_1rm_kg' => 'Schulterdrücken 1RM',
            'leg_press_1rm_kg' => 'Beinpresse max.',
            'pullups_max_reps' => 'Klimmzüge max.',
            'dips_max_reps' => 'Dips max.',
            'pushups_max_reps' => 'Liegestütze max.',
            'plank_seconds' => 'Plank-Zeit',
            'wall_sit_seconds' => 'Wall-Sit-Zeit',
            'burpees_1min' => 'Burpees in 1 Minute',
            'jump_rope_1min' => 'Seilspringen max./Minute',
            'equipment' => 'Equipment',
            'main_lifts' => 'Weitere Kraftwerte / Notizen',
            'weak_points' => 'Schwachstellen',
            'longest_ride_km' => 'Längste Fahrt',
            'weekly_elevation_m' => 'Höhenmeter pro Woche',
            'ftp_watts' => 'FTP',
            'power_20min_watts' => '20-Minuten-Leistung',
            'threshold_hr_bpm' => 'Schwellenpuls',
            'max_hr_bpm' => 'Maximalpuls',
            'avg_speed_kmh' => 'Durchschnittsgeschwindigkeit',
            'cadence_rpm' => 'Trittfrequenz',
            'bike_type' => 'Radtyp',
            'terrain_preference' => 'Terrain / Strecke',
            'pool_length_m' => 'Beckenlänge',
            'technique_level' => 'Technikniveau',
            'main_stroke' => 'Hauptlage',
            'swim_best_50m_time' => '50-m-Zeit',
            'swim_best_100m_time' => '100-m-Zeit',
            'swim_best_200m_time' => '200-m-Zeit',
            'swim_best_400m_time' => '400-m-Zeit',
            'swim_best_800m_time' => '800-m-Zeit',
            'swim_best_1500m_time' => '1500-m-Zeit',
            'weekly_meters' => 'Wochenmeter',
            'position' => 'Position',
            'season_phase' => 'Saisonphase',
            'match_day' => 'Spieltag',
            'training_days' => 'Teamtrainingstage',
            'matches_per_week' => 'Spiele pro Woche',
            'match_minutes' => 'Spielminuten',
            'preferred_foot_or_side' => 'Starke Seite',
            'sprint_30m_time' => '30-m-Sprint',
            'cooper_12min_m' => 'Cooper-Test',
            'yo_yo_level' => 'Yo-Yo-Test',
            'vertical_jump_cm' => 'Sprunghöhe',
            'focus_needs' => 'Schwerpunkte',
            'playing_level' => 'Spielniveau',
            'dominant_hand' => 'Starke Hand',
            'match_frequency' => 'Matchhäufigkeit',
            'serve_speed_kmh' => 'Aufschlaggeschwindigkeit',
            'technical_focus' => 'Technischer Schwerpunkt',
            'weight_class_kg' => 'Gewichtsklasse / Körpergewicht',
            'sparring_frequency' => 'Sparring',
            'competition_date' => 'Wettkampf / Prüfung',
            'weekly_hours' => 'Trainingsstunden pro Woche',
            'longest_session_minutes' => 'Längste Einheit',
            'primary_disciplines' => 'Disziplinen / Schwerpunkte',
            'race_goal' => 'Ziel / Event',
            'mobility_goal' => 'Beweglichkeitsziel',
            'pain_areas' => 'Schmerzbereiche',
            'current_frequency' => 'Aktueller Umfang',
            'current_volume' => 'Aktueller Umfang',
            'performance_reference' => 'Leistungsreferenz',
            'injuries' => 'Verletzungen / Einschränkungen',
            'available_days' => 'Verfügbare Trainingstage',
            'experience' => 'Erfahrung',
        ][$key] ?? str($key)->replace('_', ' ')->headline()->toString();
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
