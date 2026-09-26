<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubEmploymentEngagement;
use App\Models\ClubPersonProfile;
use App\Models\User;
use App\Support\ClubPermissions;
use App\Support\ClubWorkforceDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubWorkforcePersonModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_workforce_person_is_separate_from_membership_and_user_account(): void
    {
        $manager = User::factory()->create();
        $account = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $manager->id]);

        $club->users()->syncWithoutDetaching([
            $manager->id => ['role' => 'owner', 'roles' => ['owner'], 'membership_status' => 'active'],
            $member->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
        ]);

        $person = ClubPersonProfile::query()->create([
            'club_id' => $club->id,
            'user_id' => $account->id,
            'membership_user_id' => $member->id,
            'created_by' => $manager->id,
            'display_name' => 'Alex Trainer',
            'email' => 'alex@example.test',
            'phone' => '+491700000',
            'privacy_level' => ClubPersonProfile::PRIVACY_INTERNAL,
            'data_processing_flags' => ['contract_admin' => true],
        ]);

        $engagement = ClubEmploymentEngagement::query()->create([
            'club_id' => $club->id,
            'club_person_profile_id' => $person->id,
            'created_by' => $manager->id,
            'engagement_type' => ClubEmploymentEngagement::TYPE_TRAINER,
            'role_key' => 'head_coach',
            'status' => ClubEmploymentEngagement::STATUS_ACTIVE,
            'starts_on' => '2026-10-01',
            'qualification_requirements' => ['Trainer C'],
            'contract_terms' => ['fee_per_session' => 40],
        ]);

        $this->assertTrue($person->hasSeparateMembershipLink());
        $this->assertSame($account->id, $person->user->id);
        $this->assertSame($member->id, $person->membershipUser->id);
        $this->assertTrue($engagement->isWorkforceType());
        $this->assertSame('Trainer C', $person->engagements()->first()->qualification_requirements[0]);
    }

    public function test_directory_payload_hides_sensitive_person_data_without_club_permission(): void
    {
        $manager = User::factory()->create();
        $viewer = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $manager->id]);
        $club->users()->syncWithoutDetaching([
            $manager->id => ['role' => 'owner', 'roles' => ['owner'], 'membership_status' => 'active'],
        ]);

        $person = ClubPersonProfile::query()->create([
            'club_id' => $club->id,
            'created_by' => $manager->id,
            'display_name' => 'Mara Honorarkraft',
            'email' => 'mara@example.test',
            'phone' => '+491711111',
            'date_of_birth' => '1990-02-03',
            'privacy_level' => ClubPersonProfile::PRIVACY_INTERNAL,
            'data_processing_flags' => ['contract_admin' => true],
        ]);
        ClubEmploymentEngagement::query()->create([
            'club_id' => $club->id,
            'club_person_profile_id' => $person->id,
            'engagement_type' => ClubEmploymentEngagement::TYPE_CONTRACTOR,
            'role_key' => 'course_instructor',
            'status' => ClubEmploymentEngagement::STATUS_ACTIVE,
        ]);

        $payload = ClubWorkforceDirectory::publicPayload($person->fresh('club'), $viewer);

        $this->assertSame('Mara Honorarkraft', $payload['display_name']);
        $this->assertSame('contractor', $payload['engagements'][0]['engagement_type']);
        $this->assertArrayNotHasKey('email', $payload);
        $this->assertArrayNotHasKey('phone', $payload);
        $this->assertArrayNotHasKey('date_of_birth', $payload);
        $this->assertArrayNotHasKey('data_processing_flags', $payload);
    }

    public function test_directory_payload_respects_roles_and_club_boundaries(): void
    {
        $manager = User::factory()->create();
        $otherManager = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $manager->id]);
        $otherClub = Club::factory()->create(['owner_id' => $otherManager->id]);
        $club->users()->syncWithoutDetaching([
            $manager->id => ['role' => 'owner', 'roles' => ['owner'], 'membership_status' => 'active'],
        ]);
        $club->users()->updateExistingPivot($manager->id, [
            'permission_overrides' => [ClubPermissions::MEMBERS_VIEW => true],
        ]);

        $person = ClubPersonProfile::query()->create([
            'club_id' => $club->id,
            'created_by' => $manager->id,
            'display_name' => 'Sam Uebungsleiter',
            'email' => 'sam@example.test',
            'privacy_level' => ClubPersonProfile::PRIVACY_INTERNAL,
        ]);

        $payload = ClubWorkforceDirectory::publicPayload($person->fresh('club'), $manager);

        $this->assertSame('sam@example.test', $payload['email']);
        $this->assertSame(0, ClubPersonProfile::query()->forClub($otherClub)->count());
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        ClubWorkforceDirectory::assertSameClub($person, $otherClub);
    }
}
