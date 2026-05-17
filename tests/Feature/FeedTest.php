<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Story;
use App\Models\StoryView;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_shows_approved_posts_and_hides_reported_posts_from_other_users(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $club = $this->clubWithMember($viewer);

        Post::factory()->create([
            'user_id' => $author->id,
            'club_id' => $club->id,
            'visibility' => 'organization',
            'moderation_status' => 'approved',
            'content' => 'Freigegebene Vereinsinfo',
        ]);

        Post::factory()->create([
            'user_id' => $author->id,
            'club_id' => $club->id,
            'visibility' => 'organization',
            'moderation_status' => 'reported',
            'content' => 'Gemeldete Vereinsinfo',
        ]);

        $this->actingAs($viewer)
            ->get(route('auth.feed.index'))
            ->assertOk()
            ->assertSee('Freigegebene Vereinsinfo')
            ->assertDontSee('Gemeldete Vereinsinfo');
    }

    public function test_author_can_still_see_own_reported_post_in_feed(): void
    {
        $author = User::factory()->create();

        Post::factory()->create([
            'user_id' => $author->id,
            'visibility' => 'public',
            'moderation_status' => 'reported',
            'content' => 'Eigener Beitrag in Pruefung',
        ]);

        $this->actingAs($author)
            ->get(route('auth.feed.index'))
            ->assertOk()
            ->assertSee('Eigener Beitrag in Pruefung');
    }

    public function test_comments_endpoint_returns_only_approved_comments_for_visible_post(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'visibility' => 'public',
            'moderation_status' => 'approved',
        ]);

        Comment::query()->create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'moderation_status' => 'approved',
            'content' => 'Sichtbarer Kommentar',
        ]);

        Comment::query()->create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'moderation_status' => 'reported',
            'content' => 'Gemeldeter Kommentar',
        ]);

        $this->actingAs($viewer)
            ->getJson(route('auth.comments.index', $post))
            ->assertOk()
            ->assertJsonPath('comments.0.content', 'Sichtbarer Kommentar')
            ->assertJsonCount(1, 'comments');
    }

    public function test_comments_endpoint_denies_invisible_team_post(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $club = $this->clubWithMember($author);
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'club_id' => $club->id,
            'visibility' => 'team',
            'moderation_status' => 'approved',
            'content' => 'Team intern',
        ]);

        $this->actingAs($viewer)
            ->getJson(route('auth.comments.index', $post))
            ->assertForbidden();
    }

    public function test_feed_shows_active_visible_stories_and_hides_expired_or_reported_stories(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $club = $this->clubWithMember($viewer);

        Story::query()->create([
            'user_id' => $author->id,
            'club_id' => $club->id,
            'visibility' => 'organization',
            'moderation_status' => 'approved',
            'media_path' => 'stories/visible.jpg',
            'media_type' => 'image/jpeg',
            'caption' => 'Sichtbare Vereinsstory',
            'expires_at' => now()->addHours(4),
        ]);

        Story::query()->create([
            'user_id' => $author->id,
            'club_id' => $club->id,
            'visibility' => 'organization',
            'moderation_status' => 'reported',
            'media_path' => 'stories/reported.jpg',
            'media_type' => 'image/jpeg',
            'caption' => 'Gemeldete Story',
            'expires_at' => now()->addHours(4),
        ]);

        Story::query()->create([
            'user_id' => $author->id,
            'club_id' => $club->id,
            'visibility' => 'organization',
            'moderation_status' => 'approved',
            'media_path' => 'stories/expired.jpg',
            'media_type' => 'image/jpeg',
            'caption' => 'Abgelaufene Story',
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($viewer)
            ->get(route('auth.feed.index'))
            ->assertOk()
            ->assertSee('Sichtbare Vereinsstory')
            ->assertDontSee('Gemeldete Story')
            ->assertDontSee('Abgelaufene Story');
    }

    public function test_author_can_see_own_reported_story_in_feed(): void
    {
        $author = User::factory()->create();

        Story::query()->create([
            'user_id' => $author->id,
            'visibility' => 'public',
            'moderation_status' => 'reported',
            'media_path' => 'stories/own.jpg',
            'media_type' => 'image/jpeg',
            'caption' => 'Eigene Story in Pruefung',
            'expires_at' => now()->addHours(4),
        ]);

        $this->actingAs($author)
            ->get(route('auth.feed.index'))
            ->assertOk()
            ->assertSee('Eigene Story in Pruefung');
    }

    public function test_story_view_endpoint_records_views_for_visible_story(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $story = Story::query()->create([
            'user_id' => $author->id,
            'visibility' => 'public',
            'moderation_status' => 'approved',
            'media_path' => 'stories/public.jpg',
            'media_type' => 'image/jpeg',
            'caption' => 'Public Story',
            'expires_at' => now()->addHours(4),
        ]);

        $this->actingAs($viewer)
            ->post(route('auth.stories.viewed', $story))
            ->assertRedirect();

        $this->assertTrue(StoryView::query()
            ->where('story_id', $story->id)
            ->where('user_id', $viewer->id)
            ->exists());
    }

    public function test_story_view_endpoint_denies_invisible_team_story(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $club = $this->clubWithMember($author);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->syncWithoutDetaching([$author->id => ['role' => 'Player']]);

        $story = Story::query()->create([
            'user_id' => $author->id,
            'club_id' => $club->id,
            'team_id' => $team->id,
            'visibility' => 'team',
            'moderation_status' => 'approved',
            'media_path' => 'stories/team.jpg',
            'media_type' => 'image/jpeg',
            'caption' => 'Team Story',
            'expires_at' => now()->addHours(4),
        ]);

        $this->actingAs($viewer)
            ->postJson(route('auth.stories.viewed', $story))
            ->assertForbidden();
    }

    public function test_story_reactions_can_be_saved_for_visible_story(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $story = Story::query()->create([
            'user_id' => $author->id,
            'visibility' => 'public',
            'moderation_status' => 'approved',
            'media_path' => 'stories/reaction.jpg',
            'media_type' => 'image/jpeg',
            'caption' => 'Reaction Story',
            'expires_at' => now()->addHours(4),
        ]);

        $this->actingAs($viewer)
            ->post(route('auth.stories.react', $story), ['reaction' => 'fire'])
            ->assertRedirect();

        $this->assertDatabaseHas('story_reactions', [
            'story_id' => $story->id,
            'user_id' => $viewer->id,
            'reaction' => 'fire',
        ]);
    }

    public function test_feed_story_payload_groups_official_club_story_under_club_actor(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $club = $this->clubWithMember($viewer);

        Story::query()->create([
            'user_id' => $author->id,
            'club_id' => $club->id,
            'publisher_type' => 'club',
            'publisher_id' => $club->id,
            'visibility' => 'organization',
            'moderation_status' => 'approved',
            'media_path' => 'stories/club.jpg',
            'media_type' => 'image/jpeg',
            'caption' => 'Offizielle Vereinsstory',
            'expires_at' => now()->addHours(4),
        ]);

        $page = $this->actingAs($viewer)
            ->get(route('auth.feed.index'))
            ->assertOk();

        $page->assertSee('Offizielle Vereinsstory')
            ->assertSee($club->name);
    }

    public function test_story_rate_limit_blocks_excessive_story_creation(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 12; $i++) {
            Story::query()->create([
                'user_id' => $user->id,
                'visibility' => 'public',
                'moderation_status' => 'approved',
                'media_path' => 'stories/rate-'.$i.'.jpg',
                'media_type' => 'image/jpeg',
                'caption' => 'Rate '.$i,
                'expires_at' => now()->addHours(4),
                'created_at' => now()->subMinutes(10),
                'updated_at' => now()->subMinutes(10),
            ]);
        }

        $this->actingAs($user)
            ->post(route('auth.stories.store'), [
                'visibility' => 'public',
                'caption' => 'Too much',
            ])
            ->assertSessionHasErrors('media');
    }

    public function test_regular_member_cannot_publish_story_as_club_identity(): void
    {
        config(['filesystems.uploads_disk' => 'public']);
        Storage::fake('public');

        $user = User::factory()->create();
        $club = $this->clubWithMember($user);

        $this->actingAs($user)
            ->post(route('auth.stories.store'), [
                'club_id' => $club->id,
                'publisher_type' => 'club',
                'visibility' => 'organization',
                'caption' => 'Offiziell ohne Rolle',
                'media' => UploadedFile::fake()->image('story.jpg'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertDatabaseMissing('stories', [
            'user_id' => $user->id,
            'publisher_type' => 'club',
            'publisher_id' => $club->id,
        ]);
    }

    public function test_team_coach_can_publish_story_as_team_identity(): void
    {
        config(['filesystems.uploads_disk' => 'public']);
        Storage::fake('public');

        $user = User::factory()->create();
        $club = $this->clubWithMember($user);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->syncWithoutDetaching([$user->id => ['role' => 'Coach']]);

        $this->actingAs($user)
            ->post(route('auth.stories.store'), [
                'club_id' => $club->id,
                'team_id' => $team->id,
                'publisher_type' => 'team',
                'visibility' => 'team',
                'caption' => 'Offizielle Teamstory',
                'media' => UploadedFile::fake()->image('team-story.jpg'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('stories', [
            'user_id' => $user->id,
            'club_id' => $club->id,
            'team_id' => $team->id,
            'publisher_type' => 'team',
            'publisher_id' => $team->id,
        ]);
    }

    public function test_feed_marks_story_publisher_options_by_role(): void
    {
        $user = User::factory()->create();
        $club = $this->clubWithMember($user);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->syncWithoutDetaching([$user->id => ['role' => 'Coach']]);

        $this->actingAs($user)
            ->get(route('auth.feed.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Feed/Index')
                ->where('clubs.0.can_publish_as', false)
                ->where('teams.0.can_publish_as', true)
            );
    }

    public function test_prune_expired_stories_command_removes_expired_media_and_keeps_active_stories(): void
    {
        config(['filesystems.uploads_disk' => 'public']);

        $user = User::factory()->create();
        $expiredPath = 'stories/test-expired-'.uniqid().'.jpg';
        $activePath = 'stories/test-active-'.uniqid().'.jpg';

        $expired = Story::query()->create([
            'user_id' => $user->id,
            'visibility' => 'public',
            'moderation_status' => 'approved',
            'media_path' => $expiredPath,
            'media_type' => 'image/jpeg',
            'caption' => 'Expired Story',
            'expires_at' => now()->subMinute(),
        ]);

        $active = Story::query()->create([
            'user_id' => $user->id,
            'visibility' => 'public',
            'moderation_status' => 'approved',
            'media_path' => $activePath,
            'media_type' => 'image/jpeg',
            'caption' => 'Active Story',
            'expires_at' => now()->addHour(),
        ]);

        $this->artisan('airmius:prune-expired-stories')
            ->expectsOutput('1 expired stories pruned.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('stories', ['id' => $expired->id]);
        $this->assertDatabaseHas('stories', ['id' => $active->id]);
    }

    private function clubWithMember(User $user): Club
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'is_listed' => true,
            'teams_are_listed' => true,
            'members_can_post_to_club' => true,
            'members_can_post_to_teams' => true,
        ]);

        $club->users()->syncWithoutDetaching([
            $user->id => ['role' => 'member', 'roles' => ['member']],
        ]);

        return $club;
    }
}
