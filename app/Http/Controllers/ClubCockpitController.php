<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Team;
use App\Models\TeamJoinRequest;
use App\Models\User;
use App\Services\PlanFeatureService;
use App\Support\ClubRoles;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ClubCockpitController extends Controller
{
    public function __construct(private PlanFeatureService $planFeatures) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $hasFullClubAccess = $user->hasAnyRole(Roles::FULL_ACCESS);
        $hasManageableClubs = $hasFullClubAccess
            || Club::query()->where(function ($query) use ($user) {
                $this->scopeManageableClubs($query, $user);
            })->exists();

        abort_unless($hasManageableClubs, 403);

        $clubs = Club::query()
            ->when(! $hasFullClubAccess, function ($query) use ($user) {
                $this->scopeManageableClubs($query, $user);
            })
            ->with(['currentSubscription.plan'])
            ->withCount(['users', 'teams', 'externalMembers', 'posts'])
            ->orderBy('name')
            ->get()
            ->map(fn (Club $club) => $this->clubSummary($club))
            ->values();

        return Inertia::render('Auth/Dashboard/ClubCockpit/Index', [
            'clubs' => $clubs,
        ]);
    }

    private function scopeManageableClubs($query, User $user): void
    {
        $query->where(function ($clubQuery) use ($user) {
            $clubQuery
                ->where('owner_id', $user->id)
                ->orWhereHas('users', function ($memberQuery) use ($user) {
                    $memberQuery->where('users.id', $user->id);
                    ClubRoles::whereAny($memberQuery, ClubRoles::ELEVATED);
                });
        });
    }

    private function clubSummary(Club $club): array
    {
        $teamIds = Team::query()
            ->where('club_id', $club->id)
            ->pluck('id');
        $openInvoices = Invoice::query()
            ->where('club_id', $club->id)
            ->whereNotIn('status', ['paid', 'cancelled', 'void'])
            ->get(['id', 'amount', 'status', 'due_date']);
        $overdueInvoices = $openInvoices
            ->filter(fn (Invoice $invoice) => $invoice->due_date && $invoice->due_date->isPast());
        $pendingTeamRequests = $teamIds->isEmpty()
            ? 0
            : TeamJoinRequest::query()
                ->whereIn('team_id', $teamIds)
                ->where('status', 'pending')
                ->count();
        $pendingMembershipRequests = $club->membershipRequests()
            ->where('status', 'pending')
            ->count();
        $upcomingEvents = Event::query()
            ->where('status', 'scheduled')
            ->where('start_time', '>=', now())
            ->where(function ($query) use ($club, $teamIds) {
                $query->where('club_id', $club->id)
                    ->when($teamIds->isNotEmpty(), fn ($query) => $query->orWhereIn('team_id', $teamIds));
            })
            ->orderBy('start_time')
            ->limit(3)
            ->get(['id', 'title', 'type', 'start_time', 'location_name', 'location_city']);
        $storageBytes = (int) $club->files()->sum('size');
        $memberUsage = (int) $club->users_count + (int) $club->external_members_count;
        $sepaMissing = (int) DB::table('club_user')
            ->where('club_id', $club->id)
            ->where('membership_status', 'active')
            ->where(function ($query) {
                $query->where('contribution_amount', '>', 0)
                    ->where(function ($missing) {
                        $missing->whereNull('sepa_iban')
                            ->orWhere('sepa_iban', '')
                            ->orWhere('sepa_mandate_active', false);
                    });
            })
            ->count();
        $capabilities = $this->planFeatures->capabilities($club);
        $plan = $club->subscriptionPlan();
        $roleCoverage = $this->roleCoverage($club);
        $governance = $this->governance(
            $club,
            $roleCoverage,
            $openInvoices->count(),
            $overdueInvoices->count(),
            $sepaMissing,
            $pendingMembershipRequests + $pendingTeamRequests,
        );

        return [
            'id' => $club->id,
            'name' => $club->name,
            'plan' => [
                'name' => $plan?->name,
                'slug' => $plan?->slug,
                'member_limit' => $plan?->member_limit,
                'team_limit' => $plan?->team_limit,
                'storage_gb' => $plan?->storage_gb,
            ],
            'locked' => ! ($capabilities['club_cockpit'] ?? false),
            'capabilities' => $capabilities,
            'role_coverage' => $roleCoverage,
            'governance' => $governance,
            'stats' => [
                'members' => $memberUsage,
                'member_limit' => $plan?->member_limit,
                'teams' => (int) $club->teams_count,
                'team_limit' => $plan?->team_limit,
                'posts' => (int) $club->posts_count,
                'storage_bytes' => $storageBytes,
                'storage_gb' => $plan?->storage_gb,
                'open_invoice_amount' => (float) $openInvoices->sum('amount'),
                'open_invoice_count' => $openInvoices->count(),
                'overdue_invoice_count' => $overdueInvoices->count(),
                'pending_requests' => $pendingMembershipRequests + $pendingTeamRequests,
                'sepa_missing' => $sepaMissing,
                'upcoming_events' => $upcomingEvents->count(),
            ],
            'upcoming_events' => $upcomingEvents->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'type' => $event->type,
                'start_time' => $event->start_time,
                'location' => trim(collect([$event->location_name, $event->location_city])->filter()->implode(', ')),
            ])->values(),
            'actions' => $this->actions($club, $pendingMembershipRequests, $pendingTeamRequests, $openInvoices->count(), $sepaMissing, $upcomingEvents->count()),
        ];
    }

    private function roleCoverage(Club $club): array
    {
        $members = DB::table('club_user')
            ->where('club_id', $club->id)
            ->get(['role', 'roles', 'membership_status']);

        $elevatedMembers = $members->filter(function ($member) {
            $roles = ClubRoles::normalize($member->role, $this->decodePivotRoles($member->roles));

            return collect($roles)->intersect(ClubRoles::ELEVATED)->isNotEmpty();
        });

        return [
            'members_count' => $members->count(),
            'active_members_count' => $members->where('membership_status', 'active')->count(),
            'elevated_count' => $elevatedMembers->count(),
            'has_owner' => $elevatedMembers->contains(fn ($member) => in_array('owner', ClubRoles::normalize($member->role, $this->decodePivotRoles($member->roles)), true)),
            'has_admin' => $elevatedMembers->contains(fn ($member) => in_array('admin', ClubRoles::normalize($member->role, $this->decodePivotRoles($member->roles)), true)),
        ];
    }

    private function governance(Club $club, array $roleCoverage, int $openInvoices, int $overdueInvoices, int $sepaMissing, int $pendingRequests): array
    {
        $privacyOpen = (bool) $club->is_listed
            || (bool) $club->teams_are_listed
            || (bool) $club->members_can_post_to_club
            || (bool) $club->members_can_post_to_teams;

        $signals = [
            [
                'key' => 'verification',
                'state' => $club->verification_status === 'verified' ? 'strong' : ($club->verification_status === 'rejected' ? 'risk' : 'watch'),
            ],
            [
                'key' => 'roles',
                'state' => ((int) ($roleCoverage['elevated_count'] ?? 0)) >= 2 ? 'strong' : (((int) ($roleCoverage['elevated_count'] ?? 0)) === 1 ? 'watch' : 'risk'),
            ],
            [
                'key' => 'billing',
                'state' => $overdueInvoices > 0 ? 'risk' : ($openInvoices > 0 ? 'watch' : 'strong'),
            ],
            [
                'key' => 'sepa',
                'state' => $sepaMissing > 0 ? 'risk' : 'strong',
            ],
            [
                'key' => 'privacy',
                'state' => $privacyOpen ? 'watch' : 'strong',
            ],
            [
                'key' => 'requests',
                'state' => $pendingRequests > 0 ? 'watch' : 'strong',
            ],
        ];

        $stateScores = ['strong' => 90, 'watch' => 67, 'risk' => 25];
        $score = (int) round(collect($signals)->avg(fn (array $signal) => $stateScores[$signal['state']] ?? 50));

        return [
            'version' => '2026-06-03.club_governance.v1',
            'score' => $score,
            'level' => $score >= 80 ? 'strong' : ($score >= 50 ? 'watch' : 'risk'),
            'signals' => $signals,
            'tasks' => collect($signals)
                ->filter(fn (array $signal) => $signal['state'] !== 'strong')
                ->map(fn (array $signal) => [
                    'key' => $signal['key'],
                    'state' => $signal['state'],
                    'label_key' => 'clubs.governance.tasks.'.$signal['key'],
                ])
                ->values(),
        ];
    }

    private function decodePivotRoles(mixed $roles): array
    {
        if (is_array($roles)) {
            return $roles;
        }

        if (! is_string($roles) || trim($roles) === '') {
            return [];
        }

        $decoded = json_decode($roles, true);

        return is_array($decoded) ? $decoded : [];
    }
    private function actions(Club $club, int $membershipRequests, int $teamRequests, int $openInvoices, int $sepaMissing, int $upcomingEvents): array
    {
        return [
            [
                'key' => 'requests',
                'count' => $membershipRequests + $teamRequests,
                'href' => route('auth.club-memberships.index'),
                'icon' => 'las la-user-check',
            ],
            [
                'key' => 'billing',
                'count' => $openInvoices,
                'href' => route('auth.club-memberships.index'),
                'icon' => 'las la-file-invoice-dollar',
            ],
            [
                'key' => 'sepa',
                'count' => $sepaMissing,
                'href' => route('auth.club-memberships.index'),
                'icon' => 'las la-university',
            ],
            [
                'key' => 'structure',
                'count' => (int) $club->teams_count,
                'href' => route('auth.teams.index'),
                'icon' => 'las la-sitemap',
            ],
            [
                'key' => 'events',
                'count' => $upcomingEvents,
                'href' => route('auth.events.index'),
                'icon' => 'las la-calendar-check',
            ],
        ];
    }
}
