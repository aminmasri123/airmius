<?php
namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipStatusNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_is_notified_once_per_actual_status_change(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['language' => 'de']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'membership_status' => 'active']);
        $url = route('auth.club-memberships.members.update', [$club, $member]);
        $data = ['role' => 'member', 'membership_status' => 'paused'];
        $this->actingAs($owner)->put($url, $data)->assertSessionHasNoErrors()->assertRedirect();
        $notification = Notification::where('user_id', $member->id)->where('type', 'club.member.status_updated')->sole();
        $this->assertSame('active', $notification->data['previous_status']);
        $this->assertSame('paused', $notification->data['membership_status']);
        $this->assertStringContainsString('Pausiert', $notification->data['body']);
        $this->put($url, $data)->assertSessionHasNoErrors();
        $this->assertSame(1, Notification::where('type', 'club.member.status_updated')->count());
        $this->actingAs($owner)->putJson('/api/v1/clubs/'.$club->id.'/members/'.$member->id, ['role' => 'member', 'membership_status' => 'active'])->assertOk();
        $this->assertSame(2, Notification::where('type', 'club.member.status_updated')->count());
        $this->actingAs($member)->putJson($url, ['role' => 'member', 'membership_status' => 'active'])->assertForbidden();
        $this->assertSame(2, Notification::where('type', 'club.member.status_updated')->count());
    }
}
