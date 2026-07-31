<?php

namespace App\Http\Controllers;

use App\Http\Requests\SponsorProfileRequest;
use App\Services\SponsorWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SponsorWorkspaceController extends Controller
{
    public function __construct(private SponsorWorkspaceService $workspace) {}

    public function index(Request $request): Response
    {
        abort_unless($this->workspace->canOpen($request->user()), 403);

        return Inertia::render('Auth/Dashboard/SponsorWorkspace/Index', [
            'workspace' => $this->workspace->payload($request->user()),
        ]);
    }

    public function updateProfile(SponsorProfileRequest $request): RedirectResponse
    {
        $this->workspace->saveOwnProfile($request->user(), $request->validated());

        return back()->with('success', 'Sponsorprofil gespeichert.');
    }
}
