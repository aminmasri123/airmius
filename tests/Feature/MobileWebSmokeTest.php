<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\Message;
use App\Models\Post;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MobileWebSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_mobile_web_core_pages_render(): void
    {
        [$user, $club] = $this->mobileWebWorkspace();

        $mobileHeaders = [
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ];

        $pages = [
            [
                'url' => route('auth.dashboard'),
                'component' => 'Auth/Dashboard/Index',
                'assert' => fn (Assert $page) => $page
                    ->where('dashboard.events.upcoming_count', 1)
                    ->where('dashboard.events.next.0.title', 'Mobile Web Smoke Event'),
            ],
            [
                'url' => url('/clubs'),
                'component' => 'Auth/Dashboard/Teams/Index',
                'assert' => fn (Assert $page) => $page
                    ->where('clubs.0.name', 'AIRMIUS Mobile Club')
                    ->where('clubs.0.teams.0.name', 'Mobile Smoke Team'),
            ],
            [
                'url' => route('auth.teams.index'),
                'component' => 'Auth/Dashboard/Teams/Index',
                'assert' => fn (Assert $page) => $page
                    ->where('clubs.0.name', 'AIRMIUS Mobile Club')
                    ->where('clubs.0.teams.0.name', 'Mobile Smoke Team'),
            ],
            [
                'url' => route('auth.feed.index'),
                'component' => 'Auth/Dashboard/Feed/Index',
                'assert' => fn (Assert $page) => $page
                    ->where('posts.data.0.content', 'Mobile Web Smoke Feed')
                    ->where('teams.0.name', 'Mobile Smoke Team'),
            ],
            [
                'url' => route('auth.conversations.index'),
                'component' => 'Auth/Dashboard/Chat/Index',
                'assert' => fn (Assert $page) => $page
                    ->where('conversations.0.latest_visible_message.message', 'Mobile Web Smoke Chat'),
            ],
            [
                'url' => route('auth.events.index'),
                'component' => 'Auth/Dashboard/Events/Index',
                'assert' => fn (Assert $page) => $page
                    ->where('events.data.0.title', 'Mobile Web Smoke Event')
                    ->where('teams.0.name', 'Mobile Smoke Team'),
            ],
        ];

        foreach ($pages as $page) {
            $response = $this
                ->actingAs($user)
                ->withHeaders($mobileHeaders)
                ->withSession(['club_id' => $club->id])
                ->get($page['url']);

            $response->assertOk()
                ->assertInertia(function (Assert $inertia) use ($page) {
                    $inertia->component($page['component']);
                    $page['assert']($inertia);
                });
            $this->assertStringContainsString('X-Inertia', (string) $response->headers->get('Vary'));
        }
    }

    private function mobileWebWorkspace(): array
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $user->id,
            'name' => 'AIRMIUS Mobile Club',
            'is_listed' => true,
            'teams_are_listed' => true,
            'members_can_post_to_club' => true,
            'members_can_post_to_teams' => true,
            'verification_status' => 'verified',
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'name' => 'Mobile Smoke Team',
        ]);

        $club->users()->syncWithoutDetaching([
            $user->id => ['role' => 'owner', 'roles' => ['owner']],
        ]);
        $team->users()->attach($user->id, ['role' => TeamRoles::PLAYER]);

        Post::factory()->create([
            'user_id' => $user->id,
            'club_id' => $club->id,
            'team_id' => $team->id,
            'visibility' => 'team',
            'moderation_status' => 'approved',
            'content' => 'Mobile Web Smoke Feed',
        ]);

        Event::create([
            'user_id' => $user->id,
            'club_id' => $club->id,
            'team_id' => $team->id,
            'title' => 'Mobile Web Smoke Event',
            'type' => 'training',
            'visibility' => 'organization',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
            'location' => 'Mobile Halle',
        ]);

        $conversation = Conversation::create([
            'type' => 'direct',
        ]);
        $conversation->users()->attach([$user->id, $otherUser->id], ['joined_at' => now()]);
        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $otherUser->id,
            'message' => 'Mobile Web Smoke Chat',
            'status' => 'sent',
        ]);

        return [$user, $club];
    }
}
