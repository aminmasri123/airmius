<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Competition;
use App\Models\CompetitionClass;
use App\Models\CompetitionGame;
use App\Models\CompetitionLineup;
use App\Models\CompetitionRegistration;
use App\Models\CompetitionRosterEntry;
use App\Models\CompetitionTournamentMatch;
use App\Models\CompetitionTournamentStanding;
use App\Models\CompetitionVenue;
use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use App\Services\CompetitionRegistrationWorkflowService;
use App\Services\CompetitionSportPlanningService;
use App\Services\PlanningWorkspaceService;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use App\Support\AppNotification;
use App\Support\LocalDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompetitionPlanningController extends Controller
{
    public function workspace(Request $request, PlanningWorkspaceService $workspace)
    {
        return response()->json(['data' => ['clubs' => $workspace->clubs($request->user())]]);
    }

    public function index(Request $request, Club $club, PlanningWorkspaceService $workspace)
    {
        $workspace->authorizeClub($club, $request->user());

        $competitions = Competition::query()->where('club_id', $club->id)
            ->select(['id', 'club_id', 'team_id', 'season_name', 'name', 'status', 'starts_on', 'ends_on', 'registration_deadline_at', 'metadata'])
            ->with('venues')->withCount(['rosterEntries', 'registrations'])->orderByDesc('starts_on')->get();

        return response()->json(['data' => $competitions->map(fn (Competition $competition) => $this->competitionView($competition))]);
    }

    public function show(Request $request, Competition $competition, PlanningWorkspaceService $workspace)
    {
        $workspace->authorizeClub($competition->club, $request->user());

        $edit = ClubPermissions::allows($competition->club, $request->user(), ClubPermissions::EVENTS_EDIT);
        $finance = collect([ClubPermissions::FINANCE_VIEW, ClubPermissions::FINANCE_EDIT, ClubPermissions::FINANCE_APPROVE])
            ->contains(fn ($permission) => ClubPermissions::allows($competition->club, $request->user(), $permission));
        $competition->load(['classes', 'venues', 'rosterEntries', 'registrations', 'tournamentGroups.entries', 'tournamentMatches', 'team']);
        $data = $this->competitionView($competition);
        $teamUserIds = $competition->team?->users()->pluck('users.id')->all() ?? [];
        $gamesEnabled = Schema::hasTable('competition_games');

        return response()->json(['data' => $data + [
            'can_edit' => $edit,
            'can_edit_competitions' => $edit,
            'can_pay_fees' => ClubPermissions::allows($competition->club, $request->user(), ClubPermissions::FINANCE_EDIT),
            'can_confirm' => ClubPermissions::allows($competition->club, $request->user(), ClubPermissions::FINANCE_APPROVE),
            'can_view_finance' => $finance,
            'classes' => $competition->classes,
            'venues' => $competition->venues,
            'roster_entries' => $competition->rosterEntries->map(fn ($row) => $row->only($edit
                ? ['id', 'display_name', 'bib_number', 'competition_class_id', 'competition_registration_id', 'user_id', 'role', 'status']
                : ['id', 'display_name', 'bib_number', 'competition_class_id', 'role', 'status'])),
            'registrations' => $competition->registrations->map(fn ($row) => $row->only(array_merge(
                ['id', 'competition_class_id', 'team_id', 'status'],
                $edit ? ['license_status', 'eligibility_checked_at'] : [],
                $finance ? ['start_fee_cents', 'start_fee_paid_at', 'confirmed_at'] : [],
            ))),
            'tournament_groups' => $competition->tournamentGroups,
            'tournament_matches' => $competition->tournamentMatches->map(fn ($row) => $row->only(['id', 'phase', 'round_number', 'match_number', 'home_label', 'away_label', 'home_score', 'away_score', 'status', 'scheduled_at'])),
            'sport_type' => $competition->team?->sport_type,
            'game_planning_enabled' => $gamesEnabled,
            'eligible_roster_ids' => $competition->rosterEntries->filter(function ($row) use ($competition, $teamUserIds) {
                $registration = $competition->registrations->firstWhere('id', $row->competition_registration_id);
                return $registration
                    ? (int) $registration->team_id === (int) $competition->team_id
                    : $row->user_id && in_array($row->user_id, $teamUserIds);
            })->pluck('id')->values(),
            'games' => $gamesEnabled
                ? CompetitionGame::query()->where('competition_id', $competition->id)->with('lineup.entries')->orderBy('scheduled_at')->get()
                : [],
            'lineups' => CompetitionLineup::query()->where('competition_id', $competition->id)->with('entries')->get(),
            'standings' => CompetitionTournamentStanding::query()->where('competition_id', $competition->id)->orderBy('rank')->get(),
        ]]);
    }

    public function store(Request $request, Club $club)
    {
        $this->edit($request, $club);
        $details = $this->competitionDetails($request);
        $competition = DB::transaction(function () use ($request, $club, $details) {
            $competition = Competition::create(['club_id' => $club->id] + $this->competitionData($request, $club));
            $this->saveCompetitionDetails($competition, $details);
            $this->syncCalendarEvent($competition, $request->user()->id);

            return $competition->fresh('venues');
        });
        ClubAuditLog::record($club, $request->user(), 'club.competition.created', $competition, []);

        return response()->json(['data' => $this->competitionView($competition)], 201);
    }

    public function update(Request $request, Competition $competition)
    {
        $this->edit($request, $competition->club);
        $data = $this->competitionData($request, $competition->club);
        $details = $this->competitionDetails($request);
        abort_if(Schema::hasTable('competition_games') && $competition->games()->exists() && (int) ($data['team_id'] ?? 0) !== (int) $competition->team_id, 422, 'The team cannot be changed after games have been created.');
        DB::transaction(function () use ($request, $competition, $data, $details): void {
            $competition->update($data);
            $this->saveCompetitionDetails($competition, $details);
            $this->syncCalendarEvent($competition, $request->user()->id);
        });
        ClubAuditLog::record($competition->club, $request->user(), 'club.competition.updated', $competition, []);

        return response()->json(['data' => $this->competitionView($competition->fresh('venues'))]);
    }

    public function destroy(Request $request, Competition $competition)
    {
        app(PlanningWorkspaceService::class)->authorizeClub($competition->club, $request->user());
        abort_unless(ClubPermissions::allows($competition->club, $request->user(), ClubPermissions::EVENTS_DELETE), 403);
        $calendarEventId = (int) data_get($competition->metadata, 'calendar_event_id', 0);
        abort_if($competition->registrations()->exists() || $competition->tournamentMatches()->exists() || (Schema::hasTable('competition_games') && $competition->games()->exists()) || $competition->events()->when($calendarEventId, fn ($query) => $query->whereKeyNot($calendarEventId))->exists(), 422, 'Cancel competitions with registrations, fixtures or events instead of deleting them.');
        ClubAuditLog::record($competition->club, $request->user(), 'club.competition.deleted', $competition, []);
        if ($calendarEventId) {
            Event::query()->where('competition_id', $competition->id)->whereKey($calendarEventId)->delete();
        }
        $competition->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function add(Request $request, Competition $competition, string $kind)
    {
        $this->edit($request, $competition->club);
        $model = $this->componentModel($kind);
        $rules = match ($kind) {
            'classes' => ['name' => ['required', 'string', 'max:120'], 'age_group' => ['nullable', 'string', 'max:80'], 'discipline' => ['nullable', 'string', 'max:120']],
            'venues' => [
                'name' => ['required', 'string', 'max:120'],
                'venue_type' => ['nullable', 'string', 'max:80'],
                'street' => ['nullable', 'string', 'max:160'],
                'house_number' => ['nullable', 'string', 'max:30'],
                'postal_code' => ['nullable', 'string', 'max:20'],
                'city' => ['nullable', 'string', 'max:120'],
                'country' => ['nullable', 'string', 'max:120'],
                'venue_contact_name' => ['nullable', 'string', 'max:120'],
                'venue_contact_phone' => ['nullable', 'string', 'max:60'],
                'website' => ['nullable', 'url', 'max:500'],
                'access_notes' => ['nullable', 'string', 'max:1500'],
                'venue_notes' => ['nullable', 'string', 'max:3000'],
            ],
            'roster' => [
                'display_name' => ['required', 'string', 'max:120'],
                'user_id' => ['nullable', 'integer', Rule::exists('club_user', 'user_id')->where('club_id', $competition->club_id)->where(fn ($q) => $q->whereNull('membership_status')->orWhere('membership_status', 'active'))],
                'competition_class_id' => ['nullable', 'integer', Rule::exists('competition_classes', 'id')->where('competition_id', $competition->id)],
                'bib_number' => ['nullable', 'string', 'max:80'],
            ],
        };
        $data = $request->validate($rules);
        if ($kind === 'venues') {
            $metadata = [];
            foreach (['venue_type', 'venue_contact_name', 'venue_contact_phone', 'website', 'access_notes', 'venue_notes'] as $field) {
                $value = trim((string) ($data[$field] ?? ''));
                if ($value !== '') {
                    $metadata[$field] = $value;
                }
                unset($data[$field]);
            }
            foreach (['name', 'street', 'house_number', 'postal_code', 'city', 'country'] as $field) {
                $value = trim((string) ($data[$field] ?? ''));
                $data[$field] = $value === '' ? null : $value;
            }
            $data['metadata'] = $metadata ?: null;
        }
        $entry = $model::create(['club_id' => $competition->club_id, 'competition_id' => $competition->id] + $data);
        ClubAuditLog::record($competition->club, $request->user(), 'club.competition.component_created', $entry, ['kind' => $kind]);

        return response()->json(['data' => $entry], 201);
    }

    public function remove(Request $request, Competition $competition, string $kind, int $entry)
    {
        $this->edit($request, $competition->club);
        $model = $this->componentModel($kind);
        $record = $model::query()->where('competition_id', $competition->id)->findOrFail($entry);
        // Protect registered rosters and generated planning references from silent cascade loss.
        abort_if($competition->registrations()->exists() || $competition->tournamentMatches()->exists()
            || CompetitionLineup::where('competition_id', $competition->id)->exists(), 422, 'This component is in use by a registration or sport plan.');
        $record->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function nominate(Request $request, Competition $competition, CompetitionRegistrationWorkflowService $workflow)
    {
        $this->edit($request, $competition->club);
        $data = $request->validate([
            'competition_class_id' => ['nullable', 'integer', Rule::exists('competition_classes', 'id')->where('competition_id', $competition->id)],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('club_id', $competition->club_id)],
            'start_fee_cents' => ['required', 'integer', 'min:0', 'max:100000000'],
            'roster_ids' => ['required', 'array', 'min:1', 'max:500'],
            'roster_ids.*' => ['required', 'integer', 'distinct', Rule::exists('competition_roster_entries', 'id')->where('competition_id', $competition->id)],
        ]);
        $registration = DB::transaction(function () use ($request, $competition, $workflow, $data) {
            $competition = Competition::query()->lockForUpdate()->findOrFail($competition->id);
            abort_unless(in_array($competition->status, ['planned', 'open'], true), 422, 'This competition is closed.');
            $roster = CompetitionRosterEntry::query()->where('competition_id', $competition->id)->whereIn('id', $data['roster_ids'])->lockForUpdate()->get();
            abort_if($roster->contains(fn ($row) => $row->competition_registration_id !== null), 422, 'A selected participant is already nominated.');
            abort_if(isset($data['competition_class_id']) && $roster->contains(fn ($row) => $row->competition_class_id && $row->competition_class_id !== $data['competition_class_id']), 422, 'The roster belongs to another class.');
            $registration = $workflow->nominate($competition, $request->user(), $data);
            foreach ($roster as $entry) {
                $entry->update(['competition_registration_id' => $registration->id]);
            }

            return $registration;
        });

        return response()->json(['data' => $registration], 201);
    }

    public function registrationAction(Request $request, Competition $competition, CompetitionRegistration $registration, string $action, CompetitionRegistrationWorkflowService $workflow)
    {
        app(PlanningWorkspaceService::class)->authorizeClub($competition->club, $request->user());
        abort_unless($registration->competition_id === $competition->id, 404);
        abort_unless(in_array($action, ['eligibility', 'paid', 'confirm'], true), 404);
        $registration = DB::transaction(function () use ($request, $competition, $registration, $action, $workflow) {
            $competition = Competition::query()->lockForUpdate()->findOrFail($competition->id);
            abort_unless(in_array($competition->status, ['planned', 'open'], true), 422, 'This competition is closed.');
            $current = CompetitionRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            if ($action === 'eligibility') {
                abort_if($current->status === 'confirmed' || ! $current->rosterEntries()->exists(), 422, 'This registration cannot be rechecked.');
            }

            return match ($action) {
                'eligibility' => $workflow->checkEligibility($current, $request->user()),
                'paid' => $workflow->markStartFeePaid($current, $request->user()),
                'confirm' => $workflow->confirm($current, $request->user()),
            };
        });

        return response()->json(['data' => $registration]);
    }

    public function tournament(Request $request, Competition $competition, CompetitionSportPlanningService $planning)
    {
        $this->edit($request, $competition->club);
        $data = $request->validate([
            'roster_ids' => ['required', 'array', 'min:2', 'max:128'],
            'roster_ids.*' => ['required', 'integer', 'distinct', Rule::exists('competition_roster_entries', 'id')->where('competition_id', $competition->id)],
            'group_count' => ['required', 'integer', 'min:1', 'max:32'],
        ]);

        return DB::transaction(function () use ($request, $competition, $planning, $data) {
            $competition = Competition::query()->lockForUpdate()->findOrFail($competition->id);
            abort_if($competition->tournamentMatches()->exists(), 422, 'Fixtures already exist. Edit them instead of regenerating.');
            abort_if($data['group_count'] > count($data['roster_ids']), 422, 'There must be at least one participant per group.');

            return response()->json(['data' => $planning->generateTournament($competition, $request->user(), $data['roster_ids'], ['group_count' => $data['group_count']])], 201);
        });
    }

    public function matchUpdate(Request $request, Competition $competition, CompetitionTournamentMatch $match, CompetitionSportPlanningService $planning)
    {
        $this->edit($request, $competition->club);
        abort_unless($match->competition_id === $competition->id, 404);
        $data = $request->validate([
            'home_score' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'away_score' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::in(['planned', 'scheduled', 'completed', 'cancelled'])],
            'scheduled_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        abort_if($data['status'] === 'completed' && (! isset($data['home_score']) || ! isset($data['away_score'])), 422, 'Completed fixtures require both scores.');

        return DB::transaction(function () use ($request, $competition, $match, $planning, $data) {
            Competition::query()->lockForUpdate()->findOrFail($competition->id);
            $match = CompetitionTournamentMatch::query()->lockForUpdate()->findOrFail($match->id);
            $planning->applyMatchCorrection($competition, $request->user(), $match, collect($data)->except('reason')->all(), $data['reason']);

            return response()->json(['data' => $match->fresh()]);
        });
    }

    public function lineup(Request $request, Competition $competition, CompetitionSportPlanningService $planning)
    {
        $this->edit($request, $competition->club);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'entries' => ['required', 'array', 'min:1', 'max:500'],
            'entries.*.competition_roster_entry_id' => ['required', 'integer', 'distinct', Rule::exists('competition_roster_entries', 'id')->where('competition_id', $competition->id)],
            'entries.*.role' => ['required', Rule::in(['starter', 'substitute'])],
            'entries.*.position' => ['nullable', 'string', 'max:80'],
        ]);

        return response()->json(['data' => $planning->createLineup($competition, $request->user(), ['name' => $data['name']], $data['entries'])], 201);
    }

    public function gameStore(Request $request, Competition $competition)
    {
        abort_unless(Schema::hasTable('competition_games'), 503);
        $this->edit($request, $competition->club);
        $data = $request->validate([
            'opponent' => ['required', 'string', 'max:120'],
            'scheduled_at' => ['nullable', 'date'],
        ]);
        abort_unless(in_array($competition->status, ['planned', 'open'], true), 422);
        $team = Team::query()->where('club_id', $competition->club_id)->findOrFail($competition->team_id);
        abort_unless(in_array($team->sport_type, ['fussball', 'football', 'basketball'], true), 422);
        $game = CompetitionGame::query()->create([
            'club_id' => $competition->club_id,
            'competition_id' => $competition->id,
            'team_id' => $team->id,
            'sport_type' => $team->sport_type,
            ...$data,
        ]);
        ClubAuditLog::record($competition->club, $request->user(), 'club.competition.game_created', $game, []);

        return response()->json(['data' => $game], 201);
    }

    public function gameUpdate(Request $request, Competition $competition, int $game)
    {
        abort_unless(Schema::hasTable('competition_games'), 503);
        $this->edit($request, $competition->club);
        $game = CompetitionGame::query()->where('competition_id', $competition->id)->where('club_id', $competition->club_id)->findOrFail($game);
        abort_unless(in_array($competition->status, ['planned', 'open'], true), 422);
        $data = $request->validate([
            'opponent' => ['required', 'string', 'max:120'],
            'scheduled_at' => ['nullable', 'date'],
        ]);
        $before = $game->only(['opponent', 'scheduled_at']);
        DB::transaction(function () use ($game, $data): void {
            $game->update($data);
            $game->lineup()->update(['name' => $game->opponent]);
        });
        ClubAuditLog::record($competition->club, $request->user(), 'club.competition.game_updated', $game, ['before' => $before, 'after' => $game->only(['opponent', 'scheduled_at'])]);

        return response()->json(['data' => $game]);
    }

    public function gameLineup(Request $request, Competition $competition, int $game, CompetitionSportPlanningService $planning)
    {
        abort_unless(Schema::hasTable('competition_games'), 503);
        $this->edit($request, $competition->club);
        $game = CompetitionGame::query()->where('competition_id', $competition->id)->where('club_id', $competition->club_id)->findOrFail($game);
        $data = $request->validate([
            'entries' => ['required', 'array', 'min:1', 'max:40'],
            'entries.*.competition_roster_entry_id' => ['required', 'integer', 'distinct', Rule::exists('competition_roster_entries', 'id')->where('competition_id', $competition->id)],
            'entries.*.role' => ['required', Rule::in(['starter', 'substitute'])],
            'entries.*.position' => ['nullable', 'string', 'max:80'],
            'entries.*.shirt_number' => ['required', 'string', 'regex:/^[0-9]{1,3}$/', 'distinct'],
            'entries.*.board_x' => ['nullable', 'integer', 'between:0,100'],
            'entries.*.board_y' => ['nullable', 'integer', 'between:0,100'],
        ]);

        return response()->json(['data' => $planning->saveGameLineup($competition, $game, $request->user(), $data['entries'])]);
    }

    private function edit(Request $request, Club $club): void
    {
        app(PlanningWorkspaceService::class)->authorizeClub($club, $request->user());
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::EVENTS_EDIT), 403);
    }

    private function componentModel(string $kind): string
    {
        abort_unless(in_array($kind, ['classes', 'venues', 'roster'], true), 404);

        return match ($kind) {
            'classes' => CompetitionClass::class, 'venues' => CompetitionVenue::class, 'roster' => CompetitionRosterEntry::class,
        };
    }

    private function competitionData(Request $request, Club $club): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('club_id', $club->id)],
            'season_name' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(Competition::STATUSES)],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'registration_deadline_at' => ['nullable', 'date', 'before_or_equal:ends_on'],
        ]);
    }

    private function competitionDetails(Request $request): array
    {
        return $request->validate([
            'venue_status' => ['sometimes', Rule::in(['known', 'tbd'])],
            'venue_name' => ['nullable', 'string', 'max:120'],
            'street' => ['nullable', 'string', 'max:160'],
            'house_number' => ['nullable', 'string', 'max:30'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:120', 'required_if:venue_status,known'],
            'country' => ['nullable', 'string', 'max:120'],
            'participation_fee_type' => ['sometimes', Rule::in(['unknown', 'free', 'paid'])],
            'participation_fee_cents' => ['nullable', 'integer', 'min:1', 'max:100000000', 'required_if:participation_fee_type,paid'],
            'participation_fee_basis' => ['nullable', Rule::in(['person', 'team', 'flat']), 'required_if:participation_fee_type,paid'],
            'calendar_enabled' => ['sometimes', 'boolean'],
            'calendar_scope' => ['nullable', Rule::in(['club', 'team', 'people'])],
            'calendar_user_ids' => ['nullable', 'array', 'max:500'],
            'calendar_user_ids.*' => ['integer', 'distinct', Rule::exists('club_user', 'user_id')
                ->where('club_id', $request->route('club')?->id ?? $request->route('competition')?->club_id)
                ->where(fn ($query) => $query->whereNull('membership_status')->orWhere('membership_status', 'active'))],
            'calendar_start_time' => ['nullable', 'date_format:H:i'],
            'calendar_end_time' => ['nullable', 'date_format:H:i'],
            'meeting_at' => ['nullable', 'date'],
            'meeting_point' => ['nullable', 'string', 'max:500'],
            'departure_at' => ['nullable', 'date'],
            'expected_return_at' => ['nullable', 'date', 'after_or_equal:departure_at'],
            'travel_mode' => ['nullable', Rule::in(['individual', 'club', 'carpool', 'public_transport', 'other'])],
            'responsible_user_id' => ['nullable', 'integer', Rule::exists('club_user', 'user_id')
                ->where('club_id', $request->route('club')?->id ?? $request->route('competition')?->club_id)
                ->where(fn ($query) => $query->whereNull('membership_status')->orWhere('membership_status', 'active'))],
            'responsible_name' => ['nullable', 'string', 'max:120'],
            'responsible_phone' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:5000'],
            'equipment_notes' => ['nullable', 'string', 'max:3000'],
            'document_url' => ['nullable', 'url:http,https', 'max:2048'],
            'schedule_url' => ['nullable', 'url:http,https', 'max:2048'],
            'participant_list_url' => ['nullable', 'url:http,https', 'max:2048'],
            'website_url' => ['nullable', 'url:http,https', 'max:2048'],
            'transport_notes' => ['nullable', 'string', 'max:3000'],
            'drivers_needed' => ['nullable', 'integer', 'min:0', 'max:100'],
            'seats_needed' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'accommodation_required' => ['sometimes', 'boolean'],
            'accommodation_name' => ['nullable', 'string', 'max:200'],
            'accommodation_address' => ['nullable', 'string', 'max:500'],
            'accommodation_notes' => ['nullable', 'string', 'max:3000'],
            'accommodation_check_in_at' => ['nullable', 'date'],
            'accommodation_check_out_at' => ['nullable', 'date', 'after_or_equal:accommodation_check_in_at'],
            'accommodation_cost_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'notify_participants' => ['sometimes', 'boolean'],
            'reminder_hours_before' => ['nullable', 'integer', Rule::in([2, 6, 12, 24, 48, 72, 168])],
            'cancellation_reason' => ['nullable', 'string', 'max:1000', 'required_if:status,cancelled'],
        ]);
    }

    private function saveCompetitionDetails(Competition $competition, array $details): void
    {
        if ($details === []) {
            return;
        }

        $metadata = $competition->metadata ?? [];
        foreach ([
            'venue_status', 'participation_fee_type', 'participation_fee_cents', 'participation_fee_basis',
            'calendar_enabled', 'calendar_scope', 'calendar_user_ids', 'calendar_start_time', 'calendar_end_time',
            'meeting_at', 'meeting_point', 'departure_at', 'expected_return_at', 'travel_mode',
            'responsible_user_id', 'responsible_name', 'responsible_phone', 'description', 'equipment_notes',
            'document_url', 'schedule_url', 'participant_list_url', 'website_url', 'transport_notes', 'drivers_needed', 'seats_needed',
            'accommodation_required', 'accommodation_name', 'accommodation_address', 'accommodation_notes',
            'accommodation_check_in_at', 'accommodation_check_out_at', 'accommodation_cost_cents',
            'notify_participants', 'reminder_hours_before',
            'cancellation_reason',
        ] as $key) {
            if (array_key_exists($key, $details)) {
                $metadata[$key] = $details[$key];
            }
        }
        if (array_key_exists('participation_fee_type', $details) && $details['participation_fee_type'] !== 'paid') {
            $metadata['participation_fee_cents'] = null;
            $metadata['participation_fee_basis'] = null;
        }
        $competition->update(['metadata' => $metadata ?: null]);

        if (($details['venue_status'] ?? null) !== 'known') {
            return;
        }

        $venueData = [];
        foreach (['street', 'house_number', 'postal_code', 'city', 'country'] as $key) {
            $venueData[$key] = filled($details[$key] ?? null) ? trim((string) $details[$key]) : null;
        }
        $venueData['name'] = trim((string) ($details['venue_name'] ?? ''))
            ?: ($venueData['city'] ?: __('Austragungsort'));

        $venue = $competition->venues()->oldest('id')->first();
        $venue ? $venue->update($venueData) : $competition->venues()->create(['club_id' => $competition->club_id] + $venueData);
    }

    private function competitionView(Competition $competition): array
    {
        $venue = $competition->relationLoaded('venues')
            ? $competition->venues->sortBy('id')->first()
            : $competition->venues()->oldest('id')->first();
        $metadata = $competition->metadata ?? [];
        $venueStatus = $metadata['venue_status'] ?? ($venue ? 'known' : 'tbd');
        $showVenue = $venueStatus === 'known';

        return $competition->only([
            'id', 'club_id', 'team_id', 'season_name', 'name', 'status', 'starts_on', 'ends_on',
            'registration_deadline_at', 'roster_entries_count', 'registrations_count',
        ]) + [
            'venue_status' => $venueStatus,
            'venue_name' => $showVenue ? $venue?->name : null,
            'street' => $showVenue ? $venue?->street : null,
            'house_number' => $showVenue ? $venue?->house_number : null,
            'postal_code' => $showVenue ? $venue?->postal_code : null,
            'city' => $showVenue ? $venue?->city : null,
            'country' => $showVenue ? $venue?->country : null,
            'participation_fee_type' => $metadata['participation_fee_type'] ?? 'unknown',
            'participation_fee_cents' => $metadata['participation_fee_cents'] ?? null,
            'participation_fee_basis' => $metadata['participation_fee_basis'] ?? null,
            'calendar_enabled' => (bool) ($metadata['calendar_enabled'] ?? false),
            'calendar_scope' => $metadata['calendar_scope'] ?? ($competition->team_id ? 'team' : 'club'),
            'calendar_user_ids' => $metadata['calendar_user_ids'] ?? [],
            'calendar_start_time' => $metadata['calendar_start_time'] ?? '09:00',
            'calendar_end_time' => $metadata['calendar_end_time'] ?? '18:00',
            'meeting_at' => $metadata['meeting_at'] ?? null,
            'meeting_point' => $metadata['meeting_point'] ?? null,
            'departure_at' => $metadata['departure_at'] ?? null,
            'expected_return_at' => $metadata['expected_return_at'] ?? null,
            'travel_mode' => $metadata['travel_mode'] ?? 'individual',
            'responsible_user_id' => $metadata['responsible_user_id'] ?? null,
            'responsible_name' => $metadata['responsible_name'] ?? null,
            'responsible_phone' => $metadata['responsible_phone'] ?? null,
            'description' => $metadata['description'] ?? null,
            'equipment_notes' => $metadata['equipment_notes'] ?? null,
            'document_url' => $metadata['document_url'] ?? null,
            'schedule_url' => $metadata['schedule_url'] ?? null,
            'participant_list_url' => $metadata['participant_list_url'] ?? null,
            'website_url' => $metadata['website_url'] ?? null,
            'transport_notes' => $metadata['transport_notes'] ?? null,
            'drivers_needed' => $metadata['drivers_needed'] ?? null,
            'seats_needed' => $metadata['seats_needed'] ?? null,
            'accommodation_required' => (bool) ($metadata['accommodation_required'] ?? false),
            'accommodation_name' => $metadata['accommodation_name'] ?? null,
            'accommodation_address' => $metadata['accommodation_address'] ?? null,
            'accommodation_notes' => $metadata['accommodation_notes'] ?? null,
            'accommodation_check_in_at' => $metadata['accommodation_check_in_at'] ?? null,
            'accommodation_check_out_at' => $metadata['accommodation_check_out_at'] ?? null,
            'accommodation_cost_cents' => $metadata['accommodation_cost_cents'] ?? null,
            'notify_participants' => (bool) ($metadata['notify_participants'] ?? true),
            'reminder_hours_before' => $metadata['reminder_hours_before'] ?? 24,
            'cancellation_reason' => $metadata['cancellation_reason'] ?? null,
        ];
    }

    private function syncCalendarEvent(Competition $competition, int $actorId): void
    {
        $competition->refresh()->load('venues');
        $metadata = $competition->metadata ?? [];
        $eventId = (int) ($metadata['calendar_event_id'] ?? 0);

        if (! ($metadata['calendar_enabled'] ?? false)) {
            if ($eventId) {
                Event::query()->where('competition_id', $competition->id)->whereKey($eventId)->delete();
                unset($metadata['calendar_event_id']);
                $competition->update(['metadata' => $metadata]);
            }

            return;
        }

        $scope = $metadata['calendar_scope'] ?? ($competition->team_id ? 'team' : 'club');
        if ($scope === 'team' && ! $competition->team_id) {
            throw ValidationException::withMessages(['calendar_scope' => 'Für die Team-Sichtbarkeit muss ein Team ausgewählt werden.']);
        }
        $userIds = collect($metadata['calendar_user_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($scope === 'people' && $userIds->isEmpty()) {
            throw ValidationException::withMessages(['calendar_user_ids' => 'Wähle mindestens eine Person für den Kalendereintrag aus.']);
        }

        $timezone = LocalDateTime::timezoneFor(User::query()->find($actorId));
        $startTime = $metadata['calendar_start_time'] ?? '09:00';
        $endTime = $metadata['calendar_end_time'] ?? '18:00';
        $startsAt = CarbonImmutable::parse($competition->starts_on->format('Y-m-d').' '.$startTime, $timezone);
        $endsAt = CarbonImmutable::parse(($competition->ends_on ?? $competition->starts_on)->format('Y-m-d').' '.$endTime, $timezone);
        if (! $endsAt->isAfter($startsAt)) {
            throw ValidationException::withMessages(['calendar_end_time' => 'Das Kalenderende muss nach dem Beginn liegen.']);
        }

        $venue = $competition->venues->sortBy('id')->first();
        $responsibleName = filled($metadata['responsible_name'] ?? null)
            ? $metadata['responsible_name']
            : User::query()->whereKey($metadata['responsible_user_id'] ?? 0)->value('name');
        $event = $eventId
            ? Event::query()->where('competition_id', $competition->id)->whereKey($eventId)->first()
            : null;
        $event ??= new Event(['competition_id' => $competition->id]);
        $isNewEvent = ! $event->exists;
        $previousEvent = $event->exists ? $event->only([
            'title', 'team_id', 'visibility', 'status', 'start_time', 'end_time', 'location_name', 'notes',
        ]) : [];
        $event->fill([
            'club_id' => $competition->club_id,
            'user_id' => $actorId,
            'team_id' => $scope === 'team' ? $competition->team_id : null,
            'title' => $competition->name,
            'type' => 'match',
            'visibility' => $scope === 'club' ? 'organization' : 'private',
            'status' => $competition->status === 'cancelled' ? 'cancelled' : 'scheduled',
            'start_time' => $startsAt->utc(),
            'end_time' => $endsAt->utc(),
            'event_timezone' => $timezone,
            'location' => $venue?->name,
            'location_name' => $venue?->name,
            'location_street' => $venue?->street,
            'location_house_number' => $venue?->house_number,
            'location_postal_code' => $venue?->postal_code,
            'location_city' => $venue?->city,
            'location_country' => $venue?->country,
            'participant_response_required' => true,
            'participant_response_deadline_at' => $competition->registration_deadline_at,
            'notes' => collect([
                $metadata['description'] ?? null,
                filled($metadata['meeting_at'] ?? null) ? 'Treffzeit: '.$metadata['meeting_at'] : null,
                filled($metadata['meeting_point'] ?? null) ? 'Treffpunkt: '.$metadata['meeting_point'] : null,
                filled($metadata['departure_at'] ?? null) ? 'Abfahrt: '.$metadata['departure_at'] : null,
                filled($metadata['expected_return_at'] ?? null) ? 'Rückkehr: '.$metadata['expected_return_at'] : null,
                $metadata['equipment_notes'] ?? null,
                $metadata['transport_notes'] ?? null,
                filled($responsibleName) ? 'Kontakt: '.$responsibleName.(filled($metadata['responsible_phone'] ?? null) ? ' · '.$metadata['responsible_phone'] : '') : null,
                filled($metadata['accommodation_name'] ?? null) ? 'Unterkunft: '.$metadata['accommodation_name'].(filled($metadata['accommodation_address'] ?? null) ? ' · '.$metadata['accommodation_address'] : '') : null,
                filled($metadata['accommodation_check_in_at'] ?? null) ? 'Check-in: '.$metadata['accommodation_check_in_at'] : null,
                filled($metadata['accommodation_check_out_at'] ?? null) ? 'Check-out: '.$metadata['accommodation_check_out_at'] : null,
                isset($metadata['accommodation_cost_cents']) ? 'Unterkunftskosten: '.number_format(((int) $metadata['accommodation_cost_cents']) / 100, 2, ',', '.').' €' : null,
                $metadata['accommodation_notes'] ?? null,
                filled($metadata['document_url'] ?? null) ? 'Dokumente: '.$metadata['document_url'] : null,
                filled($metadata['schedule_url'] ?? null) ? 'Zeitplan: '.$metadata['schedule_url'] : null,
                filled($metadata['participant_list_url'] ?? null) ? 'Teilnehmerliste: '.$metadata['participant_list_url'] : null,
                filled($metadata['website_url'] ?? null) ? 'Webseite: '.$metadata['website_url'] : null,
            ])->filter()->implode("\n\n") ?: null,
            'reminder_at' => $startsAt->subHours((int) ($metadata['reminder_hours_before'] ?? 24))->utc(),
            'cancelled_at' => $competition->status === 'cancelled' ? ($event->cancelled_at ?? now()) : null,
            'cancelled_by' => $competition->status === 'cancelled' ? $actorId : null,
            'cancellation_reason' => $competition->status === 'cancelled' ? ($metadata['cancellation_reason'] ?? null) : null,
        ])->save();

        if ($scope === 'people') {
            $existingIds = $event->participants()->pluck('users.id')->map(fn ($id) => (int) $id);
            $event->participants()->detach($existingIds->diff($userIds));
            $event->participants()->attach(
                $userIds->diff($existingIds)->mapWithKeys(fn (int $userId) => [$userId => ['status' => 'maybe']])->all(),
            );
        } else {
            $event->participants()->detach();
        }
        if ($eventId !== (int) $event->id) {
            $metadata['calendar_event_id'] = $event->id;
            $competition->update(['metadata' => $metadata]);
        }

        $notificationFingerprint = hash('sha256', json_encode([
            $competition->only(['team_id', 'name', 'status', 'starts_on', 'ends_on', 'registration_deadline_at']),
            collect($metadata)->except(['calendar_event_id', 'notification_fingerprint'])->sortKeys()->all(),
        ], JSON_THROW_ON_ERROR));
        $eventChanged = $isNewEvent
            || ($metadata['notification_fingerprint'] ?? null) !== $notificationFingerprint
            || collect($previousEvent)->contains(
            fn ($value, $key) => (string) $value !== (string) $event->getAttribute($key),
        );
        if (($metadata['notify_participants'] ?? true) && $eventChanged) {
            $this->notifyCompetitionAudience($competition, $event, $scope, $userIds, $actorId, $isNewEvent);
        }
        if (($metadata['notification_fingerprint'] ?? null) !== $notificationFingerprint) {
            $metadata['notification_fingerprint'] = $notificationFingerprint;
            $competition->update(['metadata' => $metadata]);
        }
    }

    private function notifyCompetitionAudience(
        Competition $competition,
        Event $event,
        string $scope,
        $userIds,
        int $actorId,
        bool $created,
    ): void {
        $recipients = match ($scope) {
            'people' => User::query()->whereKey($userIds)->get(),
            'team' => $competition->team?->users()->get() ?? collect(),
            default => $competition->club->users()
                ->where(fn ($query) => $query->whereNull('club_user.membership_status')->orWhere('club_user.membership_status', 'active'))
                ->get(),
        };
        $scopeName = $scope === 'team' ? ($competition->team?->name ?? $competition->club->name) : $competition->club->name;
        $date = $event->start_time?->timezone($event->event_timezone ?: config('app.timezone', 'UTC'))->format('d.m.Y H:i');

        $recipients->unique('id')->reject(fn (User $user) => (int) $user->id === $actorId)->each(
            fn (User $user) => AppNotification::sendLocalized(
                $user,
                $created ? 'competition.created' : 'competition.updated',
                $created
                    ? 'server.events.notifications.competition_created_title'
                    : 'server.events.notifications.competition_updated_title',
                $created
                    ? 'server.events.notifications.competition_created_body'
                    : 'server.events.notifications.competition_updated_body',
                ['event' => $event->title, 'scope' => $scopeName, 'date' => $date],
                [
                    'url' => route('auth.events.show', $event),
                    'mobile_url' => 'airmius://events/'.$event->id,
                    'deep_link' => 'airmius://events/'.$event->id,
                    'event_id' => $event->id,
                    'competition_id' => $competition->id,
                    'club_id' => $competition->club_id,
                    'team_id' => $event->team_id,
                    'dedupe_key' => 'competition:'.$competition->id.':event-change:'.($event->updated_at?->format('YmdHisv') ?? 'new'),
                ],
            ),
        );
    }
}
