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
            ['key' => 'athlete', 'title' => 'Mein Sport', 'description' => 'Feed, Training, Events, Ernährung, Sportkarte und passende Sportpartner.', 'icon' => 'las la-running', 'href' => route('auth.feed.index'), 'status' => 'Persönlich', 'roles' => ['player', 'youth_player', 'minor_player', 'guest_player', 'captain'], 'permissions' => []],
            ['key' => 'coach', 'title' => 'Trainer-Arbeitsbereich', 'description' => 'Heutige Aufgaben, Teams, Trainingsplanung, Anwesenheit und Feedback.', 'icon' => 'las la-chalkboard-teacher', 'href' => route('auth.trainer-cockpit.index'), 'status' => 'Aktiv', 'roles' => ['coach', 'assistant_coach', 'performance_coach', 'fitness_coach', 'team_manager', 'captain'], 'permissions' => ['training.view', 'event.create', 'teams.manage_players']],
            ['key' => 'club', 'title' => 'Vereinsverwaltung', 'description' => 'Mitglieder, Teams, Rechnungen, Sponsoren und Vereinskommunikation.', 'icon' => 'las la-building', 'href' => route('auth.club-cockpit.index'), 'status' => 'Aktiv', 'roles' => ['club_owner', 'club_admin', 'club_manager', 'academy_manager', 'financial_controller'], 'permissions' => ['org.manage', 'billing.manage']],
            ['key' => 'sponsor', 'title' => 'Sponsor-Cockpit', 'description' => 'Partnerschaften, Kampagnen, Sichtbarkeit, Angebote und Wirkung zentral steuern.', 'icon' => 'las la-handshake', 'href' => route('auth.sponsor-workspace.index'), 'status' => 'Aktiv', 'roles' => ['sponsor', 'sponsor_manager'], 'permissions' => ['sponsor.workspace.view', 'sponsors.view']],
            ['key' => 'guardian', 'title' => 'Eltern & Guardian', 'description' => 'Kinderprofile, Zustimmung, Sicherheit und Einblick in relevante Vereinsdaten.', 'icon' => 'las la-user-shield', 'href' => route('guardian-access.children'), 'status' => 'Basis vorhanden', 'roles' => ['parent', 'guardian'], 'permissions' => ['guardians.children.view']],
            ['key' => 'analytics', 'title' => 'Analyse & Performance', 'description' => 'Leistungsdaten, Training, Feedback und Entwicklungsberichte.', 'icon' => 'las la-chart-bar', 'href' => route('auth.training.index'), 'status' => 'Training aktiv', 'roles' => ['data_analyst', 'performance_coach'], 'permissions' => ['analytics.view', 'performance.view', 'gps.data.view']],
            ['key' => 'medical', 'title' => 'Medical & Recovery', 'description' => 'Belastung, Reha-Hinweise, Freigaben und Feedback im Trainingskontext.', 'icon' => 'las la-heartbeat', 'href' => route('auth.training.index'), 'status' => 'Training aktiv', 'roles' => ['physiotherapist'], 'permissions' => ['medical.records.view', 'injuries.edit', 'recovery.plan.edit']],
            ['key' => 'media', 'title' => 'Media & Content', 'description' => 'Beiträge, Medien, SEO, Vereinsnews und öffentliche Kommunikation.', 'icon' => 'las la-photo-video', 'href' => route('auth.feed.index'), 'status' => 'Basis vorhanden', 'roles' => ['media_manager', 'redaktor'], 'permissions' => ['content.create', 'media.upload', 'blog.view']],
            ['key' => 'support', 'title' => 'Support', 'description' => 'Nutzerhilfe, Benachrichtigungen und Eskalationen.', 'icon' => 'las la-headset', 'href' => route('auth.notifications.index'), 'status' => 'Benachrichtigungen aktiv', 'roles' => ['support'], 'permissions' => ['support.tickets']],
        ];
    }
}
