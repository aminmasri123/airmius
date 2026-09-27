<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AdminOperationsService;
use App\Services\ProductAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminInsightsController extends Controller
{
    public function operations(Request $request, AdminOperationsService $operations)
    {
        $workspaces = $operations->workspaces($request->user());
        abort_if($workspaces === [], 403);
        $data = $request->validate(['workspace' => ['nullable', Rule::in(array_column($workspaces, 'key'))]]);

        return response()->json(['data' => [
            'workspaces' => $workspaces,
            ...$operations->payload($request->user(), $data['workspace'] ?? $workspaces[0]['key']),
        ]]);
    }

    public function analytics(Request $request, ProductAnalyticsService $analytics)
    {
        abort_unless($request->user()->can('analytics.view'), 403);
        $data = $request->validate(['days' => ['nullable', 'integer', Rule::in(config('product_analytics.allowed_windows', [7, 28, 90]))]]);

        return response()->json(['data' => $analytics->dashboard(isset($data['days']) ? (int) $data['days'] : null)]);
    }
}
