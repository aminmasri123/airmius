<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Ai\AirmiusAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class AiCommunicationDraftController extends Controller
{
    public function __invoke(Request $request, AirmiusAiService $ai): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(['message', 'invitation', 'report'])],
            'tone' => ['nullable', 'string', 'max:80'],
            'audience' => ['required', 'string', 'max:240'],
            'purpose' => ['required', 'string', 'max:600'],
            'locale' => ['nullable', 'string', 'max:12'],
            'channel' => ['nullable', 'string', 'max:80'],
            'instructions' => ['nullable', 'string', 'max:1200'],
            'source_context' => ['required', 'array', 'min:1', 'max:8'],
            'source_context.*.label' => ['nullable', 'string', 'max:160'],
            'source_context.*.reference' => ['nullable', 'string', 'max:220'],
            'source_context.*.excerpt' => ['required', 'string', 'max:1000'],
        ]);

        try {
            return response()->json([
                'data' => $ai->generateCommunicationDraft($request->user(), $data),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
