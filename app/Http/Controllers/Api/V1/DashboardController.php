<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AthleteDailyFlowService;
use App\Services\DashboardAttentionService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function dailyFlow(
        Request $request,
        AthleteDailyFlowService $dailyFlow,
        DashboardAttentionService $attention,
    ) {
        $data = $dailyFlow->forUser($request->user());
        $data['attention'] = $attention->forUser($request->user());

        return response()->json([
            'data' => $data,
        ]);
    }
}
