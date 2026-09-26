<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubSurvey;
use App\Models\ClubSurveyOption;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubSurveyController extends Controller
{
    public function index(Request $request, Club $club)
    {
        $this->authorizeSurveyAccess($request, $club);
        $user = $request->user();

        $surveys = ClubSurvey::query()
            ->where('club_id', $club->id)
            ->with([
                'options',
                'team:id,name,club_id,club_department_id',
                'votes' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->withCount('votes')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (ClubSurvey $survey) => $this->canManageSurvey($survey, $user) || $this->visibleTo($request, $survey))
            ->values();

        return response()->json([
            'data' => $surveys->map(fn (ClubSurvey $survey) => $this->payload($survey, $user)),
        ]);
    }

    public function store(Request $request, Club $club)
    {
        $data = $this->validateSurvey($request, $club);
        $teamId = $this->resolveTeamId($data);
        $this->authorizeTarget($request, $club, $teamId, ClubPermissions::SURVEYS_EDIT);

        $survey = DB::transaction(function () use ($request, $club, $data, $teamId) {
            $survey = ClubSurvey::create([
                'club_id' => $club->id,
                'user_id' => $request->user()->id,
                'question' => trim($data['question']),
                'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
                'audience_type' => $data['audience_type'],
                'team_id' => $teamId,
                'quorum' => $data['quorum'] ?? null,
                'closes_at' => $data['closes_at'] ?? null,
            ]);
            $this->replaceOptions($survey, $data['options']);

            return $survey->load(['options', 'team:id,name,club_id,club_department_id']);
        });

        return response()->json([
            'message' => __('organization.survey.created'),
            'data' => $this->payload($survey, $request->user()),
        ], 201);
    }

    public function update(Request $request, Club $club, ClubSurvey $survey)
    {
        $this->ensureSurveyClub($club, $survey);
        $this->authorizeSurvey($request, $survey, ClubPermissions::SURVEYS_EDIT);
        abort_unless($survey->status === 'open', 409);
        if ($survey->votes()->exists()) {
            throw ValidationException::withMessages(['survey' => __('organization.survey.has_votes')]);
        }

        $data = $this->validateSurvey($request, $club);
        $teamId = $this->resolveTeamId($data);
        $this->authorizeTarget($request, $club, $teamId, ClubPermissions::SURVEYS_EDIT);

        DB::transaction(function () use ($survey, $data, $teamId): void {
            $survey->update([
                'question' => trim($data['question']),
                'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
                'audience_type' => $data['audience_type'],
                'team_id' => $teamId,
                'quorum' => $data['quorum'] ?? null,
                'closes_at' => $data['closes_at'] ?? null,
            ]);
            $survey->options()->delete();
            $this->replaceOptions($survey, $data['options']);
        });

        return response()->json([
            'message' => __('organization.survey.updated'),
            'data' => $this->payload(
                $survey->fresh()->load(['options', 'team:id,name,club_id,club_department_id', 'votes']),
                $request->user(),
            ),
        ]);
    }

    public function vote(Request $request, Club $club, ClubSurvey $survey)
    {
        $this->ensureSurveyClub($club, $survey);
        $this->authorizeSurveyAccess($request, $club);
        abort_unless($this->canVote($request, $survey), 403, __('organization.survey.not_available'));

        if (! $survey->isOpen()) {
            throw ValidationException::withMessages(['survey' => __('organization.survey.already_closed')]);
        }

        $data = $request->validate([
            'option_id' => ['required', 'integer', 'exists:club_survey_options,id'],
        ]);
        $option = ClubSurveyOption::query()
            ->whereKey($data['option_id'])
            ->where('club_survey_id', $survey->id)
            ->firstOrFail();
        $vote = $survey->votes()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['club_survey_option_id' => $option->id],
        );

        return response()->json([
            'message' => __('organization.survey.vote_saved'),
            'data' => [
                'vote_id' => $vote->id,
                'survey_id' => $survey->id,
                'option_id' => $option->id,
            ],
        ]);
    }

    public function close(Request $request, Club $club, ClubSurvey $survey)
    {
        $this->ensureSurveyClub($club, $survey);
        $this->authorizeSurvey($request, $survey, ClubPermissions::SURVEYS_CLOSE);
        $survey->update(['status' => 'closed']);

        return response()->json([
            'message' => __('organization.survey.closed'),
            'data' => ['id' => $survey->id, 'status' => $survey->status],
        ]);
    }

    public function destroy(Request $request, Club $club, ClubSurvey $survey)
    {
        $this->ensureSurveyClub($club, $survey);
        $this->authorizeSurvey($request, $survey, ClubPermissions::SURVEYS_DELETE);
        if ($survey->votes()->exists()) {
            throw ValidationException::withMessages(['survey' => __('organization.survey.has_votes')]);
        }
        $survey->delete();

        return response()->json([
            'message' => __('organization.survey.deleted'),
            'data' => ['deleted' => true],
        ]);
    }

    private function validateSurvey(Request $request, Club $club): array
    {
        return $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'audience_type' => ['required', Rule::in(['all_members', 'team'])],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('club_id', $club->id)],
            'quorum' => ['nullable', 'integer', 'min:1', 'max:100'],
            'closes_at' => ['nullable', 'date', 'after:now'],
            'options' => ['required', 'array', 'min:2', 'max:6'],
            'options.*' => ['required', 'string', 'max:255'],
        ]);
    }

    private function resolveTeamId(array $data): ?int
    {
        $teamId = $data['audience_type'] === 'team' ? ($data['team_id'] ?? null) : null;
        if ($data['audience_type'] === 'team' && ! $teamId) {
            throw ValidationException::withMessages(['team_id' => __('organization.survey.team_required')]);
        }

        return $teamId ? (int) $teamId : null;
    }

    private function replaceOptions(ClubSurvey $survey, array $options): void
    {
        foreach (array_values($options) as $index => $label) {
            $survey->options()->create(['label' => trim($label), 'sort_order' => $index]);
        }
    }

    private function authorizeSurveyAccess(Request $request, Club $club): void
    {
        $user = $request->user();
        if ($this->canManageAnySurvey($club, $user)) {
            return;
        }

        $isMember = $club->users()
            ->where('users.id', $user->id)
            ->where(function ($query) {
                $query->whereNull('club_user.membership_status')
                    ->orWhere('club_user.membership_status', 'active');
            })
            ->exists();
        abort_unless($isMember, 403, __('organization.survey.members_only'));
    }

    private function canVote(Request $request, ClubSurvey $survey): bool
    {
        if ($this->canManageSurvey($survey, $request->user())) {
            return true;
        }

        return $this->visibleTo($request, $survey);
    }

    private function visibleTo(Request $request, ClubSurvey $survey): bool
    {
        if ($survey->audience_type === 'all_members') {
            return true;
        }

        return $survey->team_id !== null && $survey->team()
            ->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))
            ->exists();
    }

    private function eligibleVoterCount(ClubSurvey $survey): int
    {
        if ($survey->audience_type === 'team' && $survey->team_id) {
            return $survey->team->users()->count();
        }

        return $survey->club->users()
            ->where(function ($query) {
                $query->whereNull('club_user.membership_status')
                    ->orWhereNotIn('club_user.membership_status', ['former', 'paused', 'pending']);
            })
            ->count();
    }

    private function payload(ClubSurvey $survey, User $user): array
    {
        $totalVotes = (int) ($survey->votes_count ?? $survey->votes()->count());
        $eligible = $this->eligibleVoterCount($survey);
        $quorumReached = $survey->quorum === null
            ? true
            : ($eligible > 0 && $totalVotes >= (int) ceil($eligible * $survey->quorum / 100));
        $canEdit = $this->allowsForSurvey($survey, $user, ClubPermissions::SURVEYS_EDIT);
        $canClose = $this->allowsForSurvey($survey, $user, ClubPermissions::SURVEYS_CLOSE);
        $canDelete = $this->allowsForSurvey($survey, $user, ClubPermissions::SURVEYS_DELETE);

        return [
            'id' => $survey->id,
            'club_id' => $survey->club_id,
            'question' => $survey->question,
            'description' => $survey->description,
            'status' => $survey->status,
            'audience_type' => $survey->audience_type,
            'team_id' => $survey->team_id,
            'team' => $survey->team ? ['id' => $survey->team->id, 'name' => $survey->team->name] : null,
            'quorum' => $survey->quorum,
            'eligible_voters' => $eligible,
            'quorum_reached' => $quorumReached,
            'closes_at' => $survey->closes_at?->toDateTimeString(),
            'my_option_id' => $survey->votes->first()?->club_survey_option_id,
            'votes' => $totalVotes,
            'can_manage' => $canEdit || $canClose || $canDelete,
            'can_edit' => $canEdit,
            'can_close' => $canClose,
            'can_delete' => $canDelete,
            'options' => $survey->options->map(fn (ClubSurveyOption $option) => [
                'id' => $option->id,
                'label' => $option->label,
                'sort_order' => (int) $option->sort_order,
                'votes' => $option->votes()->count(),
            ])->values()->all(),
        ];
    }

    private function ensureSurveyClub(Club $club, ClubSurvey $survey): void
    {
        abort_unless((int) $survey->club_id === (int) $club->id, 404);
    }

    private function authorizeSurvey(Request $request, ClubSurvey $survey, string $permission): void
    {
        abort_unless($this->allowsForSurvey($survey, $request->user(), $permission), 403);
    }

    private function authorizeTarget(Request $request, Club $club, ?int $teamId, string $permission): void
    {
        if ($teamId) {
            $team = Team::query()->with('club')->where('club_id', $club->id)->findOrFail($teamId);
            abort_unless(ClubPermissions::allowsForTeam($team, $request->user(), $permission), 403);

            return;
        }

        abort_unless(ClubPermissions::allows($club, $request->user(), $permission), 403);
    }

    private function allowsForSurvey(ClubSurvey $survey, User $user, string $permission): bool
    {
        if ($survey->team_id) {
            $survey->loadMissing('team.club');

            return $survey->team && ClubPermissions::allowsForTeam($survey->team, $user, $permission);
        }

        $survey->loadMissing('club');

        return $survey->club && ClubPermissions::allows($survey->club, $user, $permission);
    }

    private function canManageSurvey(ClubSurvey $survey, User $user): bool
    {
        return collect([
            ClubPermissions::SURVEYS_EDIT,
            ClubPermissions::SURVEYS_CLOSE,
            ClubPermissions::SURVEYS_DELETE,
        ])->contains(fn (string $permission) => $this->allowsForSurvey($survey, $user, $permission));
    }

    private function canManageAnySurvey(Club $club, User $user): bool
    {
        return collect([
            ClubPermissions::SURVEYS_EDIT,
            ClubPermissions::SURVEYS_CLOSE,
            ClubPermissions::SURVEYS_DELETE,
        ])->contains(fn (string $permission) => ClubPermissions::allowsAnyScope($club, $user, $permission));
    }
}
