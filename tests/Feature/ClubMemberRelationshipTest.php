<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubMemberRelationship;
use App\Models\User;
use App\Services\ClubMemberRelationshipService;
use App\Services\GuardianChildRelationshipService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMemberRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_stores_separate_purposes_contact_methods_and_validity(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        [$club, $owner, $member, $payer] = $this->clubWithMemberAndRelatedAccount();

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/relationships", [
            'related_user_id' => $payer->id,
            'relationship_type' => 'father',
            'purposes' => [
                ClubMemberRelationship::PURPOSE_CONTRIBUTION_PAYER,
                ClubMemberRelationship::PURPOSE_EMERGENCY_CONTACT,
            ],
            'contact_methods' => [
                ClubMemberRelationship::CONTACT_EMAIL,
                ClubMemberRelationship::CONTACT_IN_APP,
            ],
            'primary' => true,
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
            'metadata' => [
                'source' => 'membership_office',
                'phone' => '+49 221 secret',
                'note' => 'Do not audit this.',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.related_user.id', $payer->id)
            ->assertJsonPath('data.purposes.0', ClubMemberRelationship::PURPOSE_CONTRIBUTION_PAYER)
            ->assertJsonPath('data.purposes.1', ClubMemberRelationship::PURPOSE_EMERGENCY_CONTACT)
            ->assertJsonPath('data.contact_methods.1', ClubMemberRelationship::CONTACT_IN_APP)
            ->assertJsonPath('data.valid_from', '2026-01-01')
            ->assertJsonPath('data.valid_until', '2026-12-31');

        $this->assertDatabaseHas('club_member_relationships', [
            'club_id' => $club->id,
            'member_user_id' => $member->id,
            'related_user_id' => $payer->id,
            'relationship_type' => 'father',
            'status' => ClubMemberRelationship::STATUS_ACTIVE,
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'contribution_payer_user_id' => $payer->id,
        ]);

        $relationship = ClubMemberRelationship::query()->sole();
        $this->assertSame(['source' => 'membership_office'], $relationship->metadata);

        $activity = Activity::query()->where('type', 'club.member_relationship.saved')->sole();
        $this->assertArrayNotHasKey('related_email', $activity->data ?? []);
        $this->assertArrayNotHasKey('phone', $activity->data ?? []);
        $this->assertSame([ClubMemberRelationship::PURPOSE_CONTRIBUTION_PAYER, ClubMemberRelationship::PURPOSE_EMERGENCY_CONTACT], $activity->data['purposes']);
    }

    public function test_relationships_are_limited_to_club_members_and_dates_are_validated(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        [$club, $owner, $member] = $this->clubWithMember();
        $foreignUser = User::factory()->create(['birth_date' => now()->subYears(40)->toDateString()]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/relationships", [
            'related_user_id' => $foreignUser->id,
            'purposes' => [ClubMemberRelationship::PURPOSE_PICKUP_AUTHORIZED],
        ])->assertJsonValidationErrors('related_user_id');

        $this->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/relationships", [
            'related_name' => 'Aunt Example',
            'purposes' => [ClubMemberRelationship::PURPOSE_PICKUP_AUTHORIZED],
            'valid_from' => '2026-12-31',
            'valid_until' => '2026-01-01',
        ])->assertJsonValidationErrors('valid_until');

        $plainMember = User::factory()->create(['birth_date' => now()->subYears(22)->toDateString()]);
        $club->users()->attach($plainMember->id, ['role' => 'member']);

        Sanctum::actingAs($plainMember);
        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/relationships")
            ->assertForbidden();
    }

    public function test_guardian_service_mirrors_existing_contract_into_unified_relationships(): void
    {
        [$club, $child] = $this->clubChild();
        $guardian = User::factory()->create([
            'email' => 'mirror-parent@example.test',
            'birth_date' => now()->subYears(36)->toDateString(),
        ]);
        $club->users()->attach($guardian->id, ['role' => 'member']);

        $relationship = app(GuardianChildRelationshipService::class)
            ->invite($club, $child, $guardian, relationshipType: 'mother', primary: true);

        $this->assertDatabaseHas('club_member_relationships', [
            'club_id' => $club->id,
            'member_user_id' => $child->id,
            'related_user_id' => $guardian->id,
            'related_email' => 'mirror-parent@example.test',
            'relationship_type' => 'mother',
            'status' => ClubMemberRelationship::STATUS_ACTIVE,
            'is_primary' => true,
            'legacy_source' => 'guardian_child_relationships',
            'legacy_source_id' => $relationship->id,
        ]);

        $unified = ClubMemberRelationship::query()->sole();
        $this->assertSame([
            ClubMemberRelationship::PURPOSE_GUARDIAN,
            ClubMemberRelationship::PURPOSE_EMERGENCY_CONTACT,
            ClubMemberRelationship::PURPOSE_PICKUP_AUTHORIZED,
        ], $unified->purposes);
    }

    public function test_service_rejects_empty_purpose_and_self_relationship(): void
    {
        [$club, $member] = $this->clubChild();

        try {
            app(ClubMemberRelationshipService::class)->upsert($club, $member, $member, purposes: []);
            $this->fail('Invalid self relationship was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('purposes', $exception->errors());
        }
    }

    /** @return array{Club, User, User, User} */
    private function clubWithMemberAndRelatedAccount(): array
    {
        [$club, $owner, $member] = $this->clubWithMember();
        $related = User::factory()->create(['birth_date' => now()->subYears(38)->toDateString()]);
        $club->users()->attach($related->id, ['role' => 'member']);

        return [$club, $owner, $member, $related];
    }

    /** @return array{Club, User, User} */
    private function clubWithMember(): array
    {
        $owner = User::factory()->create(['birth_date' => now()->subYears(40)->toDateString()]);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create(['birth_date' => now()->subYears(16)->toDateString()]);
        $club->users()->attach($member->id, ['role' => 'player']);

        return [$club, $owner, $member];
    }

    /** @return array{Club, User} */
    private function clubChild(): array
    {
        $owner = User::factory()->create(['birth_date' => now()->subYears(36)->toDateString()]);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $child = User::factory()->create(['birth_date' => now()->subYears(12)->toDateString()]);
        $club->users()->attach($child->id, ['role' => 'player']);

        return [$club, $child];
    }
}
