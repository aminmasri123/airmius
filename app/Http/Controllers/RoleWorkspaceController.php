<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SponsorWorkspaceService;
use App\Support\ClubRoles;
use App\Support\Roles;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoleWorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Auth/Dashboard/Workspaces/Index', [
            'workspaces' => collect($this->workspaces())
                ->filter(fn (array $workspace) => $this->visible($user, $workspace))
                ->values(),
        ]);
    }

    private function visible(User $user, array $workspace): bool
    {
        if ($workspace['key'] === 'coach') {
            return TrainerCockpitController::userCanView($user);
        }

        if ($workspace['key'] === 'club') {
            return $user->hasAnyRole(array_merge(Roles::FULL_ACCESS, Roles::CLUB_ADMIN))
                || $user->can('org.manage')
                || tap($user->clubs(), fn ($query) => ClubRoles::whereAny($query, ClubRoles::ELEVATED))->exists();
        }

        if ($workspace['key'] === 'sponsor') {
            return app(SponsorWorkspaceService::class)->canOpen($user);
        }

        return collect($workspace['roles'] ?? [])->intersect($user->getRoleNames())->isNotEmpty()
            || collect($workspace['permissions'] ?? [])->contains(fn ($permission) => $user->can($permission));
    }

    private function workspaces(): array
    {
        return [
            $this->workspace('athlete', 'las la-running', route('auth.feed.index'), 'personal', ['player', 'youth_player', 'minor_player', 'guest_player', 'captain']),
            $this->workspace('coach', 'las la-chalkboard-teacher', route('auth.trainer-cockpit.index'), 'active', ['coach', 'assistant_coach', 'performance_coach', 'fitness_coach', 'team_manager', 'captain'], ['training.view', 'event.create', 'teams.manage_players']),
            $this->workspace('club', 'las la-building', route('auth.club-cockpit.index'), 'active', ['club_owner', 'club_admin', 'club_manager', 'academy_manager', 'financial_controller'], ['org.manage', 'billing.manage']),
            $this->workspace('sponsor', 'las la-handshake', route('auth.sponsor-workspace.index'), 'active', ['sponsor', 'sponsor_manager'], ['sponsor.workspace.view', 'sponsors.view']),
            $this->workspace('guardian', 'las la-user-shield', route('guardian-access.children'), 'foundation_available', ['parent', 'guardian'], ['guardians.children.view']),
            $this->workspace('analytics', 'las la-chart-bar', route('admin.product-analytics.index'), 'active', ['data_analyst', 'performance_coach'], ['analytics.view', 'performance.view', 'gps.data.view']),
            $this->workspace('medical', 'las la-heartbeat', route('auth.training.index'), 'training_active', ['physiotherapist'], ['medical.records.view', 'injuries.edit', 'recovery.plan.edit']),
            $this->workspace('media', 'las la-photo-video', route('auth.feed.index'), 'foundation_available', ['media_manager', 'redaktor'], ['content.create', 'media.upload', 'blog.view']),
            $this->workspace('support', 'las la-headset', route('auth.support.index'), 'active', ['support'], ['support.tickets']),
        ];
    }

    private function workspace(
        string $key,
        string $icon,
        string $href,
        string $status,
        array $roles,
        array $permissions = [],
    ): array {
        return [
            'key' => $key,
            'title' => __("workspace.items.{$key}.title"),
            'description' => __("workspace.items.{$key}.description"),
            'icon' => $icon,
            'href' => $href,
            'status' => __("workspace.status.{$status}"),
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }
}
