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
        $minutes = $record->status === 'queued' ? 3 : 12;
        if (in_array($record->status, ['queued', 'running']) && $record->updated_at->lt(now()->subMinutes($minutes))) {
            TrainingAiGeneration::whereKey($record->id)->where('status', $record->status)
                ->where('updated_at', '<', now()->subMinutes($minutes))->update([
                    'status' => 'failed',
                    'error' => $record->status === 'queued'
                        ? 'Die Erstellung konnte auf dem Server nicht gestartet werden. Bitte versuche es später erneut. Wenn das wiederholt passiert, kontaktiere den Support.'
                        : 'Die Erstellung hat zu lange gedauert und wurde beendet. Bitte versuche es später erneut.',
                ]);
            $record->refresh();
        }
        $plan = $record->status === 'completed' ? app(TrainingAiPlanService::class)->attachSafetyProof($request->user(), $record->result) : null;

        return response()->json(['id' => $record->id, 'status' => $record->status, 'message' => $record->error, 'plan' => $plan])->header('Cache-Control', 'no-store, private');
    }
}
