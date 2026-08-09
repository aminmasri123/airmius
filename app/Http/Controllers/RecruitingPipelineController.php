<?php

namespace App\Http\Controllers;

use App\Models\OrganizationJobInterest;
use App\Services\RecruitingPipelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RecruitingPipelineController extends Controller
{
    public function __construct(private RecruitingPipelineService $pipeline) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(RecruitingPipelineService::STATUSES)],
            'job_id' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return Inertia::render('Auth/Dashboard/Recruiting/Index', $this->pipeline->payload(
            $request->user(),
            $filters,
        ));
    }

    public function update(Request $request, OrganizationJobInterest $interest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(RecruitingPipelineService::STATUSES)],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->pipeline->update($request->user(), $interest, $data);

        return back()->with('success', __('recruiting.pipeline.updated'));
    }

    public function destroy(Request $request, OrganizationJobInterest $interest): RedirectResponse
    {
        $this->pipeline->erase($request->user(), $interest);

        return back()->with('success', __('recruiting.pipeline.erased'));
    }

    public function chat(Request $request, OrganizationJobInterest $interest): RedirectResponse
    {
        $conversation = $this->pipeline->openConversation($request->user(), $interest);

        return redirect()->route('auth.conversations.index', ['conversation' => $conversation->id]);
    }
}
