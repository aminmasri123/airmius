<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembershipProspect;
use App\Models\ClubMembershipType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMembershipProspectTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_manage_a_prospect_and_trial_without_creating_a_membership(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $type = ClubMembershipType::query()->create([
            'club_id' => $club->id,
            'name' => 'Aktiv',
            'is_active' => true,
            'is_public' => true,
        ]);
        Sanctum::actingAs($owner);

        $created = $this->postJson("/api/v1/clubs/{$club->id}/membership-prospects", [
            'name' => '  Ada Interessiert  ',
            'email' => 'ADA.PROSPECT@EXAMPLE.TEST',
            'phone' => '+49 123 456',
            'status' => 'trial_scheduled',
            'source' => 'Vereinswebsite',
            'trial_at' => '2026-10-05T16:30:00+02:00',
            'team_id' => $team->id,
            'club_membership_type_id' => $type->id,
            'notes' => 'Interne Vorbereitung',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Ada Interessiert')
            ->assertJsonPath('data.email', 'ada.prospect@example.test')
            ->assertJsonPath('data.status', 'trial_scheduled')
            ->assertJsonPath('data.team.id', $team->id);
        $prospectId = $created->json('data.id');

        $this->assertSame(1, $club->users()->count());
        $this->assertDatabaseMissing('users', ['email' => 'ada.prospect@example.test']);
        $this->getJson("/api/v1/clubs/{$club->id}/membership-prospects?status=trial_scheduled")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $prospectId);

        $this->putJson("/api/v1/clubs/{$club->id}/membership-prospects/{$prospectId}", [
            'name' => 'Ada Interessiert',
            'email' => 'ada.prospect@example.test',
            'status' => 'trial_completed',
            'trial_at' => '2026-10-05T16:30:00+02:00',
            'trial_outcome' => 'interested',
            'team_id' => $team->id,
            'club_membership_type_id' => $type->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'trial_completed')
            ->assertJsonPath('data.trial_outcome', 'interested');

        $this->postJson("/api/v1/clubs/{$club->id}/membership-prospects/{$prospectId}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'type' => 'club.membership_prospect.archived',
            'subject_id' => $prospectId,
        ]);
    }

    public function test_prospect_is_linked_to_an_application_and_converted_only_after_approval(): void
    {
        $owner = User::factory()->create();
        $applicant = User::factory()->create(['email' => 'future.member@example.test']);
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'membership_requests_enabled' => true,
            'membership_application_fields' => [],
            'membership_application_documents' => [],
        ]);
        $prospect = ClubMembershipProspect::query()->create([
            'club_id' => $club->id,
            'name' => 'Future Member',
            'email' => 'future.member@example.test',
            'status' => 'trial_completed',
            'trial_at' => now()->subDay(),
            'trial_outcome' => 'interested',
            'created_by' => $owner->id,
        ]);

        Sanctum::actingAs($applicant);
        $response = $this->postJson("/api/v1/clubs/{$club->id}/membership-requests", [
            'type' => 'membership',
            'application_data' => [
                'street' => 'Testweg',
                'house_number' => '4',
                'postal_code' => '10115',
                'city' => 'Berlin',
            ],
            'accepted_documents' => [],
        ])->assertCreated();
        $requestId = $response->json('data.id');

        $prospect->refresh();
        $this->assertSame('application', $prospect->status);
        $this->assertSame('application', $prospect->trial_outcome);
        $this->assertSame($applicant->id, $prospect->user_id);
        $this->assertSame($requestId, $prospect->club_membership_request_id);
        $this->assertNull($prospect->converted_at);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-requests/{$requestId}/approve")
            ->assertOk();

        $prospect->refresh();
        $this->assertSame('converted', $prospect->status);
        $this->assertSame('converted', $prospect->trial_outcome);
        $this->assertNotNull($prospect->converted_at);
        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $applicant->id,
            'membership_status' => 'active',
        ]);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'type' => 'club.membership_prospect.converted',
            'subject_id' => $prospect->id,
        ]);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-prospects/{$prospect->id}/archive")
            ->assertUnprocessable();
        $this->putJson("/api/v1/clubs/{$club->id}/membership-prospects/{$prospect->id}", [
            'name' => 'Changed after conversion',
            'status' => 'prospect',
        ])->assertUnprocessable();
    }

    public function test_prospect_endpoints_enforce_permissions_and_club_scoped_relations(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $otherClub = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $foreignTeam = Team::factory()->create(['club_id' => $otherClub->id]);

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/clubs/{$club->id}/membership-prospects")->assertForbidden();
        $this->postJson("/api/v1/clubs/{$club->id}/membership-prospects", [
            'name' => 'Unauthorized',
            'status' => 'prospect',
        ])->assertForbidden();

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/membership-prospects", [
            'name' => 'Manual conversion',
            'status' => 'converted',
        ])->assertJsonValidationErrors('status');
        $this->postJson("/api/v1/clubs/{$club->id}/membership-prospects", [
            'name' => 'Wrong team',
            'status' => 'trial_scheduled',
            'team_id' => $foreignTeam->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('team_id');

        $foreignProspect = ClubMembershipProspect::query()->create([
            'club_id' => $otherClub->id,
            'name' => 'Foreign',
            'status' => 'prospect',
        ]);
        $this->putJson("/api/v1/clubs/{$club->id}/membership-prospects/{$foreignProspect->id}", [
            'name' => 'Foreign',
            'status' => 'prospect',
        ])->assertNotFound();
    }
}
