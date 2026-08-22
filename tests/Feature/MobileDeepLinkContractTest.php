<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileDeepLinkContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_deep_link_resolver_covers_mvp_targets(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $cases = [
            ['airmius://clubs/7', 'club_show', 'ClubShow', 'club', '7'],
            ['airmius://clubs/7/membership-requests', 'club_membership_requests', 'ClubMembershipRequests', 'club', '7'],
            ['airmius://membership-applications/99', 'membership_application', 'MembershipRequestStatus', 'application', '99'],
            ['airmius://notifications', 'notifications', 'NotificationsCenter', null, null],
            ['airmius://teams/12', 'team_show', 'TeamShow', 'team', '12'],
            ['airmius://events/31', 'event_show', 'EventShow', 'event', '31'],
            ['airmius://training/plans/41', 'training_plan', 'TrainingPlanShow', 'trainingPlan', '41'],
            ['airmius://training/logs/42', 'training_log', 'TrainingLogShow', 'trainingLog', '42'],
            ['airmius://feed/44', 'feed_post', 'FeedPost', 'post', '44'],
            ['airmius://chat/9', 'chat_conversation', 'ChatConversation', 'conversation', '9'],
            ['airmius://messages/17', 'chat_message', 'ChatMessage', 'message', '17'],
            ['airmius://profile/23', 'profile_show', 'ProfileShow', 'user', '23'],
            ['airmius://friends/invitations/token/abc123/accept', 'friend_invitation', 'FriendInvitationAccept', 'token', 'abc123'],
            ['airmius://team-invitations/token/abc123/accept', 'invitation', 'InvitationAccept', 'token', 'abc123'],
        ];

        foreach ($cases as [$url, $key, $screen, $param, $value]) {
            $response = $this->postJson('/api/v1/mobile/deep-links/resolve', ['url' => $url])
                ->assertOk()
                ->assertJsonPath('data.target.recognized', true)
                ->assertJsonPath('data.target.key', $key)
                ->assertJsonPath('data.target.screen', $screen);

            if ($param !== null) {
                $response->assertJsonPath("data.target.params.$param", $value);
            }
        }
    }
}
