<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Ride;
use App\Models\Team;
use App\Models\TeamFee;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Support\Collection;

class TeamDailyLifeService
{
    public function forTeam(Team $team, User $viewer): array
    {
        $members = $team->users()
            ->select('users.id', 'users.name', 'users.birth_date', 'users.guardian_user_id', 'users.guardian_email')
            ->orderBy('users.name')
            ->get();
        $teamUserIds = $members->pluck('id')->map(fn ($id) => (int) $id)->all();
        $viewerMembership = $members->firstWhere('id', $viewer->id);
        $viewerRole = $viewerMembership?->pivot?->role;
        $isTeamMember = $viewerMembership !== null;
        $canManageTeam = $viewer->can('update', $team);
        $canManageOperations = $canManageTeam || in_array($viewerRole, TeamRoles::TEAM_STAFF_ROLES, true);
        $events = Event::query()
            ->where('team_id', $team->id)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('start_time', [now()->copy()->subMonths(6), now()->copy()->addYear()])
            ->orderBy('start_time')
            ->limit(200)
            ->get();
        $upcoming = $events
            ->filter(fn (Event $event) => $event->start_time?->isFuture())
            ->values();
        $nextEvent = $upcoming->first();
        $participantRecords = $nextEvent
            ? EventParticipant::query()
                ->where('event_id', $nextEvent->id)
                ->whereIn('user_id', $teamUserIds)
                ->get(['user_id', 'status'])
            : collect();
        $attendance = $nextEvent ? $this->attendance($nextEvent, $teamUserIds, $participantRecords) : null;
        $missingResponses = $nextEvent && $canManageOperations
            ? $this->missingResponses($members, $participantRecords)
            : [];
        $viewerResponse = $participantRecords->firstWhere('user_id', $viewer->id)?->status;
        $rides = $this->rides($team, $nextEvent, $isTeamMember || $canManageTeam);
        $cash = $this->cashBox($team, $viewer, $canManageOperations);
        $guardian = $this->guardianMode($members, $canManageOperations);
        $tasks = $this->tasks(
            $nextEvent,
            $attendance,
            $missingResponses,
            $rides,
            $cash,
            $guardian,
            $upcoming->count(),
            $canManageOperations,
            $isTeamMember,
            $viewerResponse,
        );
        $materials = $this->materials($nextEvent);
        $season = $this->seasonPlanning($events, $upcoming);

        return [
            'version' => '2026-08-08.airmius_team_home.v2',
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'sport_type' => $team->sport_type,
                'members_count' => count($teamUserIds),
                'viewer_role' => $viewerRole,
            ],
            'access' => [
                'is_team_member' => $isTeamMember,
                'can_manage_team' => $canManageTeam,
                'can_manage_operations' => $canManageOperations,
                'can_view_response_details' => $canManageOperations,
                'can_manage_cash_box' => $canManageOperations,
            ],
            'today' => [
                'next_event_id' => $nextEvent?->id,
                'primary_action' => $this->primaryAction($tasks, $team, $nextEvent),
                'open_tasks' => count($tasks),
                'attendance_missing' => (int) ($attendance['missing'] ?? 0),
                'open_fees' => $cash['counts']['open'],
                'available_carpool_seats' => $rides['available_seats_total'],
            ],
            'attendance' => [
                'next_event' => $nextEvent ? $this->eventPayload($nextEvent) : null,
                'summary' => $attendance,
                'viewer_response' => $viewerResponse,
                'missing_responses' => $missingResponses,
                'response_options' => ['yes', 'no', 'maybe', 'late'],
                'reminder_contract' => [
                    'enabled' => $canManageOperations,
                    'channels' => ['push', 'in_app'],
                    'audience' => 'missing_responses',
                    'requires_confirmation' => true,
                ],
            ],
            'carpools' => $rides,
            'tasks' => [
                'items' => $tasks,
                'templates' => [
                    ['key' => 'bring_material', 'assignee_role' => 'player_or_parent'],
                    ['key' => 'confirm_attendance', 'assignee_role' => 'player_or_parent'],
                    ['key' => 'collect_fee', 'assignee_role' => 'treasurer_or_coach'],
                    ['key' => 'offer_ride', 'assignee_role' => 'parent_or_driver'],
                ],
            ],
            'cash_box' => $cash,
            'guardian_mode' => $guardian,
            'materials' => $materials,
            'season_planning' => $season,
            'mobile_contract' => [
                'bottom_tab_recommended' => 'team',
                'offline_read_model' => true,
                'write_endpoints' => [
                    'attendance' => '/api/v1/events/{event}/participation',
                    'carpools' => '/api/v1/rides',
                    'fees' => '/api/v1/teams/{team}/penalty-fees',
                ],
            ],
        ];
    }

    private function attendance(Event $event, array $teamUserIds, Collection $participantRecords): array
    {
        $teamSize = count($teamUserIds);
        $totals = $participantRecords
            ->countBy('status')
            ->all();
        $responded = array_sum($totals);
        $yesLate = (int) ($totals['yes'] ?? 0) + (int) ($totals['late'] ?? 0);

        return [
            'team_size' => $teamSize,
            'responded' => $responded,
            'missing' => max(0, $teamSize - $responded),
            'yes' => (int) ($totals['yes'] ?? 0),
            'late' => (int) ($totals['late'] ?? 0),
            'maybe' => (int) ($totals['maybe'] ?? 0),
            'no' => (int) ($totals['no'] ?? 0),
            'attendance_rate' => $teamSize === 0 ? 0 : round(($yesLate / $teamSize) * 100, 2),
            'response_rate' => $teamSize === 0 ? 0 : round(($responded / $teamSize) * 100, 2),
            'deadline_at' => $event->participant_response_deadline_at?->toIso8601String(),
            'deadline_state' => $this->deadlineState($event),
        ];
    }

    private function missingResponses(Collection $members, Collection $participantRecords): array
    {
        $respondedIds = $participantRecords
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $members
            ->reject(fn (User $member) => in_array((int) $member->id, $respondedIds, true))
            ->take(20)
            ->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
            ])
            ->values()
            ->all();
    }

    private function rides(Team $team, ?Event $nextEvent, bool $canView): array
    {
        if (! $canView) {
            return [
                'event_id' => $nextEvent?->id,
                'count' => 0,
                'available_seats_total' => 0,
                'items' => [],
                'needs_more_seats' => false,
            ];
        }

        $query = Ride::query()
            ->where('team_id', $team->id)
            ->with(['driver:id,name'])
            ->withCount([
                'users as accepted_count' => fn ($query) => $query->where('ride_users.status', Ride::MEMBER_STATUS_ACCEPTED),
                'users as requested_count' => fn ($query) => $query->where('ride_users.status', Ride::MEMBER_STATUS_REQUESTED),
            ]);

        if ($nextEvent) {
            $query->where('event_id', $nextEvent->id);
        } else {
            $query->where('departure_time', '>=', now()->startOfDay());
        }

        $rides = $query
            ->orderBy('departure_time')
            ->limit(10)
            ->get()
            ->map(fn (Ride $ride) => [
                'id' => $ride->id,
                'event_id' => $ride->event_id,
                'driver' => $ride->driver ? ['id' => $ride->driver->id, 'name' => $ride->driver->name] : null,
                'from' => $ride->from,
                'to' => $ride->to,
                'departure_time' => $ride->departure_time?->toIso8601String(),
                'seats' => (int) $ride->seats,
                'accepted_count' => (int) $ride->accepted_count,
                'requested_count' => (int) $ride->requested_count,
                'available_seats' => max(0, (int) $ride->seats - (int) $ride->accepted_count),
            ])
            ->values();

        return [
            'event_id' => $nextEvent?->id,
            'count' => $rides->count(),
            'available_seats_total' => $rides->sum('available_seats'),
            'items' => $rides->all(),
            'needs_more_seats' => $nextEvent !== null && $rides->sum('available_seats') === 0,
        ];
    }

    private function cashBox(Team $team, User $viewer, bool $canManage): array
    {
        $scopedFees = TeamFee::query()
            ->where('team_id', $team->id)
            ->when(! $canManage, fn ($query) => $query->where('user_id', $viewer->id));
        $aggregate = (clone $scopedFees)
            ->selectRaw("SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count")
            ->selectRaw("SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'open' THEN amount ELSE 0 END), 0) as open_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) as paid_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'cancelled' THEN amount ELSE 0 END), 0) as cancelled_total")
            ->first();

        $openItems = (clone $scopedFees)
            ->where('status', 'open')
            ->with('member:id,name')
            ->orderBy('due_date')
            ->limit(8)
            ->get()
            ->map(fn (TeamFee $fee) => [
                'id' => $fee->id,
                'member_id' => $fee->user_id,
                'member_name' => $fee->member?->name,
                'category' => $fee->category,
                'amount' => (float) $fee->amount,
                'currency' => $fee->currency,
                'due_date' => $fee->due_date?->toDateString(),
            ])
            ->all();

        return [
            'scope' => $canManage ? 'team' : 'self',
            'can_manage' => $canManage,
            'counts' => [
                'open' => (int) ($aggregate?->open_count ?? 0),
                'paid' => (int) ($aggregate?->paid_count ?? 0),
                'cancelled' => (int) ($aggregate?->cancelled_count ?? 0),
            ],
            'totals' => [
                'open' => (float) ($aggregate?->open_total ?? 0),
                'paid' => (float) ($aggregate?->paid_total ?? 0),
                'cancelled' => (float) ($aggregate?->cancelled_total ?? 0),
            ],
            'open_items' => $openItems,
            'reminder_contract' => [
                'enabled' => $canManage,
                'audience' => 'members_with_open_fees',
                'channels' => ['push', 'in_app'],
                'requires_confirmation' => true,
            ],
        ];
    }

    private function guardianMode(Collection $members, bool $canManage): array
    {
        if (! $canManage) {
            return [
                'visible' => false,
                'recommended' => false,
                'minor_members_count' => 0,
                'linked_guardians_count' => 0,
                'channels' => [],
                'approval_required_for' => [],
            ];
        }

        $minorCutoff = now()->subYears(18);
        $minors = $members->filter(fn (User $member) => $member->birth_date && $member->birth_date->greaterThan($minorCutoff));

        return [
            'visible' => true,
            'recommended' => $minors->isNotEmpty(),
            'minor_members_count' => $minors->count(),
            'linked_guardians_count' => $minors->filter(fn (User $user) => $user->guardian_user_id || $user->guardian_email)->count(),
            'channels' => ['attendance', 'transport', 'tasks', 'fees'],
            'approval_required_for' => ['late_carpool_join', 'fee_reminder', 'medical_note'],
        ];
    }

    private function tasks(
        ?Event $nextEvent,
        ?array $attendance,
        array $missingResponses,
        array $rides,
        array $cash,
        array $guardian,
        int $upcomingEvents,
        bool $canManage,
        bool $isTeamMember,
        ?string $viewerResponse,
    ): array {
        $tasks = [];

        if (! $canManage && $isTeamMember && $nextEvent && ! $viewerResponse) {
            $tasks[] = $this->task('confirm_attendance', 'high', 'player_or_parent', 1, $nextEvent->participant_response_deadline_at?->toIso8601String());
        }

        if ($canManage && ($attendance['missing'] ?? 0) > 0) {
            $tasks[] = $this->task('remind_missing_responses', 'high', 'coach', count($missingResponses), $nextEvent?->participant_response_deadline_at?->toIso8601String());
        }

        if ($canManage && ($attendance['attendance_rate'] ?? 100) < 70 && $nextEvent) {
            $tasks[] = $this->task('check_squad_availability', 'medium', 'coach', (int) ($attendance['yes'] ?? 0), null);
        }

        if ($rides['needs_more_seats']) {
            $tasks[] = $this->task('organize_carpool', 'medium', 'parent_or_driver', 0, $nextEvent?->start_time?->subHours(6)->toIso8601String());
        }

        if (($cash['counts']['open'] ?? 0) > 0) {
            $tasks[] = $this->task($canManage ? 'collect_open_fees' : 'review_own_fee', 'medium', $canManage ? 'treasurer_or_coach' : 'player_or_parent', (int) $cash['counts']['open'], null);
        }

        if ($canManage && $guardian['recommended'] && ($guardian['linked_guardians_count'] ?? 0) < ($guardian['minor_members_count'] ?? 0)) {
            $tasks[] = $this->task('complete_parent_links', 'medium', 'coach', (int) ($guardian['minor_members_count'] - $guardian['linked_guardians_count']), null);
        }

        if ($canManage && $upcomingEvents < 4) {
            $tasks[] = $this->task('extend_season_calendar', 'low', 'coach', $upcomingEvents, null);
        }

        if ($tasks === []) {
            $tasks[] = $this->task('team_routine_stable', 'low', $canManage ? 'coach' : 'team', 0, null);
        }

        return array_slice($tasks, 0, 10);
    }

    private function materials(?Event $event): array
    {
        if (! $event) {
            return [
                'status' => 'idle',
                'event_id' => null,
                'suggested_lists' => [],
            ];
        }

        $items = [
            ['key' => 'first_aid', 'required' => true, 'assignee_role' => 'coach'],
            ['key' => 'water', 'required' => true, 'assignee_role' => 'team'],
            ['key' => 'balls', 'required' => in_array($event->type, ['training', 'match'], true), 'assignee_role' => 'coach'],
        ];

        if ($event->type === 'match') {
            $items[] = ['key' => 'jerseys', 'required' => true, 'assignee_role' => 'player_or_parent'];
        }

        return [
            'status' => 'check_recommended',
            'event_id' => $event->id,
            'suggested_lists' => $items,
            'task_template' => 'bring_material',
        ];
    }

    private function seasonPlanning(Collection $events, Collection $upcoming): array
    {
        $byType = $events->countBy('type')->all();
        $matchCount = (int) ($byType['match'] ?? 0);
        $trainingCount = (int) ($byType['training'] ?? 0);

        return [
            'events_total' => $events->count(),
            'upcoming_events' => $upcoming->count(),
            'training_count' => $trainingCount,
            'match_count' => $matchCount,
            'planning_state' => $upcoming->count() >= 6 ? 'planned' : ($upcoming->count() >= 3 ? 'watch' : 'needs_more_events'),
            'recommended_next_events' => $this->recommendedNextEvents($trainingCount, $matchCount, $upcoming->count()),
        ];
    }

    private function recommendedNextEvents(int $trainingCount, int $matchCount, int $upcomingCount): array
    {
        $events = [];

        if ($upcomingCount < 4) {
            $events[] = ['key' => 'weekly_training', 'type' => 'training'];
        }

        if ($matchCount === 0) {
            $events[] = ['key' => 'season_matchday', 'type' => 'match'];
        }

        if ($trainingCount < 2) {
            $events[] = ['key' => 'extra_training_block', 'type' => 'training'];
        }

        return $events;
    }

    private function primaryAction(array $tasks, Team $team, ?Event $nextEvent): array
    {
        $task = $tasks[0] ?? $this->task('team_routine_stable', 'low', 'team', 0, null);
        $eventHref = $nextEvent ? route('auth.events.show', $nextEvent) : route('auth.events.index');
        $routes = [
            'confirm_attendance' => [$eventHref, 'las la-calendar-check', '/api/v1/events/{event}/participation'],
            'remind_missing_responses' => [$eventHref, 'las la-bell', '/api/v1/events/{event}'],
            'check_squad_availability' => [$eventHref, 'las la-users', '/api/v1/events/{event}'],
            'organize_carpool' => [route('auth.rides.index'), 'las la-car-side', '/api/v1/rides'],
            'collect_open_fees' => [route('auth.teams.show', $team).'#team-cash-box', 'las la-coins', '/api/v1/teams/{team}/penalties'],
            'review_own_fee' => [route('auth.teams.show', $team).'#team-cash-box', 'las la-receipt', '/api/v1/teams/{team}/penalties'],
            'complete_parent_links' => [route('auth.teams.show', $team).'#team-members', 'las la-user-shield', '/api/v1/teams/{team}'],
            'extend_season_calendar' => [route('auth.events.index'), 'las la-calendar-plus', '/api/v1/events'],
            'team_routine_stable' => [route('auth.teams.show', $team), 'las la-check-circle', '/api/v1/teams/{team}/daily-life'],
        ];
        [$href, $icon, $apiTarget] = $routes[$task['key']] ?? $routes['team_routine_stable'];

        return [
            'key' => $task['key'],
            'count' => $task['count'],
            'label' => __("team_home.actions.{$task['key']}.label"),
            'reason' => __("team_home.actions.{$task['key']}.reason", ['count' => $task['count']]),
            'href' => $href,
            'icon' => $icon,
            'api_target' => $apiTarget,
            'deep_link' => "airmius://teams/{$team->id}/today",
        ];
    }

    private function eventPayload(Event $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'type' => $event->type,
            'start_time' => $event->start_time?->toIso8601String(),
            'location_name' => $event->location_name,
            'response_required' => (bool) $event->participant_response_required,
        ];
    }

    private function task(string $key, string $priority, string $assigneeRole, int $count, ?string $dueAt): array
    {
        return [
            'key' => $key,
            'priority' => $priority,
            'assignee_role' => $assigneeRole,
            'count' => $count,
            'due_at' => $dueAt,
            'status' => 'open',
        ];
    }

    private function deadlineState(Event $event): string
    {
        $deadline = $event->participant_response_deadline_at;

        if (! $deadline) {
            return 'not_set';
        }

        if ($deadline->isPast()) {
            return 'expired';
        }

        if ($deadline->isToday()) {
            return 'today';
        }

        return 'upcoming';
    }
}
