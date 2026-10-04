<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubMembershipType;
use App\Models\User;
use App\Notifications\ExternalClubMembershipInvitation;
use App\Support\ClubRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMemberInvitationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_invites_external_member_with_role_token_link_and_expiry(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $expiresOn = now()->addDays(5)->toDateString();

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/clubs/{$club->id}/members/invite", [
            'email' => 'trainer@example.org',
            'name' => 'Trainer Einladung',
            'role' => 'trainer',
            'membership_status' => 'pending',
            'member_number' => 'UC21-001',
            'contribution_amount' => 19,
            'contribution_interval' => 'monthly',
            'send_invitation' => true,
            'invitation_expires_at' => $expiresOn,
        ])->assertOk()
            ->assertJsonPath('status', 'invited')
            ->assertJsonPath('data.external_members.0.role', 'trainer')
            ->assertJsonPath('data.external_members.0.invitation_status', 'pending');

        $externalMember = ClubExternalMember::query()->firstOrFail();

        $this->assertSame('trainer', $externalMember->role);
        $this->assertSame('pending', $externalMember->membership_status);
        $this->assertSame('UC21-001', $externalMember->member_number);
        $this->assertSame('19.00', $externalMember->contribution_amount);
        $this->assertSame('monthly', $externalMember->contribution_interval);
        $this->assertSame('pending', $externalMember->invitation_status);
        $this->assertNotNull($externalMember->invitation_token);
        $this->assertSame(64, strlen($externalMember->invitation_token));
        $this->assertTrue($externalMember->invitation_expires_at->isSameDay($expiresOn));

        $response
            ->assertJsonPath('data.external_members.0.invitation_token', $externalMember->invitation_token)
            ->assertJsonPath('data.external_members.0.invitation_url', route('auth.club-member-invitations.accept', $externalMember->invitation_token))
            ->assertJsonPath('data.invitable_club_roles.0.value', 'admin')
            ->assertJsonPath('data.invitable_club_roles.5.value', 'member');

        Notification::assertSentOnDemand(ExternalClubMembershipInvitation::class);
    }

    public function test_accepting_external_invitation_applies_selected_club_role(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'manager@example.org']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $membershipType = ClubMembershipType::query()->create([
            'club_id' => $club->id,
            'name' => 'Befristete Mitgliedschaft',
            'slug' => 'fixed-term',
            'is_public' => true,
            'is_active' => true,
        ]);
        $externalMember = ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Manager Einladung',
            'email' => $invitee->email,
            'role' => 'manager',
            'membership_status' => 'active',
            'club_membership_type_id' => $membershipType->id,
            'phone' => '+49 221 12345',
            'city' => 'Köln',
        ]);

        $externalMember->issueInvitation(now()->addDay());

        $this->actingAs($invitee)
            ->get(route('auth.club-member-invitations.accept', $externalMember->invitation_token))
            ->assertRedirect(route('auth.clubs.show', $club));

        $membership = DB::table('club_user')
            ->where('club_id', $club->id)
            ->where('user_id', $invitee->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertSame('manager', $membership->role);
        $this->assertSame(['manager'], json_decode($membership->roles, true));
        $this->assertSame($membershipType->id, $membership->club_membership_type_id);
        $this->assertSame('+49 221 12345', $invitee->fresh()->phone);
        $this->assertSame('Köln', $invitee->fresh()->city);
        $this->assertDatabaseMissing('club_external_members', ['id' => $externalMember->id]);
    }

    public function test_existing_account_must_accept_email_and_in_app_invitation_before_linking(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $member = User::factory()->create([
            'email' => 'mitglied@example.org',
            'timezone' => 'America/New_York',
        ]);
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/members/invite", [
            'email' => $member->email,
            'name' => $member->name,
            'role' => 'member',
            'membership_status' => 'active',
            'send_invitation' => true,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'invited');

        $notification = $member->appNotifications()
            ->where('type', 'club.membership_invitation')
            ->firstOrFail();

        $externalMember = ClubExternalMember::query()->where('email', $member->email)->firstOrFail();
        $mobileUrl = 'airmius://club-member-invitations/'.$externalMember->invitation_token;

        $this->assertSame(route('auth.club-member-invitations.accept', $externalMember->invitation_token), $notification->data['url']);
        $this->assertSame($mobileUrl, $notification->data['mobile_url']);
        $this->assertSame($mobileUrl, $notification->data['deep_link']);
        $this->assertSame(
            $externalMember->invitation_expires_at->copy()->timezone('America/New_York')->format('d.m.Y H.i'),
            $notification->data['invitation_expires_at'],
        );
        $this->assertSame('America/New_York', $notification->data['invitation_expires_at_timezone']);
        $this->assertDatabaseMissing('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
        ]);
        Notification::assertSentOnDemand(ExternalClubMembershipInvitation::class);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/club-external-invitations/{$externalMember->invitation_token}/accept")
            ->assertOk();

        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $member->id,
        ]);
        $this->assertDatabaseMissing('club_external_members', ['id' => $externalMember->id]);

        $this->actingAs($member)
            ->get(route('auth.clubs.show', $club))
            ->assertOk();
    }

    public function test_expired_external_invitation_returns_gone_and_marks_invitation_expired(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'expired@example.org']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $externalMember = ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Abgelaufene Einladung',
            'email' => $invitee->email,
            'role' => 'trainer',
            'membership_status' => 'pending',
        ]);

        $externalMember->issueInvitation(now()->subDays(2));

        $this->actingAs($invitee)
            ->get(route('auth.club-member-invitations.accept', $externalMember->invitation_token))
            ->assertStatus(410);

        $this->assertDatabaseHas('club_external_members', [
            'id' => $externalMember->id,
            'invitation_status' => 'expired',
        ]);
        $this->assertDatabaseMissing('club_user', [
            'club_id' => $club->id,
            'user_id' => $invitee->id,
            'role' => ClubRoles::primary(['trainer']),
        ]);
    }
}
