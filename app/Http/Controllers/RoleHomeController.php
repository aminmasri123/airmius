<?php

namespace App\Http\Controllers;

use App\Services\SponsorWorkspaceService;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoleHomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        $registrationOnboarding = $request->session()->pull('registration_onboarding');
        if (is_array($registrationOnboarding)) {
            if (($registrationOnboarding['type'] ?? null) === 'club') {
                return redirect()->route('auth.teams.index', ['create_club' => 1]);
            }

            if (($registrationOnboarding['type'] ?? null) === 'coach') {
                return redirect()->route('auth.settings', ['tab' => 'roles', 'onboarding' => 'trainer']);
            }
        }

        $roles = $user->getRoleNames();

        if ($roles->intersect(Roles::FULL_ACCESS)->isNotEmpty()) {
            return redirect()->route('auth.dashboard');
        }

        $canOpenClub = ClubCockpitController::userCanView($user);
        $canOpenCoach = TrainerCockpitController::userCanView($user);
        $canOpenSponsor = app(SponsorWorkspaceService::class)->canOpen($user);
        $destinations = collect([
            $canOpenClub
                ? route('auth.club-cockpit.index')
                : null,
            $canOpenCoach
                ? route('auth.trainer-cockpit.index')
                : null,
            $canOpenSponsor
                ? route('auth.sponsor-workspace.index')
                : null,
            $roles->intersect(Roles::PARENT)->isNotEmpty()
                ? route('guardian-access.children')
                : null,
        ])->filter()->unique()->values();

        if ($destinations->count() > 1) {
            return redirect()->route('auth.workspaces.index');
        }

        if ($destinations->isNotEmpty()) {
            return redirect()->to($destinations->first());
        }

        return redirect()->route('auth.feed.index');
    }
}
