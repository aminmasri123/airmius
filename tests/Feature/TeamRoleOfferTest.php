<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Notification;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamRoleOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamRoleOfferTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $role = 'Player'): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $club->users()->attach($member, ['role' => 'member']);
        $team->users()->attach($member, ['role' => $role]);

        return [$owner, $member, $team];
    }

    private function offer(User $owner, User $member, Team $team, string $role = 'Coach'): TeamRoleOffer
    {
        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/teams/{$team->id}/members/{$member->id}", ['role' => $role])->assertOk();

        return TeamRoleOffer::latest('id')->firstOrFail();
    }

    public function test_offer_keeps_existing_role_until_recipient_accepts_and_notifies_once(): void
    {
        [$owner, $member, $team] = $this->fixture();
        $offer = $this->offer($owner, $member, $team);
        $this->assertDatabaseHas('team_user', ['team_id' => $team->id, 'user_id' => $member->id, 'role' => 'Player']);
        $this->assertFalse($member->can('updateMemberRole', $team));
        $this->assertDatabaseHas('notifications', ['user_id' => $member->id, 'type' => 'team.role_offered']);
        $this->offer($owner, $member, $team);
        $this->assertSame(1, TeamRoleOffer::count());
        $this->assertSame(1, Notification::where('user_id', $member->id)->where('type', 'team.role_offered')->count());
        Sanctum::actingAs($member);
        $this->getJson("/api/v1/teams/{$team->id}")->assertOk()->assertJsonPath('data.role_offers.0.can_respond', true);
        $this->postJson("/api/v1/team-role-offers/{$offer->id}/accept")->assertOk()->assertJsonCount(0, 'data.role_offers');
        $this->assertDatabaseHas('team_user', ['team_id' => $team->id, 'user_id' => $member->id, 'role' => 'Coach']);
        $this->assertSame(0, $member->roles()->count());
        $this->postJson("/api/v1/team-role-offers/{$offer->id}/accept")->assertOk();
        $this->assertSame(1, Notification::where('user_id', $owner->id)->where('type', 'team.role_accepted')->count());
    }

    public function test_web_offer_and_decline_preserve_role_and_profile_contains_decision_buttons(): void
    {
        [$owner, $member, $team] = $this->fixture('Captain');
        $this->actingAs($owner)->put(route('auth.teams.members.update', [$team, $member]), ['role' => 'Treasurer'])->assertRedirect()->assertSessionHasNoErrors();
        $offer = TeamRoleOffer::firstOrFail();
        $this->actingAs($member)->get(route('auth.teams.show', $team))->assertOk()->assertInertia(fn ($page) => $page->has('teamProfile.role_offers', 1)->where('teamProfile.role_offers.0.can_respond', true));
        $this->post(route('auth.team-role-offers.respond', [$offer, 'decline']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('declined', $offer->fresh()->status);
        $this->assertDatabaseHas('team_user', ['team_id' => $team->id, 'user_id' => $member->id, 'role' => 'Captain']);
    }

    public function test_only_recipient_can_respond_and_unrelated_members_cannot_see_offers(): void
    {
        [$owner, $member, $team] = $this->fixture();
        $offer = $this->offer($owner, $member, $team);
        $this->postJson("/api/v1/team-role-offers/{$offer->id}/accept")->assertForbidden();
        $other = User::factory()->create();
        $team->users()->attach($other, ['role' => 'Player']);
        Sanctum::actingAs($other);
        $this->getJson("/api/v1/teams/{$team->id}")->assertOk()->assertJsonCount(0, 'data.role_offers');
        foreach (['accept', 'decline', 'cancel'] as $decision) {
            $this->postJson("/api/v1/team-role-offers/{$offer->id}/{$decision}")->assertForbidden();
        }
    }

    public function test_revoke_is_immediate_and_cancelled_offer_cannot_restore_rights(): void
    {
        [$owner, $member, $team] = $this->fixture('Coach');
        $offer = $this->offer($owner, $member, $team, 'Treasurer');
        $oldInvitation = TeamInvitation::create(['team_id' => $team->id, 'inviter_id' => $owner->id, 'email' => $member->email, 'token' => 'old-role-invitation', 'role' => 'Coach', 'status' => 'pending']);
        $this->putJson("/api/v1/teams/{$team->id}/members/{$member->id}", ['role' => 'Player'])->assertOk()->assertJsonCount(0, 'data.role_offers');
        $this->assertDatabaseHas('team_user', ['team_id' => $team->id, 'user_id' => $member->id, 'role' => 'Player']);
        Sanctum::actingAs($member);
        $this->postJson("/api/v1/team-role-offers/{$offer->id}/accept")->assertStatus(409);
        $this->assertSame('cancelled', $offer->fresh()->status);
        $this->assertSame('cancelled', $oldInvitation->fresh()->status);
        $this->postJson('/api/v1/team-invitations/token/old-role-invitation/accept')->assertStatus(404);
    }

    public function test_expired_stale_and_no_longer_authorized_offers_cannot_be_accepted(): void
    {
        [$owner, $member, $team] = $this->fixture();
        $offer = $this->offer($owner, $member, $team);
        $offer->update(['expires_at' => now()->subMinute()]);
        Sanctum::actingAs($member);
        $this->postJson("/api/v1/team-role-offers/{$offer->id}/accept")->assertStatus(409);
        $offer->update(['expires_at' => now()->addDay()]);
        $team->users()->updateExistingPivot($member->id, ['role' => 'Captain']);
        $this->postJson("/api/v1/team-role-offers/{$offer->id}/accept")->assertStatus(409);
        $team->users()->updateExistingPivot($member->id, ['role' => 'Player']);
        $team->club->update(['owner_id' => User::factory()->create()->id]);
        $team->club->users()->detach($owner);
        $this->postJson("/api/v1/team-role-offers/{$offer->id}/accept")->assertStatus(409);
        $this->assertDatabaseHas('team_user', ['team_id' => $team->id, 'user_id' => $member->id, 'role' => 'Player']);
    }

    public function test_replacement_cancellation_and_member_removal_invalidate_old_offers(): void
    {
        [$owner, $member, $team] = $this->fixture();
        $first = $this->offer($owner, $member, $team);
        $second = $this->offer($owner, $member, $team, 'Captain');
        $this->assertSame('cancelled', $first->fresh()->status);
        $this->postJson("/api/v1/team-role-offers/{$second->id}/cancel")->assertOk();
        $this->assertSame('cancelled', $second->fresh()->status);
        $third = $this->offer($owner, $member, $team);
        $this->deleteJson("/api/v1/teams/{$team->id}/members/{$member->id}")->assertOk();
        $this->assertSame('cancelled', $third->fresh()->status);
        $team->users()->attach($member, ['role' => 'Player']);
        Sanctum::actingAs($member);
        $this->postJson("/api/v1/team-role-offers/{$third->id}/accept")->assertStatus(409);
    }

    public function test_direct_member_addition_does_not_grant_privileged_role(): void
    {
        [$owner, $member, $team] = $this->fixture();
        $team->users()->detach($member);
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/teams/{$team->id}/members", ['user_id' => $member->id, 'role' => 'Treasurer'])->assertCreated();
        $this->assertDatabaseHas('team_user', ['team_id' => $team->id, 'user_id' => $member->id, 'role' => 'Player']);
        $this->assertSame('Treasurer', TeamRoleOffer::firstOrFail()->role);
    }
}
