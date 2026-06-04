<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Ride;
use App\Models\Team;
use App\Models\TeamFee;
use App\Models\User;
use Illuminate\Support\Collection;

class TeamDailyLifeService
{
    public function forTeam(Team $team, User $viewer): array
    {
        $teamUserIds = $team->users()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        $events = Event::query()
            ->where('team_id', $team->id)
            ->where('status', '!=', 'cancelled')
            ->withCount('rides')
            ->orderBy('start_time')
            ->get();
        $upcoming = $events
            ->filter(fn (Event $event) => $event->start_time?->isFuture())
            ->values();
        $nextEvent = $upcoming->first();
        $attendance = $nextEvent ? $this->attendance($nextEvent, $teamUserIds) : null;
        $missingResponses = $nextEvent ? $this->missingResponses($nextEvent, $teamUserIds) : [];
        $rides = $this->rides($team, $nextEvent);
        $cash = $this->cashBox($team);
        $guardian = $this->guardianMode($team);
        $tasks = $this->tasks($nextEvent, $attendance, $missingResponses, $rides, $cash, $guardian, $upcoming->count());
        $materials = $this->materials($nextEvent);
        $season = $this->seasonPlanning($events, $upcoming);

        return [
            'version' => '2026-06-03.spielerplus_team_life.v1',
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'sport_type' => $team->sport_type,
                'members_count' => count($teamUserIds),
                'viewer_role' => $team->users()->where('users.id', $viewer->id)->first()?->pivot?->role,
            ],
            'today' => [
                'next_event_id' => $nextEvent?->id,
                'primary_action' => $this->primaryAction($attendance, $missingResponses, $cash, $upcoming->count()),
                'open_tasks' => count($tasks),
                'attendance_missing' => count($missingResponses),
                'open_fees' => $cash['counts']['open'],
                'available_carpool_seats' => $rides['available_seats_total'],
            ],
            'attendance' => [
                'next_event' => $nextEvent ? $this->eventPayload($nextEvent) : null,
                'summary' => $attendance,
                'missing_responses' => $missingResponses,
                'response_options' => ['yes', 'no', 'maybe', 'late'],
                'reminder_contract' => [
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
                    'attendance' => '/api/v1/events/{event}/competitive/participation',
                    'carpools' => '/api/v1/events/{event}/competitive/carpools',
                    'fees' => '/api/v1/teams/{team}/competitive/fees',
                ],
            ],
        ];
    }

    private function attendance(Event $event, array $teamUserIds): array
    {
        $teamSize = count($teamUserIds);
        $totals = EventParticipant::query()
            ->where('event_id', $event->id)
            ->whereIn('user_id', $teamUserIds)
            ->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
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

    private function missingResponses(Event $event, array $teamUserIds): array
    {
        $respondedIds = EventParticipant::query()
            ->where('event_id', $event->id)
            ->whereIn('user_id', $teamUserIds)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return User::query()
            ->whereIn('id', array_values(array_diff($teamUserIds, $respondedIds)))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'guardian_user_id', 'guardian_email'])
            ->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'guardian_user_id' => $member->guardian_user_id,
                'guardian_email' => $member->guardian_email,
            ])
            ->all();
    }

    private function rides(Team $team, ?Event $nextEvent): array
    {
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

    private function cashBox(Team $team): array
    {
        $counts = TeamFee::query()
            ->where('team_id', $team->id)
            ->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');
        $totals = TeamFee::query()
            ->where('team_id', $team->id)
            ->select('status')
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $openItems = TeamFee::query()
            ->where('team_id', $team->id)
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
            'counts' => [
                'open' => (int) ($counts['open'] ?? 0),
                'paid' => (int) ($counts['paid'] ?? 0),
                'cancelled' => (int) ($counts['cancelled'] ?? 0),
            ],
            'totals' => [
                'open' => (float) ($totals['open'] ?? 0),
                'paid' => (float) ($totals['paid'] ?? 0),
                'cancelled' => (float) ($totals['cancelled'] ?? 0),
            ],
            'open_items' => $openItems,
            'reminder_contract' => [
                'audience' => 'members_with_open_fees',
                'channels' => ['push', 'in_app'],
                'requires_confirmation' => true,
            ],
        ];
    }

    private function guardianMode(Team $team): array
    {
        $minors = $team->users()
            ->whereNotNull('birth_date')
            ->where('birth_date', '>', now()->subYears(18)->toDateString())
            ->get(['users.id', 'users.name', 'users.guardian_user_id', 'users.guardian_email']);

        return [
            'recommended' => $minors->isNotEmpty(),
            'minor_members_count' => $minors->count(),
            'linked_guardians_count' => $minors->filter(fn (User $user) => $user->guardian_user_id || $user->guardian_email)->count(),
            'channels' => ['attendance', 'transport', 'tasks', 'fees'],
            'approval_required_for' => ['late_carpool_join', 'fee_reminder', 'medical_note'],
        ];
    }

    private function tasks(?Event $nextEvent, ?array $attendance, array $missingResponses, array $rides, array $cash, array $guardian, int $upcomingEvents): array
    {
        $tasks = [];

        if (($attendance['missing'] ?? 0) > 0) {
            $tasks[] = $this->task('remind_missing_responses', 'high', 'coach', count($missingResponses), $nextEvent?->participant_response_deadline_at?->toIso8601String());
        }

        if (($attendance['attendance_rate'] ?? 100) < 70 && $nextEvent) {
            $tasks[] = $this->task('check_squad_availability', 'medium', 'coach', (int) ($attendance['yes'] ?? 0), null);
        }

        if ($rides['needs_more_seats']) {
            $tasks[] = $this->task('organize_carpool', 'medium', 'parent_or_driver', 0, $nextEvent?->start_time?->subHours(6)->toIso8601String());
        }

        if (($cash['counts']['open'] ?? 0) > 0) {
            $tasks[] = $this->task('collect_open_fees', 'medium', 'treasurer_or_coach', (int) $cash['counts']['open'], null);
        }

        if ($guardian['recommended'] && ($guardian['linked_guardians_count'] ?? 0) < ($guardian['minor_members_count'] ?? 0)) {
            $tasks[] = $this->task('complete_parent_links', 'medium', 'coach', (int) ($guardian['minor_members_count'] - $guardian['linked_guardians_count']), null);
        }

        if ($upcomingEvents < 4) {
            $tasks[] = $this->task('extend_season_calendar', 'low', 'coach', $upcomingEvents, null);
        }

        if ($tasks === []) {
            $tasks[] = $this->task('team_routine_stable', 'low', 'coach', 0, null);
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

    private function primaryAction(?array $attendance, array $missingResponses, array $cash, int $upcomingEvents): array
    {
        if (($attendance['missing'] ?? 0) > 0) {
            return ['key' => 'remind_missing_responses', 'count' => count($missingResponses)];
        }

        if (($cash['counts']['open'] ?? 0) > 0) {
            return ['key' => 'review_open_fees', 'count' => (int) $cash['counts']['open']];
        }

        if ($upcomingEvents < 4) {
            return ['key' => 'extend_season_calendar', 'count' => $upcomingEvents];
        }

        return ['key' => 'team_routine_stable', 'count' => 0];
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
