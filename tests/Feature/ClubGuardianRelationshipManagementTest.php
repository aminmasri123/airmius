<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\GuardianChildRelationship;
use App\Models\User;
use App\Services\GuardianChildRelationshipService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubGuardianRelationshipManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_api_manages_multiple_guardians_with_clear_statuses(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        [$club, $owner, $child] = $this->clubWithChild();
        $acceptedGuardian = $this->guardian('accepted-parent@example.test');
        $club->users()->attach($acceptedGuardian->id, ['role' => 'member']);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/members/{$child->id}/guardians", [
            'guardian_user_id' => $acceptedGuardian->id,
            'relationship_type' => 'mother',
            'primary' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', GuardianChildRelationship::STATUS_ACCEPTED)
            ->assertJsonPath('data.status_label', 'Aktiv')
            ->assertJsonPath('data.is_primary', true);

        $this->postJson("/api/v1/clubs/{$club->id}/members/{$child->id}/guardians", [
            'guardian_email' => 'invited-parent@example.test',
            'relationship_type' => 'father',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', GuardianChildRelationship::STATUS_INVITED)
            ->assertJsonPath('data.status_label', 'Einladung offen')
            ->assertJsonPath('data.guardian', null);

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$child->id}/guardians")
            ->assertOk()
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.summary.accepted', 1)
            ->assertJsonPath('data.summary.invited', 1)
            ->assertJsonPath('data.relationships.0.status_description', 'Dieses Konto ist aktiv verknüpft und als Hauptkontakt hinterlegt.')
            ->assertJsonPath('data.relationships.1.status_description', 'Die Einladung wurde erfasst, aber noch nicht durch ein eigenes Konto angenommen.');
    }

    public function test_guardian_account_accepts_own_open_invitation_without_merging_other_guardians(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        [$club, $owner, $child] = $this->clubWithChild();
        $primaryGuardian = $this->guardian('primary-parent@example.test');
        $invitedGuardian = $this->guardian('invited-parent@example.test');
        $club->users()->attach($primaryGuardian->id, ['role' => 'member']);
        $club->users()->attach($invitedGuardian->id, ['role' => 'member']);

        $service = app(GuardianChildRelationshipService::class);
        $primary = $service->invite($club, $child, $primaryGuardian, actor: $owner, primary: true);
        $invitation = $service->invite($club, $child, null, 'invited-parent@example.test', $owner, 'guardian');

        Sanctum::actingAs($invitedGuardian);

        $this->getJson('/api/v1/guardian/invitations')
            ->assertOk()
            ->assertJsonPath('data.summary.open', 1)
            ->assertJsonPath('data.invitations.0.id', $invitation->id)
            ->assertJsonPath('data.invitations.0.status_label', 'Einladung offen')
            ->assertJsonPath('data.invitations.0.can_accept', true);

        $this->postJson("/api/v1/guardian/invitations/{$invitation->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', GuardianChildRelationship::STATUS_ACCEPTED)
            ->assertJsonPath('data.can_accept', false);

        $this->assertDatabaseHas('guardian_child_relationships', [
            'id' => $primary->id,
            'guardian_user_id' => $primaryGuardian->id,
            'is_primary' => true,
            'status' => GuardianChildRelationship::STATUS_ACCEPTED,
        ]);
        $this->assertDatabaseHas('guardian_child_relationships', [
            'id' => $invitation->id,
            'guardian_user_id' => $invitedGuardian->id,
            'is_primary' => false,
            'status' => GuardianChildRelationship::STATUS_ACCEPTED,
        ]);
    }

    public function test_roles_and_club_boundaries_are_enforced(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        [$club, $owner, $child] = $this->clubWithChild();
        [$otherClub, $otherOwner, $otherChild] = $this->clubWithChild();
        $member = User::factory()->create(['birth_date' => now()->subYears(28)->toDateString()]);
        $club->users()->attach($member->id, ['role' => 'member']);
        $foreignGuardian = $this->guardian('foreign-parent@example.test');
        $otherClub->users()->attach($foreignGuardian->id, ['role' => 'member']);

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}/members/{$child->id}/guardians")
            ->assertForbidden();

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/clubs/{$club->id}/members/{$otherChild->id}/guardians")
            ->assertNotFound();

        $this->postJson("/api/v1/clubs/{$club->id}/members/{$child->id}/guardians", [
            'guardian_user_id' => $foreignGuardian->id,
        ])
            ->assertJsonValidationErrors('guardian_user_id');

        Sanctum::actingAs($foreignGuardian);
        $relationship = app(GuardianChildRelationshipService::class)
            ->invite($otherClub, $otherChild, null, 'foreign-parent@example.test', $otherOwner);

        $this->postJson("/api/v1/guardian/invitations/{$relationship->id}/decline")
            ->assertOk()
            ->assertJsonPath('data.status', GuardianChildRelationship::STATUS_DECLINED);
    }

    public function test_web_routes_use_the_same_guardian_management_contract(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        [$club, $owner, $child] = $this->clubWithChild();

        $this->actingAs($owner)
            ->post(route('auth.clubs.members.guardians.store', [$club, $child]), [
                'guardian_email' => 'web-parent@example.test',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status_label', 'Einladung offen');

        $this->actingAs($owner)
            ->get(route('auth.clubs.members.guardians.index', [$club, $child]))
            ->assertOk()
            ->assertJsonPath('data.summary.invited', 1);
    }

    /** @return array{Club, User, User} */
    private function clubWithChild(): array
    {
        $owner = User::factory()->create(['birth_date' => now()->subYears(40)->toDateString()]);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $child = User::factory()->create(['birth_date' => now()->subYears(12)->toDateString()]);
        $child->assignRole('minor_pending_consent');
        $club->users()->attach($child->id, ['role' => 'player']);

        return [$club, $owner, $child];
    }

    private function guardian(string $email): User
    {
        $guardian = User::factory()->create([
            'email' => $email,
            'birth_date' => now()->subYears(35)->toDateString(),
        ]);
        $guardian->assignRole('guardian');

        return $guardian;
    }
}
