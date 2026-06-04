<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Team;
use App\Models\TeamFee;
use App\Models\TeamOnboarding;
use App\Models\User;
use App\Support\Api\V1\ApiPagination;
use App\Support\TeamRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeamCompetitivenessController extends Controller
{
    public function onboarding(Request $request, Team $team)
    {
        $onboarding = $team->onboarding()->firstOrCreate([
            'team_id' => $team->id,
        ], [
            'team_structure_ready' => false,
            'roles_defined' => false,
            'calendar_setup_done' => false,
            'communication_setup_done' => false,
            'completed_steps' => [],
        ]);

        return response()->json([
            'data' => [
                'team_id' => $team->id,
                'step' => [
                    'team_structure_ready' => (bool) $onboarding->team_structure_ready,
                    'roles_defined' => (bool) $onboarding->roles_defined,
                    'calendar_setup_done' => (bool) $onboarding->calendar_setup_done,
                    'communication_setup_done' => (bool) $onboarding->communication_setup_done,
                    'completed_steps' => $onboarding->completed_steps ?: [],
                    'next_step' => $onboarding->next_step,
                ],
                'progress' => $this->onboardingProgress($onboarding),
            ],
        ]);
    }

    public function setOnboarding(Request $request, Team $team)
    {
        Gate::authorize('update', $team);

        $data = $request->validate([
            'team_structure_ready' => ['required', 'boolean'],
            'roles_defined' => ['required', 'boolean'],
            'calendar_setup_done' => ['required', 'boolean'],
            'communication_setup_done' => ['required', 'boolean'],
            'completed_steps' => ['nullable', 'array'],
            'completed_steps.*' => ['nullable', 'string', 'max:120'],
            'next_step' => ['nullable', 'string', 'max:120'],
        ]);

        $onboarding = $team->onboarding()->updateOrCreate(
            ['team_id' => $team->id],
            $data
        );

        return response()->json([
            'data' => [
                'team_id' => $team->id,
                'progress' => $this->onboardingProgress($onboarding),
                'step' => [
                    'team_structure_ready' => (bool) $onboarding->team_structure_ready,
                    'roles_defined' => (bool) $onboarding->roles_defined,
                    'calendar_setup_done' => (bool) $onboarding->calendar_setup_done,
                    'communication_setup_done' => (bool) $onboarding->communication_setup_done,
                    'completed_steps' => $onboarding->completed_steps ?: [],
                    'next_step' => $onboarding->next_step,
                ],
            ],
        ]);
    }

    public function roleProfiles(Request $request, Team $team)
    {
        Gate::authorize('view', $team);

        $roleDistribution = $team->users()
            ->select('team_user.role')
            ->selectRaw('count(*) as count')
            ->groupBy('team_user.role')
            ->pluck('count', 'role')
            ->all();

        $members = $team->users()
            ->select(['users.id', 'users.name', 'users.email'])
            ->get()
            ->map(fn ($member) => [
                'user' => new UserResource($member),
                'role' => $member->pivot?->role,
                'role_label' => TeamRoles::definition((string) $member->pivot?->role)['label'] ?? $member->pivot?->role,
            ]);

        return response()->json([
            'data' => [
                'team_id' => $team->id,
                'role_distribution' => $roleDistribution,
                'role_profiles' => array_map(
                    fn (string $role) => ['key' => $role, ...TeamRoles::definition($role)],
                    Team::ROLES
                ),
                'members' => $members,
            ],
        ]);
    }

    public function setMemberRole(Request $request, Team $team, User $member)
    {
        Gate::authorize('update', $team);

        $data = $request->validate([
            'role' => ['required', 'string', Rule::in(Team::ROLES)],
        ]);

        $isMember = $team->users()->where('users.id', $member->id)->exists();
        if (! $isMember) {
            abort(404, 'Mitglied nicht gefunden.');
        }

        $team->users()->updateExistingPivot($member->id, [
            'role' => $data['role'],
        ]);

        return response()->json([
            'message' => 'Teamrolle aktualisiert.',
            'data' => [
                'team_id' => $team->id,
                'member_id' => $member->id,
                'role' => $data['role'],
                'role_label' => TeamRoles::definition($data['role'])['label'] ?? $data['role'],
            ],
        ]);
    }

    public function fees(Request $request, Team $team)
    {
        Gate::authorize('view', $team);

        $feesQuery = TeamFee::query()
            ->where('team_id', $team->id)
            ->with(['member:id,name,email', 'collector:id,name,email'])
            ->orderByDesc('id');

        $fees = $feesQuery->paginate(ApiPagination::perPage($request));

        return response()->json([
            'data' => $fees->through(function (TeamFee $fee) {
                return [
                    'id' => $fee->id,
                    'team_id' => $fee->team_id,
                    'member' => new UserResource($fee->member),
                    'collector' => $fee->collector ? new UserResource($fee->collector) : null,
                    'category' => $fee->category,
                    'amount' => (float) $fee->amount,
                    'currency' => $fee->currency,
                    'status' => $fee->status,
                    'due_date' => $fee->due_date?->toDateString(),
                    'paid_at' => $fee->paid_at?->toDateString(),
                    'note' => $fee->note,
                    'created_at' => $fee->created_at,
                ];
            }),
            'pagination' => [
                'total' => $fees->total(),
                'per_page' => $fees->perPage(),
                'page' => $fees->currentPage(),
                'last_page' => $fees->lastPage(),
            ],
            'summary' => $this->teamFeeSummary($team),
        ]);
    }

    public function storeFee(Request $request, Team $team)
    {
        Gate::authorize('update', $team);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'category' => ['required', 'string', 'max:40'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'currency' => ['required', 'string', Rule::in(['EUR', 'USD'])],
            'status' => ['required', Rule::in(['open', 'paid', 'cancelled'])],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $fee = TeamFee::create([
            ...$data,
            'team_id' => $team->id,
            'collector_id' => $request->user()->id,
            'amount' => (float) $data['amount'],
            'due_date' => $data['due_date'] ?? null,
            'paid_at' => $data['paid_at'] ?? null,
        ]);

        return response()->json([
            'message' => 'Teamgebühr erfasst.',
            'data' => $this->presentFee($fee),
        ]);
    }

    public function updateFee(Request $request, Team $team, TeamFee $fee)
    {
        Gate::authorize('update', $team);
        abort_unless((int) $fee->team_id === (int) $team->id, 404);

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:1000000'],
            'currency' => ['nullable', 'string', Rule::in(['EUR', 'USD'])],
            'status' => ['nullable', 'string', Rule::in(['open', 'paid', 'cancelled'])],
            'due_date' => ['nullable', 'date'],
            'paid_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:40'],
        ]);

        if (array_filter($data) === []) {
            throw ValidationException::withMessages([
                'fee' => 'Es wurden keine Änderungen erkannt.',
            ]);
        }

        $fee->update($data);

        return response()->json([
            'message' => 'Teamgebühr aktualisiert.',
            'data' => $this->presentFee($fee->refresh()),
        ]);
    }

    public function destroyFee(Team $team, TeamFee $fee)
    {
        Gate::authorize('update', $team);
        abort_unless((int) $fee->team_id === (int) $team->id, 404);

        $fee->delete();

        return response()->json([
            'message' => 'Teamgebühr entfernt.',
        ]);
    }

    public function insights(Team $team)
    {
        Gate::authorize('view', $team);

        $teamUserIds = $team->users()->pluck('users.id')->all();
        $roleDistribution = $team->users()
            ->select('team_user.role')
            ->selectRaw('count(*) as count')
            ->groupBy('team_user.role')
            ->get()
            ->pluck('count', 'role')
            ->toArray();

        $memberCount = count($teamUserIds);
        $events = Event::query()
            ->where('team_id', $team->id)
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('start_time')
            ->get();

        $upcomingEvents = $events->filter(fn (Event $event) => optional($event->start_time)->isFuture())->count();
        $recentEvents = $events->take(8);
        $nextEvent = $events
            ->filter(fn (Event $event) => optional($event->start_time)->isFuture())
            ->sortBy('start_time')
            ->first();
        $teamParticipation = $recentEvents->map(function (Event $event) use ($teamUserIds) {
            $responded = EventParticipant::query()
                ->where('event_id', $event->id)
                ->whereIn('user_id', $teamUserIds)
                ->count();

            $yesLate = EventParticipant::query()
                ->where('event_id', $event->id)
                ->whereIn('status', ['yes', 'late'])
                ->whereIn('user_id', $teamUserIds)
                ->count();

            $teamSize = count($teamUserIds);

            return [
                'event_id' => $event->id,
                'start_time' => optional($event->start_time)->toDateString(),
                'team_size' => $teamSize,
                'responded' => $responded,
                'yes_late' => $yesLate,
                'response_rate' => $teamSize === 0 ? 0 : round(($responded / $teamSize) * 100, 2),
                'attendance_rate' => $teamSize === 0 ? 0 : round(($yesLate / $teamSize) * 100, 2),
            ];
        });

        $last30Days = $events->filter(function (Event $event) {
            return optional($event->start_time)?->isBetween(now()->subDays(30), now()->addDay());
        });
        $last30DaysResponses = $last30Days->sum(function (Event $event) use ($teamUserIds) {
            return EventParticipant::query()
                ->where('event_id', $event->id)
                ->whereIn('user_id', $teamUserIds)
                ->count();
        });
        $feeSummary = $this->teamFeeSummary($team);
        $teamMembers = $team->users()->get(['users.id', 'users.birth_date']);
        $nextEventSummary = $nextEvent ? $this->eventResponseSummary($nextEvent, $teamUserIds) : null;
        $missingResponses = $nextEvent ? $this->missingResponsesForEvent($nextEvent, $teamUserIds) : [];
        $memberReliability = $this->memberReliability($team, $events->take(12), $teamUserIds);
        $teamActions = $this->teamActions($nextEventSummary, $missingResponses, $feeSummary, $roleDistribution, $memberCount, $upcomingEvents);
        $attendancePlaybook = $this->attendancePlaybook($nextEvent, $nextEventSummary, $missingResponses, $memberReliability, $teamActions);

        return response()->json([
            'data' => [
                'team_id' => $team->id,
                'members' => $memberCount,
                'roles' => $roleDistribution,
                'events' => [
                    'total' => $events->count(),
                    'upcoming' => $upcomingEvents,
                    'next' => $nextEvent ? [
                        'id' => $nextEvent->id,
                        'title' => $nextEvent->title,
                        'type' => $nextEvent->type,
                        'start_time' => $nextEvent->start_time?->toDateTimeString(),
                        'response_required' => (bool) $nextEvent->participant_response_required,
                        'response_deadline_at' => $nextEvent->participant_response_deadline_at?->toDateTimeString(),
                        'participation' => $nextEventSummary,
                    ] : null,
                    'recent_participation_trend' => $teamParticipation->values(),
                ],
                'participation' => [
                    'response_rate_30d' => $last30DaysResponses === 0 ? 0 : round(($last30DaysResponses / max(1, $memberCount * $last30Days->count())) * 100, 2),
                    'age_groups' => $this->ageGroupsFromMembers($teamMembers),
                    'missing_responses' => $missingResponses,
                    'reliability' => $memberReliability,
                    'playbook' => $attendancePlaybook,
                ],
                'fees' => $feeSummary,
                'team_actions' => $teamActions,
                'team_organizer' => $this->teamOrganizer($team, $nextEvent, $nextEventSummary, $missingResponses, $feeSummary, $teamActions, $upcomingEvents),
            ],
        ]);
    }

    private function eventResponseSummary(Event $event, array $teamUserIds): array
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
            'yes_late' => $yesLate,
            'response_rate' => $teamSize === 0 ? 0 : round(($responded / $teamSize) * 100, 2),
            'attendance_rate' => $teamSize === 0 ? 0 : round(($yesLate / $teamSize) * 100, 2),
        ];
    }

    private function missingResponsesForEvent(Event $event, array $teamUserIds): array
    {
        if ($teamUserIds === []) {
            return [];
        }

        $respondedIds = EventParticipant::query()
            ->where('event_id', $event->id)
            ->whereIn('user_id', $teamUserIds)
            ->pluck('user_id')
            ->all();

        return User::query()
            ->whereIn('id', array_values(array_diff($teamUserIds, $respondedIds)))
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
            ])
            ->values()
            ->all();
    }

    private function memberReliability(Team $team, $events, array $teamUserIds): array
    {
        if ($teamUserIds === [] || $events->isEmpty()) {
            return [];
        }

        $eventIds = $events->pluck('id')->all();
        $participants = EventParticipant::query()
            ->whereIn('event_id', $eventIds)
            ->whereIn('user_id', $teamUserIds)
            ->get(['event_id', 'user_id', 'status'])
            ->groupBy('user_id');
        $eventCount = max(1, count($eventIds));

        return $team->users()
            ->whereIn('users.id', $teamUserIds)
            ->orderBy('name')
            ->get(['users.id', 'users.name'])
            ->map(function (User $member) use ($participants, $eventCount) {
                $records = $participants->get($member->id, collect());
                $yesLate = $records->whereIn('status', ['yes', 'late'])->count();
                $responded = $records->count();

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'responded' => $responded,
                    'yes_late' => $yesLate,
                    'missing' => max(0, $eventCount - $responded),
                    'response_rate' => round(($responded / $eventCount) * 100, 2),
                    'attendance_rate' => round(($yesLate / $eventCount) * 100, 2),
                ];
            })
            ->sortBy([
                ['missing', 'desc'],
                ['attendance_rate', 'asc'],
                ['name', 'asc'],
            ])
            ->take(12)
            ->values()
            ->all();
    }

    private function attendancePlaybook(?Event $nextEvent, ?array $nextEventSummary, array $missingResponses, array $memberReliability, array $teamActions): array
    {
        $deadline = $nextEvent?->participant_response_deadline_at;
        $deadlineState = 'not_set';

        if ($deadline?->isPast()) {
            $deadlineState = 'expired';
        } elseif ($deadline?->isToday()) {
            $deadlineState = 'today';
        } elseif ($deadline) {
            $deadlineState = 'upcoming';
        }

        $missingCount = (int) ($nextEventSummary['missing'] ?? 0);
        $attendanceRate = (float) ($nextEventSummary['attendance_rate'] ?? 0);
        $responseRate = (float) ($nextEventSummary['response_rate'] ?? 0);
        $risk = match (true) {
            $missingCount > 0 && $deadlineState === 'expired' => 'critical',
            $attendanceRate > 0 && $attendanceRate < 60 => 'high',
            $responseRate < 70 => 'watch',
            default => 'stable',
        };

        $lowReliability = collect($memberReliability)
            ->filter(fn (array $member) => (float) ($member['response_rate'] ?? 100) < 70 || (int) ($member['missing'] ?? 0) >= 2)
            ->take(5)
            ->values()
            ->all();

        $reminders = [];

        if ($missingCount > 0) {
            $reminders[] = [
                'key' => 'push_missing',
                'channel' => 'push',
                'audience' => 'missing_responses',
                'count' => $missingCount,
                'recommended_at' => now()->toDateTimeString(),
                'message_key' => 'teams.reminders.missing_response',
            ];
        }

        if ($lowReliability !== []) {
            $reminders[] = [
                'key' => 'coach_followup_low_reliability',
                'channel' => 'coach_task',
                'audience' => 'low_reliability_members',
                'count' => count($lowReliability),
                'recommended_at' => now()->toDateTimeString(),
                'message_key' => 'teams.reminders.low_reliability',
            ];
        }

        return [
            'version' => '2026-06-03',
            'risk' => $risk,
            'deadline_state' => $deadlineState,
            'next_event_id' => $nextEvent?->id,
            'response_rate' => $responseRate,
            'attendance_rate' => $attendanceRate,
            'missing_count' => $missingCount,
            'low_reliability_members' => $lowReliability,
            'reminders' => $reminders,
            'coach_briefing' => $this->coachBriefing($risk, $missingCount, $attendanceRate, $teamActions),
        ];
    }

    private function teamOrganizer(Team $team, ?Event $nextEvent, ?array $nextEventSummary, array $missingResponses, array $feeSummary, array $teamActions, int $upcomingEvents): array
    {
        $tasks = collect($teamActions)
            ->map(fn (array $action) => [
                'key' => $action['key'],
                'title' => $this->organizerTaskTitle($action['key']),
                'priority' => $action['priority'] ?? 'normal',
                'count' => $action['count'] ?? 0,
                'assignee_role' => 'coach',
                'due_at' => $nextEvent?->participant_response_deadline_at?->toIso8601String(),
            ])
            ->values();

        if (($feeSummary['counts']['open'] ?? 0) > 0) {
            $tasks->push([
                'key' => 'collect_open_fees',
                'title' => 'Offene Teambeitraege klaeren',
                'priority' => 'normal',
                'count' => (int) ($feeSummary['counts']['open'] ?? 0),
                'assignee_role' => 'treasurer_or_coach',
                'due_at' => null,
            ]);
        }

        return [
            'version' => '2026-06-03',
            'team_id' => $team->id,
            'next_event_id' => $nextEvent?->id,
            'tasks' => $tasks->take(8)->values()->all(),
            'materials' => [
                'status' => $nextEvent ? 'check_recommended' : 'idle',
                'suggested_lists' => $this->materialListsForEvent($nextEvent),
            ],
            'polls' => [
                'recommended' => count($missingResponses) > 0 || (($nextEventSummary['maybe'] ?? 0) > 0),
                'templates' => [
                    ['key' => 'attendance_confirmation', 'label' => 'Teilnahme final bestätigen'],
                    ['key' => 'transport_options', 'label' => 'Fahrgemeinschaften abstimmen'],
                    ['key' => 'equipment_needed', 'label' => 'Materialbedarf klaeren'],
                ],
            ],
            'season_plan' => [
                'upcoming_events' => $upcomingEvents,
                'next_focus' => $nextEvent?->type ?: 'training',
                'planning_state' => $upcomingEvents >= 4 ? 'planned' : 'needs_more_events',
            ],
            'guardian_mode' => [
                'recommended' => $this->guardianModeRecommended($team),
                'channels' => ['attendance', 'transport', 'fees'],
            ],
        ];
    }

    private function organizerTaskTitle(string $key): string
    {
        return [
            'remind_missing_responses' => 'Fehlende Rückmeldungen erinnern',
            'check_availability' => 'Kader und Verfügbarkeit prüfen',
            'review_open_fees' => 'Offene Beiträge prüfen',
            'complete_roles' => 'Teamrollen vervollstaendigen',
        ][$key] ?? str($key)->replace('_', ' ')->headline()->toString();
    }

    private function materialListsForEvent(?Event $event): array
    {
        if (! $event) {
            return [];
        }

        $base = [
            ['key' => 'balls', 'label' => 'Baelle', 'required' => in_array($event->type, ['training', 'match'], true)],
            ['key' => 'first_aid', 'label' => 'Erste Hilfe', 'required' => true],
            ['key' => 'water', 'label' => 'Wasser', 'required' => true],
        ];

        if ($event->type === 'match') {
            $base[] = ['key' => 'jerseys', 'label' => 'Trikots', 'required' => true];
        }

        return $base;
    }

    private function guardianModeRecommended(Team $team): bool
    {
        return $team->users()
            ->whereNotNull('birth_date')
            ->where('birth_date', '>', now()->subYears(18)->toDateString())
            ->exists();
    }

    private function coachBriefing(string $risk, int $missingCount, float $attendanceRate, array $teamActions): array
    {
        $headline = match ($risk) {
            'critical' => 'Teilnahmefrist abgelaufen - Antworten fehlen.',
            'high' => 'Zu wenige Zusagen für den nächsten Termin.',
            'watch' => 'Antwortquote beobachten und frueh erinnern.',
            default => 'Team-Alltag wirkt stabil.',
        };

        return [
            'headline' => $headline,
            'summary' => $missingCount > 0
                ? $missingCount.' Mitglieder ohne Antwort, Zusagequote '.$attendanceRate.'%.'
                : 'Keine offenen Teilnahmeantworten, Zusagequote '.$attendanceRate.'%.',
            'next_actions' => collect($teamActions)
                ->take(4)
                ->map(fn (array $action) => [
                    'key' => $action['key'],
                    'priority' => $action['priority'],
                    'count' => $action['count'],
                ])
                ->values()
                ->all(),
        ];
    }

    private function teamActions(?array $nextEventSummary, array $missingResponses, array $feeSummary, array $roleDistribution, int $memberCount, int $upcomingEvents): array
    {
        $actions = [];

        if ($memberCount === 0) {
            $actions[] = ['key' => 'invite_members', 'priority' => 'high', 'count' => 0];
        }

        if (! isset($roleDistribution[TeamRoles::COACH]) && ! isset($roleDistribution['coach'])) {
            $actions[] = ['key' => 'assign_coach', 'priority' => 'high', 'count' => 0];
        }

        if ($upcomingEvents === 0) {
            $actions[] = ['key' => 'schedule_event', 'priority' => 'medium', 'count' => 0];
        }

        if ($nextEventSummary && ($nextEventSummary['missing'] ?? 0) > 0) {
            $actions[] = ['key' => 'remind_missing_responses', 'priority' => 'high', 'count' => count($missingResponses)];
        }

        if ($nextEventSummary && ($nextEventSummary['attendance_rate'] ?? 100) < 70) {
            $actions[] = ['key' => 'check_availability', 'priority' => 'medium', 'count' => (int) ($nextEventSummary['yes_late'] ?? 0)];
        }

        if (($feeSummary['counts']['open'] ?? 0) > 0) {
            $actions[] = ['key' => 'review_open_fees', 'priority' => 'medium', 'count' => (int) $feeSummary['counts']['open']];
        }

        if ($actions === []) {
            $actions[] = ['key' => 'team_routine_stable', 'priority' => 'low', 'count' => $memberCount];
        }

        return $actions;
    }

    private function onboardingProgress(TeamOnboarding $onboarding): array
    {
        $steps = [
            'team_structure_ready',
            'roles_defined',
            'calendar_setup_done',
            'communication_setup_done',
        ];

        $completed = 0;

        foreach ($steps as $step) {
            if ((bool) $onboarding->{$step}) {
                $completed++;
            }
        }

        return [
            'completed' => $completed,
            'total' => count($steps),
            'percent' => $completed === 0 ? 0 : (int) round(($completed / count($steps)) * 100),
        ];
    }

    private function ageGroupFromBirthDate(?string $birthDate): string
    {
        if (! $birthDate) {
            return 'unbekannt';
        }

        try {
            $age = \Carbon\Carbon::parse($birthDate)->age;
        } catch (\Throwable $exception) {
            return 'unbekannt';
        }

        if ($age < 8) {
            return 'u8';
        }

        if ($age < 10) {
            return 'u10';
        }

        if ($age < 12) {
            return 'u12';
        }

        if ($age < 14) {
            return 'u14';
        }

        if ($age < 16) {
            return 'u16';
        }

        if ($age < 18) {
            return 'u18';
        }

        if ($age < 30) {
            return 'u30';
        }

        return 'u30+';
    }

    private function ageGroupsFromMembers($members): array
    {
        $groups = [];

        foreach ($members as $member) {
            $bucket = $this->ageGroupFromBirthDate((string) $member->birth_date);
            $groups[$bucket] = ($groups[$bucket] ?? 0) + 1;
        }

        ksort($groups);

        return $groups;
    }

    private function teamFeeSummary(Team $team): array
    {
        $summary = TeamFee::query()
            ->select('status')
            ->selectRaw('COALESCE(SUM(amount),0) as total')
            ->where('team_id', $team->id)
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $totals = [
            'open' => (float) ($summary['open'] ?? 0),
            'paid' => (float) ($summary['paid'] ?? 0),
            'cancelled' => (float) ($summary['cancelled'] ?? 0),
        ];

        $counts = TeamFee::query()
            ->select('status')
            ->selectRaw('COUNT(*) as count')
            ->where('team_id', $team->id)
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        return [
            'counts' => [
                'open' => (int) ($counts['open'] ?? 0),
                'paid' => (int) ($counts['paid'] ?? 0),
                'cancelled' => (int) ($counts['cancelled'] ?? 0),
            ],
            'totals' => [
                'open' => $totals['open'],
                'paid' => $totals['paid'],
                'cancelled' => $totals['cancelled'],
            ],
        ];
    }

    private function presentFee(TeamFee $fee): array
    {
        return [
            'id' => $fee->id,
            'team_id' => $fee->team_id,
            'user_id' => $fee->user_id,
            'category' => $fee->category,
            'amount' => (float) $fee->amount,
            'currency' => $fee->currency,
            'status' => $fee->status,
            'note' => $fee->note,
            'due_date' => $fee->due_date?->toDateString(),
            'paid_at' => $fee->paid_at?->toDateString(),
            'created_at' => $fee->created_at?->toDateTimeString(),
            'updated_at' => $fee->updated_at?->toDateTimeString(),
        ];
    }
}

