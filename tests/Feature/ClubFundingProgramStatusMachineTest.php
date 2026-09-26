<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubFundingProgram;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\ClubYearPeriod;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubFundingProgramStatusMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_roles_manage_funding_program_lifecycle_from_deadline_to_payout(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $approver = User::factory()->create();
        $responsible = User::factory()->create(['name' => 'Grant Lead']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        foreach ([$editor, $approver, $responsible] as $member) {
            $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        }
        $this->assignRole($club, $editor, [ClubPermissions::FINANCE_VIEW, ClubPermissions::FINANCE_EDIT], $owner);
        $this->assignRole($club, $approver, [ClubPermissions::FINANCE_VIEW, ClubPermissions::FINANCE_APPROVE], $owner);
        $period = $this->businessPeriod($club);

        Sanctum::actingAs($editor);
        $programId = $this->postJson("/api/v1/clubs/{$club->id}/funding-programs", [
            'club_year_period_id' => $period->id,
            'responsible_user_id' => $responsible->id,
            'program_name' => 'Sportstättenförderung',
            'provider_name' => 'Landessportbund',
            'status' => 'ready',
            'deadline_on' => '2026-10-15',
            'requested_amount_cents' => 250000,
            'own_contribution_cents' => 50000,
            'contact_snapshot' => [
                'name' => 'Sensitive Contact',
                'email' => 'grant@example.test',
                'role' => 'Sachbearbeitung',
            ],
            'application_snapshot' => [
                'reference' => 'LSB-2026-77',
                'channel' => 'portal',
                'requirements' => ['Kostenplan', 'Beschluss'],
            ],
            'internal_note' => 'Contains sensitive application details',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.responsible_name', 'Grant Lead')
            ->assertJsonPath('data.allowed_transitions.1', 'submitted')
            ->json('data.id');

        $this->putJson("/api/v1/clubs/{$club->id}/funding-programs/{$programId}/status", ['status' => 'submitted'])
            ->assertForbidden();

        Sanctum::actingAs($approver);
        $this->putJson("/api/v1/clubs/{$club->id}/funding-programs/{$programId}/status", ['status' => 'submitted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.submitted_on', now()->toDateString());

        $this->putJson("/api/v1/clubs/{$club->id}/funding-programs/{$programId}/status", [
            'status' => 'approved',
            'approved_amount_cents' => 220000,
            'approved_on' => '2026-10-20',
        ])->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_amount_cents', 220000);

        $this->putJson("/api/v1/clubs/{$club->id}/funding-programs/{$programId}/status", [
            'status' => 'own_contribution_secured',
            'own_contribution_cents' => 55000,
        ])->assertOk();

        $this->putJson("/api/v1/clubs/{$club->id}/funding-programs/{$programId}/status", [
            'status' => 'paid_out',
            'paid_out_amount_cents' => 220000,
            'paid_out_on' => '2026-11-10',
        ])->assertOk()
            ->assertJsonPath('data.status', 'paid_out')
            ->assertJsonPath('data.paid_out_amount_cents', 220000);

        $activity = Activity::query()->where('type', 'club.funding_program.created')->firstOrFail();
        $this->assertSame('funding_program', $activity->data['entity_type']);
        $this->assertSame(250000, $activity->data['requested_amount_cents']);
        $this->assertArrayNotHasKey('program_name', $activity->data);
        $this->assertArrayNotHasKey('contact_snapshot', $activity->data);
        $this->assertArrayNotHasKey('application_snapshot', $activity->data);
        $this->assertArrayNotHasKey('internal_note', $activity->data);
    }

    public function test_funding_program_rejects_invalid_transitions_and_foreign_club_data(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $foreignClub = Club::factory()->create(['owner_id' => User::factory()]);
        $period = $this->businessPeriod($club);
        $foreignPeriod = $this->businessPeriod($foreignClub);
        $foreignResponsible = User::factory()->create();
        $foreignClub->users()->attach($foreignResponsible->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/funding-programs", $this->payload([
            'club_year_period_id' => $foreignPeriod->id,
        ]))->assertUnprocessable();

        $this->postJson("/api/v1/clubs/{$club->id}/funding-programs", $this->payload([
            'club_year_period_id' => $period->id,
            'responsible_user_id' => $foreignResponsible->id,
        ]))->assertUnprocessable();

        $programId = $this->postJson("/api/v1/clubs/{$club->id}/funding-programs", $this->payload([
            'club_year_period_id' => $period->id,
            'status' => 'ready',
        ]))->assertCreated()->json('data.id');

        $this->putJson("/api/v1/clubs/{$club->id}/funding-programs/{$programId}/status", ['status' => 'paid_out'])
            ->assertUnprocessable();

        $foreignProgram = ClubFundingProgram::query()->create($this->payload([
            'club_id' => $foreignClub->id,
            'program_name' => 'Foreign',
            'provider_name' => 'Foreign Provider',
        ]));

        $this->putJson("/api/v1/clubs/{$club->id}/funding-programs/{$foreignProgram->id}/status", ['status' => 'submitted'])
            ->assertNotFound();
    }

    public function test_finance_viewer_can_list_but_not_change_funding_programs(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($viewer->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $this->assignRole($club, $viewer, [ClubPermissions::FINANCE_VIEW], $owner);

        ClubFundingProgram::query()->create($this->payload([
            'club_id' => $club->id,
            'program_name' => 'Integration durch Sport',
            'provider_name' => 'Kommune',
        ]));

        Sanctum::actingAs($viewer);
        $this->getJson("/api/v1/clubs/{$club->id}/funding-programs")
            ->assertOk()
            ->assertJsonCount(1, 'data.programs')
            ->assertJsonPath('data.can_manage', false)
            ->assertJsonPath('data.can_approve', false);

        $this->postJson("/api/v1/clubs/{$club->id}/funding-programs", $this->payload())
            ->assertForbidden();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'program_name' => 'Förderprogramm',
            'provider_name' => 'Förderstelle',
            'status' => 'draft',
            'requested_amount_cents' => 100000,
        ], $overrides);
    }

    private function businessPeriod(Club $club): ClubYearPeriod
    {
        return ClubYearPeriod::query()->create([
            'club_id' => $club->id,
            'type' => 'business',
            'name' => 'Geschäftsjahr '.$club->id,
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
    }

    private function assignRole(Club $club, User $member, array $permissions, User $owner): void
    {
        static $roleIndex = 0;
        $roleIndex++;

        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'funding_'.$roleIndex,
            'name' => 'Funding role',
            'permissions' => $permissions,
            'is_active' => true,
        ]);

        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $member->id,
            'scope_type' => 'club',
            'scope_id' => null,
            'scope_key' => 'club',
            'assigned_by' => $owner->id,
        ]);
    }
}
