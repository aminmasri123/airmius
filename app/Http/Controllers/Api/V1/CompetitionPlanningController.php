<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Competition;
use App\Models\CompetitionClass;
use App\Models\CompetitionLineup;
use App\Models\CompetitionRegistration;
use App\Models\CompetitionRosterEntry;
use App\Models\CompetitionTournamentMatch;
use App\Models\CompetitionTournamentStanding;
use App\Models\CompetitionVenue;
use App\Services\CompetitionRegistrationWorkflowService;
use App\Services\CompetitionSportPlanningService;
use App\Services\PlanningWorkspaceService;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CompetitionPlanningController extends Controller
{
    public function workspace(Request $request, PlanningWorkspaceService $workspace)
    {
        return response()->json(['data' => ['clubs' => $workspace->clubs($request->user())]]);
    }

    public function index(Request $request, Club $club, PlanningWorkspaceService $workspace)
    {
        $workspace->authorizeClub($club, $request->user());

        return response()->json(['data' => Competition::query()->where('club_id', $club->id)
            ->select(['id', 'club_id', 'team_id', 'season_name', 'name', 'status', 'starts_on', 'ends_on', 'registration_deadline_at'])
            ->withCount(['rosterEntries', 'registrations'])->orderByDesc('starts_on')->get()]);
    }

    public function show(Request $request, Competition $competition, PlanningWorkspaceService $workspace)
    {
        $workspace->authorizeClub($competition->club, $request->user());

        $edit = ClubPermissions::allows($competition->club, $request->user(), ClubPermissions::EVENTS_EDIT);
        $finance = collect([ClubPermissions::FINANCE_VIEW, ClubPermissions::FINANCE_EDIT, ClubPermissions::FINANCE_APPROVE])
            ->contains(fn ($permission) => ClubPermissions::allows($competition->club, $request->user(), $permission));
        $competition->load(['classes', 'venues', 'rosterEntries', 'registrations', 'tournamentGroups.entries', 'tournamentMatches']);
        $data = $competition->only(['id', 'club_id', 'team_id', 'season_name', 'name', 'status', 'starts_on', 'ends_on', 'registration_deadline_at']);

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
                'lineups' => CompetitionLineup::query()->where('competition_id', $competition->id)->with('entries')->get(),
                'standings' => CompetitionTournamentStanding::query()->where('competition_id', $competition->id)->orderBy('rank')->get(),
            ]]);
    }

    public function store(Request $request, Club $club)
    {
        $this->edit($request, $club);
        $competition = Competition::create(['club_id' => $club->id] + $this->competitionData($request, $club));
        ClubAuditLog::record($club, $request->user(), 'club.competition.created', $competition, []);

        return response()->json(['data' => $competition], 201);
    }

    public function update(Request $request, Competition $competition)
    {
        $this->edit($request, $competition->club);
        $competition->update($this->competitionData($request, $competition->club));
        ClubAuditLog::record($competition->club, $request->user(), 'club.competition.updated', $competition, []);

        return response()->json(['data' => $competition]);
    }

    public function destroy(Request $request, Competition $competition)
    {
        app(PlanningWorkspaceService::class)->authorizeClub($competition->club, $request->user());
        abort_unless(ClubPermissions::allows($competition->club, $request->user(), ClubPermissions::EVENTS_DELETE), 403);
        abort_if($competition->registrations()->exists() || $competition->tournamentMatches()->exists() || $competition->events()->exists(), 422, 'Cancel competitions with registrations, fixtures or events instead of deleting them.');
        ClubAuditLog::record($competition->club, $request->user(), 'club.competition.deleted', $competition, []);
        $competition->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function add(Request $request, Competition $competition, string $kind)
    {
        $this->edit($request, $competition->club);
        $model = $this->componentModel($kind);
        $rules = match ($kind) {
            'classes' => ['name' => ['required', 'string', 'max:120'], 'age_group' => ['nullable', 'string', 'max:80'], 'discipline' => ['nullable', 'string', 'max:120']],
            'venues' => ['name' => ['required', 'string', 'max:120'], 'city' => ['nullable', 'string', 'max:120']],
            'roster' => [
                'display_name' => ['required', 'string', 'max:120'],
                'user_id' => ['nullable', 'integer', Rule::exists('club_user', 'user_id')->where('club_id', $competition->club_id)->where(fn ($q) => $q->whereNull('membership_status')->orWhere('membership_status', 'active'))],
                'competition_class_id' => ['nullable', 'integer', Rule::exists('competition_classes', 'id')->where('competition_id', $competition->id)],
                'bib_number' => ['nullable', 'string', 'max:80'],
            ],
        };
        $data = $request->validate($rules);
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
}
