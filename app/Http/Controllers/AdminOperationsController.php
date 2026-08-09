<?php

namespace App\Http\Controllers;

use App\Services\AdminOperationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminOperationsController extends Controller
{
    public function __construct(private readonly AdminOperationsService $operations) {}

    public function index(Request $request): Response
    {
        $workspaces = $this->operations->workspaces($request->user());
        abort_if($workspaces === [], 403);

        return Inertia::render('Auth/Dashboard/Admin/Operations/Index', [
            'workspaces' => $workspaces,
            'dataEndpoint' => route('admin.operations.data', absolute: false),
            'copy' => [
                ...trans('admin_operations.ui'),
                'priorities' => trans('admin_operations.priorities'),
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace' => ['required', 'string', Rule::in([
                AdminOperationsService::WORKSPACE_PLATFORM,
                AdminOperationsService::WORKSPACE_TRUST,
                AdminOperationsService::WORKSPACE_REVENUE,
            ])],
        ]);

        return response()->json([
            'data' => $this->operations->payload($request->user(), $validated['workspace']),
        ]);
    }
}
