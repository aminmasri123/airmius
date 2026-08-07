<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SportMatchingResource;
use App\Models\Sport;
use App\Models\SportMatching;
use App\Models\SportMatchingApplication;
use App\Models\Team;
use App\Models\UserBlock;
use App\Support\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SportMatchingController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'mode' => ['nullable', Rule::in(SportMatching::MODES)],
            'sport_id' => ['nullable', 'integer', 'exists:sports,id'],
            'city' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'skill_level' => ['nullable', Rule::in(SportMatching::SKILL_LEVELS)],
            'status' => ['nullable', Rule::in(SportMatching::STATUSES)],
        ]);

        $query = SportMatching::query()
            ->with(['user:id,name,profile_photo_path', 'sport:id,name,slug', 'team.club', 'applications.user', 'applications.team'])
            ->withCount([
                'applications',
                'applications as accepted_count' => fn ($q) => $q->where('status', 'accepted'),
            ])
            ->addSelect([
                'my_application' => SportMatchingApplication::query()
                    ->select('status')
                    ->whereColumn('sport_matching_id', 'sport_matchings.id')
                    ->where('user_id', $request->user()->id)
                    ->limit(1),
            ])
            ->whereDoesntHave('dismissals', fn ($q) => $q->where('user_id', $request->user()->id))
            ->whereNotIn('user_id', UserBlock::query()->select('blocked_user_id')->where('user_id', $request->user()->id))
            ->whereNotIn('user_id', UserBlock::query()->select('user_id')->where('blocked_user_id', $request->user()->id))
            ->when($filters['mode'] ?? null, fn ($q, $value) => $q->where('mode', $value))
            ->when($filters['sport_id'] ?? null, fn ($q, $value) => $q->where('sport_id', $value))
            ->when($filters['location'] ?? $filters['city'] ?? null, function ($q, $value) {
                $q->where(function ($query) use ($value) {
                    $query->where('city', 'like', '%'.$value.'%')
                        ->orWhere('postal_code', 'like', '%'.$value.'%')
                        ->orWhere('location_name', 'like', '%'.$value.'%');
                });
            })
            ->when($filters['radius_km'] ?? null, fn ($q, $value) => $q->where('radius_km', '<=', $value))
            ->when($filters['skill_level'] ?? null, fn ($q, $value) => $q->whereIn('skill_level', [$value, 'all']))
            ->when($filters['status'] ?? 'open', fn ($q, $value) => $q->where('status', $value))
            ->where('starts_at', '>=', now()->subHours(3))
            ->orderBy('starts_at');

        return SportMatchingResource::collection($query->paginate(min(max($request->integer('per_page', 20), 1), 50)))
            ->additional($this->catalogs($request));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;
        $data['title'] = blank($data['title'] ?? null)
            ? $this->defaultTitle($data)
            : $data['title'];
        $this->validateTeam($request, $data);

        return (new SportMatchingResource(
            SportMatching::create($data)->load(['user', 'sport', 'team.club'])
        ))->response()->setStatusCode(201);
    }

    public function apply(Request $request, SportMatching $sportMatching)
    {
        abort_if($sportMatching->status !== 'open' || $sportMatching->user_id === $request->user()->id, 422);
        $data = $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($sportMatching->mode === 'team') {
            abort_if(empty($data['team_id']), 422, 'Für Team-Matching ist ein Team erforderlich.');
            $this->assertTeamMember($request, (int) $data['team_id']);
        } else {
            $data['team_id'] = null;
        }

        $application = SportMatchingApplication::updateOrCreate(
            [
                'sport_matching_id' => $sportMatching->id,
                'user_id' => $request->user()->id,
                'team_id' => $data['team_id'] ?? null,
            ],
            ['message' => $data['message'] ?? null, 'status' => 'pending'],
        );
        AppNotification::send($sportMatching->user_id, 'sport_matching.application', [
            'title' => 'Neue Matching-Anfrage',
            'body' => $request->user()->name.' interessiert sich für '.$sportMatching->title,
            'matching_id' => $sportMatching->id,
        ]);

        return response()->json(['data' => $application->load(['user', 'team'])]);
    }

    public function dismiss(Request $request, SportMatching $sportMatching)
    {
        abort_if($sportMatching->user_id === $request->user()->id, 422);

        $data = $request->validate([
            'dismissed' => ['sometimes', 'boolean'],
        ]);

        if (($data['dismissed'] ?? true) === false) {
            $sportMatching->dismissals()->where('user_id', $request->user()->id)->delete();
        } else {
            $sportMatching->dismissals()->firstOrCreate(['user_id' => $request->user()->id]);
        }

        return response()->json(['data' => ['matching_id' => $sportMatching->id, 'dismissed' => ($data['dismissed'] ?? true)]]);
    }

    public function decide(Request $request, SportMatching $sportMatching, SportMatchingApplication $application)
    {
        abort_unless($sportMatching->user_id === $request->user()->id && $application->sport_matching_id === $sportMatching->id, 403);
        $data = $request->validate(['status' => ['required', Rule::in(['accepted', 'declined'])]]);
        $application->update(['status' => $data['status']]);

        if ($data['status'] === 'accepted') {
            $accepted = $sportMatching->applications()->where('status', 'accepted')->count();
            if ($sportMatching->mode === 'team' || $accepted >= $sportMatching->participants_needed) {
                $sportMatching->update(['status' => 'matched']);
            }
        }
        AppNotification::send($application->user_id, 'sport_matching.decision', [
            'title' => $data['status'] === 'accepted' ? 'Matching bestätigt' : 'Matching-Anfrage abgelehnt',
            'body' => $sportMatching->title,
            'matching_id' => $sportMatching->id,
        ]);

        return response()->json(['data' => $application->fresh(['user', 'team'])]);
    }

    public function cancel(Request $request, SportMatching $sportMatching)
    {
        abort_unless($sportMatching->user_id === $request->user()->id, 403);
        $sportMatching->update(['status' => 'cancelled']);
        return response()->json(['data' => ['id' => $sportMatching->id, 'status' => 'cancelled']]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'mode' => ['required', Rule::in(SportMatching::MODES)],
            'sport_id' => ['required', 'integer', 'exists:sports,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'title' => ['nullable', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:2000'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'location_name' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:240'],
            'country_code' => ['required', 'string', 'size:2'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['required', 'integer', 'min:1', 'max:500'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'participants_needed' => ['required', 'integer', 'min:1', 'max:500'],
            'team_size' => ['nullable', 'required_if:mode,team', 'integer', 'min:1', 'max:500'],
            'skill_level' => ['required', Rule::in(SportMatching::SKILL_LEVELS)],
        ]);
    }

    private function validateTeam(Request $request, array &$data): void
    {
        if ($data['mode'] === 'team') {
            abort_if(empty($data['team_id']), 422);
            $this->assertTeamMember($request, (int) $data['team_id']);
            $data['participants_needed'] = 1;
        } else {
            $data['team_id'] = null;
            $data['team_size'] = null;
        }
    }

    private function defaultTitle(array $data): string
    {
        $sportName = Sport::query()->whereKey($data['sport_id'])->value('name') ?: 'Sport';

        return $data['mode'] === 'team'
            ? $sportName.'-Teamgegner gesucht'
            : $sportName.'-Sportpartner gesucht';
    }

    private function assertTeamMember(Request $request, int $teamId): void
    {
        abort_unless(Team::query()->whereKey($teamId)
            ->whereHas('users', fn ($q) => $q->where('users.id', $request->user()->id))->exists(), 403);
    }

    private function catalogs(Request $request): array
    {
        return ['meta' => [
            'sports' => Sport::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug']),
            'teams' => Team::query()->whereHas('users', fn ($q) => $q->where('users.id', $request->user()->id))
                ->orderBy('name')->get(['id', 'name', 'sport_type']),
            'modes' => SportMatching::MODES,
            'skill_levels' => SportMatching::SKILL_LEVELS,
        ]];
    }
}
