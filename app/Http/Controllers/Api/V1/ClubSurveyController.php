<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubSurvey;
use App\Models\ClubSurveyOption;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubSurveyController extends Controller
{
    public function index(Request $request, Club $club)
    {
        $canManage = $this->authorizeSurveyAccess($request, $club);
        $teamIds = $canManage
            ? []
            : $club->teams()
                ->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))
                ->pluck('teams.id')
                ->all();

        $surveys = ClubSurvey::query()
            ->where('club_id', $club->id)
            ->when(! $canManage, function ($query) use ($teamIds) {
                $query->where(function ($visible) use ($teamIds) {
                    $visible->where('audience_type', 'all_members');
                    if ($teamIds !== []) {
                        $visible->orWhereIn('team_id', $teamIds);
                    }
                });
            })
            ->with([
                'options',
                'team:id,name',
                'votes' => fn ($query) => $query->where('user_id', $request->user()->id),
            ])
            ->withCount('votes')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $surveys->map(fn (ClubSurvey $survey) => $this->payload($survey, $canManage)),
        ]);
    }

    public function store(Request $request, Club $club)
    {
        Gate::forUser($request->user())->authorize('update', $club);

        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'audience_type' => ['required', Rule::in(['all_members', 'team'])],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'quorum' => ['nullable', 'integer', 'min:1', 'max:100'],
            'closes_at' => ['nullable', 'date', 'after:now'],
            'options' => ['required', 'array', 'min:2', 'max:6'],
            'options.*' => ['required', 'string', 'max:255'],
        ]);

        $teamId = $data['team_id'] ?? null;
        if ($data['audience_type'] === 'team') {
            if (! $teamId || ! Team::query()->whereKey($teamId)->where('club_id', $club->id)->exists()) {
                throw ValidationException::withMessages([
                    'team_id' => 'Bitte ein Team dieses Vereins auswählen.',
                ]);
            }
        } else {
            $teamId = null;
        }

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

            foreach (array_values($data['options']) as $index => $label) {
                $survey->options()->create([
                    'label' => trim($label),
                    'sort_order' => $index,
                ]);
            }

            return $survey->load(['options', 'team:id,name']);
        });

        return response()->json([
            'message' => 'Umfrage erstellt.',
            'data' => $this->payload($survey, true),
        ], 201);
    }

    public function vote(Request $request, Club $club, ClubSurvey $survey)
    {
        abort_unless((int) $survey->club_id === (int) $club->id, 404);
        $this->authorizeSurveyAccess($request, $club);
        abort_unless($this->canVote($request, $survey), 403, 'Diese Umfrage ist für dich nicht freigegeben.');

        if (! $survey->isOpen()) {
            throw ValidationException::withMessages([
                'survey' => 'Diese Umfrage ist geschlossen.',
            ]);
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
            'message' => 'Stimme gespeichert.',
            'data' => [
                'vote_id' => $vote->id,
                'survey_id' => $survey->id,
                'option_id' => $option->id,
            ],
        ]);
    }

    public function close(Request $request, Club $club, ClubSurvey $survey)
    {
        abort_unless((int) $survey->club_id === (int) $club->id, 404);
        Gate::forUser($request->user())->authorize('update', $club);

        $survey->update(['status' => 'closed']);

        return response()->json([
            'message' => 'Umfrage geschlossen.',
            'data' => [
                'id' => $survey->id,
                'status' => $survey->status,
            ],
        ]);
    }

    private function authorizeSurveyAccess(Request $request, Club $club): bool
    {
        $user = $request->user();
        $canManage = Gate::forUser($user)->allows('update', $club);
        if ($canManage) {
            return true;
        }

        $isMember = $club->users()
            ->where('users.id', $user->id)
            ->where(function ($query) {
                $query->whereNull('club_user.membership_status')
                    ->orWhere('club_user.membership_status', 'active');
            })
            ->exists();

        abort_unless($isMember, 403, 'Nur Vereinsmitglieder können Vereinsumfragen sehen.');

        return false;
    }

    private function canVote(Request $request, ClubSurvey $survey): bool
    {
        if (Gate::forUser($request->user())->allows('update', $survey->club)) {
            return true;
        }

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

    private function payload(ClubSurvey $survey, bool $canManage): array
    {
        $totalVotes = (int) ($survey->votes_count ?? $survey->votes()->count());
        $eligible = $this->eligibleVoterCount($survey);
        $quorumReached = $survey->quorum === null
            ? true
            : ($eligible > 0 && $totalVotes >= (int) ceil($eligible * $survey->quorum / 100));

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
            'can_manage' => $canManage,
            'options' => $survey->options->map(fn (ClubSurveyOption $option) => [
                'id' => $option->id,
                'label' => $option->label,
                'sort_order' => (int) $option->sort_order,
                'votes' => $option->votes()->count(),
            ])->values()->all(),
        ];
    }
}
