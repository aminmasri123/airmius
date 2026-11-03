<?php

namespace App\Http\Controllers;

use App\Models\TrainingAiGeneration;
use App\Services\Training\TrainingAiPlanService;
use App\Services\Training\TrainingResourceService;
use Illuminate\Http\Request;

class TrainingAiGenerationController extends Controller
{
    public function show(Request $request, string $generation = 'latest')
    {
        abort_unless(app(TrainingResourceService::class)->canManageTrainingPlans($request->user()), 403);
        $query = TrainingAiGeneration::where('user_id', $request->user()->id)->where('created_at', '>=', now()->subDay());
        $record = $generation === 'latest' ? $query->latest()->first() : $query->whereKey($generation)->firstOrFail();
        if (! $record) {
            return response()->json(['status' => 'none']);
        }
        if (in_array($record->status, ['queued', 'running']) && $record->updated_at->lt(now()->subMinutes(15))) {
            $record->update(['status' => 'failed', 'error' => 'Die Erstellung wurde nicht rechtzeitig abgeschlossen. Bitte erneut versuchen.']);
        }
        $plan = $record->status === 'completed' ? app(TrainingAiPlanService::class)->attachSafetyProof($request->user(), $record->result) : null;

        return response()->json(['id' => $record->id, 'status' => $record->status, 'message' => $record->error, 'plan' => $plan]);
    }
}
