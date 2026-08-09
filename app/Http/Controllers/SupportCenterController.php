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

        return Inertia::render('Auth/Dashboard/Support/Index', [
            'clubs' => $this->access->linkedClubs($user)->map->only(['id', 'name'])->values(),
            'supportClubs' => $scope['global']
                ? []
                : $this->access->supportClubs($user)->map->only(['id', 'name'])->values(),
            'abilities' => [
                'operate' => $scope['global'] || $scope['club_ids'] !== [],
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
