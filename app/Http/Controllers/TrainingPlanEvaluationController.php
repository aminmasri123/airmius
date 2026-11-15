<?php

namespace App\Http\Controllers;

use App\Models\TrainingLog;
use App\Services\Training\TrainingFeedbackService;
use App\Services\Training\TrainingPlanEvaluationService;
use Illuminate\Http\Request;

class TrainingPlanEvaluationController extends Controller
{
    public function index(Request $request, TrainingPlanEvaluationService $evaluation)
    {
        abort_unless(TrainerCockpitController::userCanView($request->user()), 403);
        $filters = $request->validate([
            'plan_id' => ['nullable', 'integer', 'min:1'],
            'week' => ['nullable', 'regex:/^(unscheduled|\d{4}-\d{2}-\d{2})$/'],
            'page' => ['nullable', 'integer', 'min:1'], 'search' => ['nullable', 'string', 'max:100'],
            'open_only' => ['nullable', 'boolean'],
        ]);

        return response()->json(['data' => $evaluation->evaluate($request->user(), $filters)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function feedback(Request $request, TrainingLog $trainingLog, TrainingPlanEvaluationService $evaluation, TrainingFeedbackService $feedback)
    {
        abort_unless(TrainerCockpitController::userCanView($request->user()), 403);
        $evaluation->authorizeFeedback($request->user(), $trainingLog);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:3000']]);
        $feedback->create($request->user(), $trainingLog, $data['body']);

        return response()->json(['message' => __('server.training.feedback_sent')], 201);
    }
}
