<?php

namespace App\Http\Controllers;

use App\Services\SupportAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportCenterController extends Controller
{
    public function __construct(private readonly SupportAccessService $access) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $scope = $this->access->operatorScope($user);
        $clubs = $this->access->linkedClubs($user)
            ->load(['departments:id,club_id,name', 'teams:id,club_id,club_department_id,name']);
        $supportClubIds = $scope['global'] ? collect() : $this->access->supportClubs($user)->pluck('id');
        $clubPayload = fn ($club): array => [
            'id' => (int) $club->id,
            'name' => $club->name,
            'departments' => $club->departments->map->only(['id', 'name'])->values(),
            'teams' => $club->teams->map->only(['id', 'name', 'club_department_id'])->values(),
        ];
        $supportClubPayload = function ($club) use ($clubPayload, $scope): array {
            $payload = $clubPayload($club);
            if (in_array((int) $club->id, $scope['club_ids'], true)) {
                return $payload;
            }

            $payload['departments'] = $club->departments
                ->whereIn('id', $scope['department_ids'])->map->only(['id', 'name'])->values();
            $payload['teams'] = $club->teams
                ->whereIn('id', $scope['team_ids'])->map->only(['id', 'name', 'club_department_id'])->values();

            return $payload;
        };

        return Inertia::render('Auth/Dashboard/Support/Index', [
            'clubs' => $clubs->map($clubPayload)->values(),
            'supportClubs' => $scope['global']
                ? []
                : $clubs->filter(fn ($club) => $supportClubIds->contains($club->id))->map($supportClubPayload)->values(),
            'abilities' => [
                'operate' => $this->access->canOperate($user),
                'cross_tenant' => $scope['global'],
            ],
            'currentUser' => $user->only(['id', 'name']),
            'copy' => __('support.web'),
            'options' => [
                'categories' => $this->options(['technical', 'club', 'membership', 'payment', 'privacy'], 'support.categories'),
                'priorities' => $this->options(['low', 'normal', 'high', 'urgent'], 'support.priorities'),
                'statuses' => $this->options(['open', 'in_progress', 'waiting_user', 'resolved', 'closed'], 'support.statuses'),
            ],
        ]);
    }

    /** @return array<int, array{value: string, label: string}> */
    private function options(array $values, string $translationGroup): array
    {
        return collect($values)->map(fn (string $value): array => [
            'value' => $value,
            'label' => __("{$translationGroup}.{$value}"),
        ])->all();
    }
}
