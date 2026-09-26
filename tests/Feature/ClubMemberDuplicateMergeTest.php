<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubCustomFieldDefinition;
use App\Models\ClubCustomFieldValue;
use App\Models\ClubExternalMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMemberDuplicateMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_email_duplicate_is_detected_and_merge_preserves_roles_and_existing_data(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create([
            'email' => 'duplicate@example.test',
            'athlete_license_number' => null,
        ]);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, [
            'role' => 'manager',
            'roles' => ['manager'],
            'permission_overrides' => ['members.edit' => true],
            'membership_status' => 'active',
            'member_number' => 'REG-100',
            'contribution_amount' => 15,
            'contribution_interval' => 'monthly',
            'membership_notes' => 'Bestehende Notiz',
        ]);
        $external = ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Externe Dublette',
            'email' => 'DUPLICATE@example.test',
            'role' => 'admin',
            'membership_status' => 'paused',
            'family_group_key' => 'family-one',
            'member_number' => 'EXT-200',
            'athlete_license_number' => 'LIC-200',
            'contribution_amount' => 35,
            'contribution_interval' => 'yearly',
            'membership_notes' => 'Externe Notiz',
        ]);
        $definition = ClubCustomFieldDefinition::query()->create([
            'club_id' => $club->id,
            'entity_type' => 'member',
            'key' => 'emergency_note',
            'label' => 'Notfallhinweis',
            'field_type' => 'text',
            'is_active' => true,
        ]);
        ClubCustomFieldValue::query()->create([
            'club_id' => $club->id,
            'club_custom_field_definition_id' => $definition->id,
            'subject_type' => 'external_member',
            'subject_id' => $external->id,
            'payload' => ['value' => 'Asthma'],
            'updated_by' => $owner->id,
        ]);
        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.management.external_members.0.duplicate_candidate.user_id', $member->id)
            ->assertJsonPath('data.management.external_members.0.duplicate_candidate.reasons.0', 'email');

        $this->postJson("/api/v1/clubs/{$club->id}/external-members/{$external->id}/invite")
            ->assertUnprocessable();
        $this->assertDatabaseHas('club_external_members', ['id' => $external->id]);

        $this->postJson("/api/v1/clubs/{$club->id}/external-members/{$external->id}/merge/{$member->id}", [
            'resolution' => 'keep_registered',
            'confirm_email' => 'wrong@example.test',
        ])->assertUnprocessable()->assertJsonValidationErrors('confirm_email');

        $this->postJson("/api/v1/clubs/{$club->id}/external-members/{$external->id}/merge/{$member->id}", [
            'resolution' => 'keep_registered',
            'confirm_email' => 'duplicate@example.test',
        ])->assertOk();

        $membership = DB::table('club_user')
            ->where('club_id', $club->id)
            ->where('user_id', $member->id)
            ->first();
        $this->assertSame('manager', $membership->role);
        $this->assertSame(['manager'], json_decode($membership->roles, true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame(['members.edit' => true], json_decode($membership->permission_overrides, true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame('active', $membership->membership_status);
        $this->assertSame('REG-100', $membership->member_number);
        $this->assertSame('family-one', $membership->family_group_key);
        $this->assertEquals(15.0, $membership->contribution_amount);
        $this->assertSame('Bestehende Notiz', $membership->membership_notes);
        $this->assertSame('LIC-200', $member->fresh()->athlete_license_number);
        $this->assertDatabaseMissing('club_external_members', ['id' => $external->id]);
        $this->assertDatabaseHas('club_custom_field_values', [
            'club_id' => $club->id,
            'club_custom_field_definition_id' => $definition->id,
            'subject_type' => 'member',
            'subject_id' => $member->id,
        ]);

        $audit = Activity::query()->where('type', 'club.member.duplicate_merged')->sole();
        $this->assertSame($owner->id, $audit->user_id);
        $this->assertSame($member->id, $audit->subject_id);
        $this->assertSame('keep_registered', $audit->data['resolution']);
        $this->assertSame(['email'], $audit->data['match_reasons']);
        $this->assertNotContains('role', $audit->data['changed_fields']);
        $this->assertNotContains('permission_overrides', $audit->data['changed_fields']);
    }

    public function test_member_number_duplicate_can_use_external_business_data_but_never_roles(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'registered@example.test']);
        $emailMatch = User::factory()->create(['email' => 'different@example.test']);
        $foreign = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $foreignClub = Club::factory()->create(['owner_id' => User::factory()]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'permission_overrides' => ['finance.view' => false],
            'membership_status' => 'active',
            'member_number' => 'MATCH-42',
            'contribution_amount' => 10,
            'contribution_interval' => 'monthly',
        ]);
        $club->users()->attach($emailMatch->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'member_number' => 'OTHER-99',
        ]);
        $foreignClub->users()->attach($foreign->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $external = ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Nummerndublette',
            'email' => 'different@example.test',
            'phone' => '+49 89 12345',
            'city' => 'München',
            'role' => 'admin',
            'membership_status' => 'paused',
            'member_number' => 'MATCH-42',
            'contribution_amount' => 49,
            'contribution_interval' => 'yearly',
            'membership_notes' => 'Übernehmen',
        ]);
        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.management.external_members.0.duplicate_candidate.ambiguous', true)
            ->assertJsonCount(2, 'data.management.external_members.0.duplicate_candidate.candidates');

        $this->postJson("/api/v1/clubs/{$club->id}/external-members/{$external->id}/merge/{$member->id}", [
            'resolution' => 'use_external',
            'confirm_email' => $external->email,
        ])->assertUnprocessable()->assertJsonValidationErrors('target_user_id');
        $external->forceFill(['email' => 'resolved@example.test'])->save();

        $this->postJson("/api/v1/clubs/{$club->id}/external-members/{$external->id}/merge/{$foreign->id}", [
            'resolution' => 'use_external',
            'confirm_email' => $external->email,
        ])->assertNotFound();

        $this->postJson("/api/v1/clubs/{$club->id}/external-members/{$external->id}/merge/{$member->id}", [
            'resolution' => 'use_external',
            'confirm_email' => $external->email,
        ])->assertOk();

        $membership = DB::table('club_user')
            ->where('club_id', $club->id)
            ->where('user_id', $member->id)
            ->first();
        $this->assertSame('member', $membership->role);
        $this->assertSame(['member'], json_decode($membership->roles, true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame(['finance.view' => false], json_decode($membership->permission_overrides, true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame('paused', $membership->membership_status);
        $this->assertEquals(49.0, $membership->contribution_amount);
        $this->assertSame('yearly', $membership->contribution_interval);
        $this->assertSame('Übernehmen', $membership->membership_notes);
        $this->assertSame('+49 89 12345', $member->fresh()->phone);
        $this->assertSame('München', $member->fresh()->city);
    }
}
