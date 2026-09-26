<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TrainingLog;
use App\Services\Training\TrainingFeedbackService;
use Illuminate\Http\Request;

class TrainingFeedbackController extends Controller
{
    public function __construct(private readonly TrainingFeedbackService $feedback) {}

    public function store(Request $request, TrainingLog $trainingLog)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:3000'],
        ]);

        $feedback = $this->feedback->create($request->user(), $trainingLog, $data['body']);

        return response()->json([
            'message' => __('server.training.feedback_sent'),
            'data' => [
                'id' => $feedback->id,
                'training_log_id' => $feedback->training_log_id,
                'user_id' => $feedback->user_id,
                'body' => $feedback->body,
                'role' => $feedback->role,
                'classification' => $feedback->classification,
                'retention_until' => $feedback->retention_until?->toJSON(),
                'created_at' => $feedback->created_at?->toJSON(),
                'author' => $feedback->author ? [
                    'id' => $feedback->author->id,
                    'name' => trim(($feedback->author->first_name ?? '').' '.($feedback->author->last_name ?? '')) ?: $feedback->author->name,
                ] : null,
            ],
        ], 201);
    }
}
