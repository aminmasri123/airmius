<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubMemberQualification;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMemberQualificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_admin_manages_minimal_qualification_records_with_review_and_reminder_status(): void
    {
        [$owner, $club, $member] = $this->clubWithMember();
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/member-qualifications", [
            'user_id' => $member->id,
            'type' => 'license',
            'title' => 'Trainer C',
            'issuer' => 'Landessportbund',
            'license_number' => 'LSB-42',
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
            'proof_status' => 'verified',
            'remind_on' => '2026-11-30',
            'visibility' => 'membership_admins',
            'is_sensitive' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.license_number', 'LSB-42')
            ->assertJsonPath('data.proof_status', 'verified')
            ->assertJsonPath('data.proof_checked_by', $owner->id)
            ->assertJsonPath('data.is_due_for_reminder', false);

        $qualification = ClubMemberQualification::query()->firstOrFail();

        $this->assertDatabaseHas('club_member_qualifications', [
            'club_id' => $club->id,
            'user_id' => $member->id,
            'title' => 'Trainer C',
            'proof_status' => 'verified',
            'license_number' => 'LSB-42',
        ]);
        $this->assertNotNull($qualification->proof_checked_at);

        $this->putJson("/api/v1/clubs/{$club->id}/member-qualifications/{$qualification->id}", [
            'user_id' => $member->id,
            'type' => 'training',
            'title' => 'Kinderschutz-Fortbildung',
            'issuer' => 'Verein',
            'license_number' => null,
            'valid_from' => '2025-01-01',
            'valid_until' => '2025-12-31',
            'proof_status' => 'missing',
            'remind_on' => '2025-11-01',
            'visibility' => 'member',
            'is_sensitive' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_expired', true)
            ->assertJsonPath('data.is_due_for_reminder', true)
            ->assertJsonPath('data.proof_checked_by', null);

        $audit = Activity::query()->where('type', 'club.member_qualification.updated')->latest('id')->firstOrFail();
        $this->assertSame($owner->id, $audit->user_id);
        $this->assertSame($qualification->id, $audit->subject_id);
        $this->assertSame('missing', $audit->data['proof_status']);
    }

    public function test_club_boundaries_are_enforced_for_members_external_members_and_records(): void
    {
        [$owner, $club, $member] = $this->clubWithMember();
        [$otherOwner, $otherClub, $otherMember] = $this->clubWithMember();
        $externalMember = ClubExternalMember::query()->create([
            'club_id' => $otherClub->id,
            'email' => 'external@example.test',
            'name' => 'External Member',
            'membership_status' => 'active',
        ]);
        $qualification = ClubMemberQualification::query()->create([
            'club_id' => $otherClub->id,
            'user_id' => $otherMember->id,
            'created_by' => $otherOwner->id,
            'type' => 'license',
            'title' => 'Other Club License',
            'proof_status' => 'submitted',
            'visibility' => 'membership_admins',
        ]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/member-qualifications", [
            'user_id' => $otherMember->id,
            'type' => 'license',
            'title' => 'Wrong Club User',
            'proof_status' => 'missing',
            'visibility' => 'membership_admins',
        ])->assertStatus(422);

        $this->postJson("/api/v1/clubs/{$club->id}/member-qualifications", [
            'external_member_id' => $externalMember->id,
            'type' => 'license',
            'title' => 'Wrong Club External',
            'proof_status' => 'missing',
            'visibility' => 'membership_admins',
        ])->assertStatus(422);

        $this->putJson("/api/v1/clubs/{$club->id}/member-qualifications/{$qualification->id}", [
            'user_id' => $member->id,
            'type' => 'license',
            'title' => 'Hijack',
            'proof_status' => 'verified',
            'visibility' => 'membership_admins',
        ])->assertNotFound();
    }

    public function test_regular_member_only_sees_own_non_sensitive_member_visible_minimal_payload(): void
    {
        [$owner, $club, $member] = $this->clubWithMember();
        $other = User::factory()->create();
        $club->users()->attach($other->id, ['role' => 'member', 'membership_status' => 'active']);

        ClubMemberQualification::query()->create([
            'club_id' => $club->id,
            'user_id' => $member->id,
            'created_by' => $owner->id,
            'type' => 'license',
            'title' => 'Visible License',
            'license_number' => 'SECRET-LIC',
            'proof_status' => 'verified',
            'valid_until' => '2026-12-31',
            'visibility' => 'member',
            'is_sensitive' => false,
        ]);
        ClubMemberQualification::query()->create([
            'club_id' => $club->id,
            'user_id' => $member->id,
            'created_by' => $owner->id,
            'type' => 'certificate',
            'title' => 'Sensitive Certificate',
            'license_number' => 'SENSITIVE',
            'proof_status' => 'submitted',
            'visibility' => 'member',
            'is_sensitive' => true,
        ]);
        ClubMemberQualification::query()->create([
            'club_id' => $club->id,
            'user_id' => $other->id,
            'created_by' => $owner->id,
            'type' => 'training',
            'title' => 'Other Member Training',
            'proof_status' => 'verified',
            'visibility' => 'member',
            'is_sensitive' => false,
        ]);

        Sanctum::actingAs($member);
        $response = $this->getJson("/api/v1/clubs/{$club->id}/member-qualifications")
            ->assertOk()
            ->assertJsonPath('data.can_manage', false)
            ->assertJsonCount(1, 'data.qualifications')
            ->assertJsonPath('data.qualifications.0.title', 'Visible License');

        $this->assertStringNotContainsString('SECRET-LIC', $response->getContent());
        $this->assertStringNotContainsString('SENSITIVE', $response->getContent());
        $this->assertStringNotContainsString('Other Member Training', $response->getContent());
    }

    private function clubWithMember(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner', 'roles' => ['owner'], 'membership_status' => 'active'],
            $member->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
        ]);
        $club->users()->updateExistingPivot($owner->id, [
            'permission_overrides' => [ClubPermissions::MEMBERS_EDIT => true],
        ]);

        return [$owner, $club, $member];
    }
}
