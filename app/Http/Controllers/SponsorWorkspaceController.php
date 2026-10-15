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
            'workspace' => $this->workspace->payload($request->user(), $request->integer('sponsor_id') ?: null),
        ]);
    }

    public function updateProfile(SponsorProfileRequest $request): RedirectResponse
    {
        $this->workspace->saveOwnProfile(
            $request->user(),
            $request->validated(),
            $request->integer('sponsor_id') ?: null,
        );

        return back()->with('success', __('sponsor.flash.profile_saved'));
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sponsor_id' => ['nullable', 'integer'],
            'variant' => ['required', 'in:primary,light,dark'],
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $this->workspace->uploadOwnLogo(
            $request->user(),
            $request->file('logo'),
            $data['variant'],
            $request->integer('sponsor_id') ?: null,
        );

        return back()->with('success', __('sponsor.flash.profile_saved'));
    }
}
