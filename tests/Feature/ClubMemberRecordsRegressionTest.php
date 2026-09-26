<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubDepartment;
use App\Models\ClubExternalMember;
use App\Models\ClubMembershipType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMemberRecordsRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_record_account_and_multiple_team_participations_stay_separate(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create([
            'phone' => '+49 30 123456',
            'city' => 'Berlin',
        ]);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $passive = $this->membershipType($club, 'Passivmitgliedschaft', 'passive');
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'paused',
            'club_membership_type_id' => $passive->id,
            'member_number' => 'M-2042',
            'joined_on' => '2020-01-01',
            'membership_ends_on' => '2027-12-31',
        ]);
        $firstDepartment = ClubDepartment::query()->create([
            'club_id' => $club->id,
            'name' => 'Breitensport',
            'is_public' => true,
        ]);
        $secondDepartment = ClubDepartment::query()->create([
            'club_id' => $club->id,
            'name' => 'Leistungssport',
            'is_public' => true,
        ]);
        $firstTeam = Team::factory()->create([
            'club_id' => $club->id,
            'club_department_id' => $firstDepartment->id,
        ]);
        $secondTeam = Team::factory()->create([
            'club_id' => $club->id,
            'club_department_id' => $secondDepartment->id,
        ]);
        $firstTeam->users()->attach($member->id, ['role' => 'player']);
        $secondTeam->users()->attach($member->id, ['role' => 'guest']);

        Sanctum::actingAs($owner);
        $response = $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.management.members');

        $memberPayload = collect($response->json('data.management.members'))->firstWhere('id', $member->id);
        $this->assertSame('+49 30 123456', $memberPayload['phone']);
        $this->assertSame('Berlin', $memberPayload['city']);
        $this->assertSame($passive->id, $memberPayload['membership']['club_membership_type_id']);
        $this->assertSame('M-2042', $memberPayload['membership']['member_number']);
        $this->assertSame('paused', $memberPayload['membership']['status']);

        $this->assertCount(2, $member->teams()->where('teams.club_id', $club->id)->get());
        $this->assertEqualsCanonicalizing(
            [$firstDepartment->id, $secondDepartment->id],
            $member->teams()->pluck('club_department_id')->all(),
        );
        $this->assertDatabaseCount('club_user', 2);
        $this->assertSame($member->id, $memberPayload['id']);
    }

    public function test_external_person_has_full_contact_record_and_same_membership_types_without_an_account(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        foreach ([
            ['Aktivmitgliedschaft', 'active'],
            ['Passivmitgliedschaft', 'passive'],
            ['Fördermitgliedschaft', 'supporting'],
            ['Befristete Mitgliedschaft', 'fixed-term'],
        ] as [$name, $slug]) {
            $this->membershipType($club, $name, $slug);
        }
        $honorary = $this->membershipType($club, 'Ehrenmitgliedschaft', 'honorary');

        Sanctum::actingAs($owner);
        $created = $this->postJson("/api/v1/clubs/{$club->id}/members/invite", [
            'name' => 'Erika Beispiel',
            'email' => 'erika@example.test',
            'phone' => '+49 40 987654',
            'country' => 'de',
            'street' => 'Vereinsweg',
            'house_number' => '7a',
            'postal_code' => '20095',
            'city' => 'Hamburg',
            'membership_status' => 'active',
            'club_membership_type_id' => $honorary->id,
            'member_number' => 'E-25',
            'send_invitation' => false,
        ])->assertCreated();

        $externalId = $created->json('data.external_members.0.id');
        $this->assertNotNull($externalId);
        $created->assertJsonPath('data.external_members.0.membership_type.name', 'Ehrenmitgliedschaft')
            ->assertJsonPath('data.external_members.0.phone', '+49 40 987654')
            ->assertJsonPath('data.external_members.0.country', 'DE');
        $this->assertDatabaseMissing('users', ['email' => 'erika@example.test']);

        $this->putJson("/api/v1/clubs/{$club->id}/external-members/{$externalId}", [
            'name' => 'Erika Beispiel',
            'email' => 'erika@example.test',
            'phone' => '+49 40 111111',
            'country' => 'DE',
            'street' => 'Vereinsweg',
            'house_number' => '7a',
            'postal_code' => '20095',
            'city' => 'Hamburg',
            'role' => 'member',
            'membership_status' => 'former',
            'club_membership_type_id' => $honorary->id,
            'member_number' => 'E-25',
            'contribution_interval' => 'none',
            'sepa_mandate_active' => false,
            'joined_on' => '2001-05-01',
            'membership_ends_on' => '2026-09-30',
        ])->assertOk()
            ->assertJsonPath('data.external_members.0.phone', '+49 40 111111')
            ->assertJsonPath('data.external_members.0.membership_status', 'former');

        $external = ClubExternalMember::query()->findOrFail($externalId);
        $this->assertSame($honorary->id, $external->club_membership_type_id);
        $this->assertSame('2001-05-01', $external->joined_on?->toDateString());
        $this->assertSame('2026-09-30', $external->membership_ends_on?->toDateString());
        $audit = Activity::query()->where('type', 'club.member.external_updated')->sole();
        $this->assertContains('phone', $audit->data['changed_fields']);
        $this->assertContains('membership_status', $audit->data['changed_fields']);
        $this->assertArrayNotHasKey('sepa_iban', $audit->data);

        $foreignType = $this->membershipType(
            Club::factory()->create(['owner_id' => $owner->id]),
            'Fremder Typ',
            'foreign',
        );
        $this->putJson("/api/v1/clubs/{$club->id}/external-members/{$externalId}", [
            'email' => 'erika@example.test',
            'membership_status' => 'former',
            'club_membership_type_id' => $foreignType->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('club_membership_type_id');
    }

    private function membershipType(Club $club, string $name, string $slug): ClubMembershipType
    {
        return ClubMembershipType::query()->create([
            'club_id' => $club->id,
            'name' => $name,
            'slug' => $slug,
            'is_public' => true,
            'is_active' => true,
        ]);
    }
}
