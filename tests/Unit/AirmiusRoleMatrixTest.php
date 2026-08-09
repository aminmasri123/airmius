<?php

namespace Tests\Unit;

use App\Support\AirmiusRoleMatrix;
use App\Support\ClubRoles;
use App\Support\Roles;
use App\Support\TeamRoles;
use PHPUnit\Framework\TestCase;

class AirmiusRoleMatrixTest extends TestCase
{
    public function test_matrix_contains_required_mvp_personas(): void
    {
        $this->assertSame([
            AirmiusRoleMatrix::SPORTLER,
            AirmiusRoleMatrix::TRAINER,
            AirmiusRoleMatrix::VEREIN_ADMIN,
            AirmiusRoleMatrix::ELTERNTEIL,
            AirmiusRoleMatrix::SPONSOR,
            AirmiusRoleMatrix::PLATTFORM_ADMIN,
        ], AirmiusRoleMatrix::keys());
    }

    public function test_matrix_references_existing_role_sources(): void
    {
        $platformRoles = array_unique(Roles::all());
        $clubRoles = ClubRoles::ALL;
        $teamRoles = TeamRoles::all();

        foreach (AirmiusRoleMatrix::all() as $entry) {
            $this->assertNotEmpty($entry['label']);
            $this->assertNotEmpty($entry['capabilities']);

            foreach ($entry['platform_roles'] as $role) {
                $this->assertContains($role, $platformRoles, "Unknown platform role [{$role}] in {$entry['key']}");
            }

            foreach ($entry['club_roles'] as $role) {
                $this->assertContains($role, $clubRoles, "Unknown club role [{$role}] in {$entry['key']}");
            }

            foreach ($entry['team_roles'] as $role) {
                $this->assertContains($role, $teamRoles, "Unknown team role [{$role}] in {$entry['key']}");
            }
        }
    }

    public function test_platform_admin_includes_full_access_roles(): void
    {
        $platformAdmin = AirmiusRoleMatrix::get(AirmiusRoleMatrix::PLATTFORM_ADMIN);

        foreach (Roles::FULL_ACCESS as $role) {
            $this->assertContains($role, $platformAdmin['platform_roles']);
        }
    }

    public function test_sponsor_persona_is_explicit_and_does_not_inherit_platform_admin_scope(): void
    {
        $sponsor = AirmiusRoleMatrix::get(AirmiusRoleMatrix::SPONSOR);

        $this->assertSame('sponsor', $sponsor['scope']);
        $this->assertSame(['sponsor', 'sponsor_manager'], $sponsor['platform_roles']);
        $this->assertContains('sponsor.workspace.view', $sponsor['capabilities']);
        $this->assertContains('sponsor.outcomes.view', $sponsor['capabilities']);

        foreach (Roles::FULL_ACCESS as $role) {
            $this->assertNotContains($role, $sponsor['platform_roles']);
        }
    }
}
