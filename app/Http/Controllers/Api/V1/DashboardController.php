<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AthleteDailyFlowService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function dailyFlow(Request $request, AthleteDailyFlowService $dailyFlow)
    {
        return response()->json([
            'data' => $dailyFlow->forUser($request->user()),
        ]);
    }
}
