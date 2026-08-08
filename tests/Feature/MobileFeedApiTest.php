<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\ContentReport;
use App\Models\DomainOutboxEvent;
use App\Models\ModerationFlag;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileFeedApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_feed_supports_posts_comments_reactions_and_delete(): void
    {
        $user = User::factory()->create(['name' => 'ZBB Konto']);
        Sanctum::actingAs($user);

        $postResponse = $this->postJson('/api/v1/feed', [
            'content' => 'Heute gibt es ein neues Training im Verein.',
            'visibility' => 'public',
            'post_type' => 'normal',
        ])
            ->assertCreated()
            ->assertJsonPath('data.content', 'Heute gibt es ein neues Training im Verein.')
            ->assertJsonPath('data.visibility', 'public')
            ->assertJsonPath('data.can_delete', true);

        $postId = $postResponse->json('data.id');

        $outboxEvent = DomainOutboxEvent::query()
            ->where('event_name', 'community.post.created.v1')
            ->where('aggregate_id', (string) $postId)
            ->firstOrFail();
        $this->assertSame('public', $outboxEvent->payload['visibility']);
        $this->assertArrayNotHasKey('content', $outboxEvent->payload);

        $this->getJson('/api/v1/feed')
            ->assertOk()
            ->assertJsonPath('data.0.id', $postId)
            ->assertJsonPath('data.0.user.name', 'ZBB Konto');

        $commentResponse = $this->postJson("/api/v1/posts/{$postId}/comments", [
            'content' => 'Ich bin dabei.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.content', 'Ich bin dabei.')
            ->assertJsonPath('data.mine', true);

        $commentId = $commentResponse->json('data.id');

        $this->getJson("/api/v1/posts/{$postId}/comments")
            ->assertOk()
            ->assertJsonPath('data.0.id', $commentId)
            ->assertJsonPath('data.0.content', 'Ich bin dabei.');

        $this->putJson("/api/v1/comments/{$commentId}", [
            'content' => 'Ich bin sicher dabei.',
        ])
            ->assertOk()
            ->assertJsonPath('data.content', 'Ich bin sicher dabei.');

        $this->postJson("/api/v1/posts/{$postId}/like")
            ->assertOk()
            ->assertJsonPath('data.likes_count', 1)
            ->assertJsonPath('data.liked_by_me', true);

        $this->postJson("/api/v1/posts/{$postId}/helpful")
            ->assertOk()
            ->assertJsonPath('data.helpfuls_count', 1)
            ->assertJsonPath('data.helpful_by_me', true);

        $this->deleteJson("/api/v1/comments/{$commentId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('comments', ['id' => $commentId]);

        $this->deleteJson("/api/v1/posts/{$postId}")
            ->assertOk()
            ->assertJsonPath('message', 'Post deleted.');

        $this->assertDatabaseMissing('posts', ['id' => $postId]);
    }

    public function test_mobile_post_detail_returns_visible_post_and_denies_private_post(): void
    {
        $author = User::factory()->create(['name' => 'Post Autor']);
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();
        $public = Post::factory()->create([
            'user_id' => $author->id,
            'visibility' => 'public',
            'moderation_status' => 'approved',
            'content' => 'Öffentlicher Deep-Link-Beitrag',
        ]);
        $private = Post::factory()->create([
            'user_id' => $author->id,
            'visibility' => 'team',
            'moderation_status' => 'approved',
            'content' => 'Privater Deep-Link-Beitrag',
        ]);

        Sanctum::actingAs($viewer);
        $this->getJson("/api/v1/posts/{$public->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $public->id)
            ->assertJsonPath('data.content', 'Öffentlicher Deep-Link-Beitrag')
            ->assertJsonPath('data.user.name', 'Post Autor');

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/posts/{$private->id}")
            ->assertForbidden();

        Sanctum::actingAs($author);
        $this->getJson("/api/v1/posts/{$private->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $private->id);
    }

    public function test_mobile_story_api_supports_list_view_react_and_delete(): void
    {
        config([
            'filesystems.uploads_disk' => 'public',
            'filesystems.uploads_url' => '/storage',
        ]);
        Storage::fake('public');

        $user = User::factory()->create(['name' => 'Story Autor']);
        $viewer = User::factory()->create(['name' => 'Story Viewer']);
        Sanctum::actingAs($user);

        Storage::disk('public')->put('users/'.$user->id.'/stories/story.jpg', 'fake image');

        $storyId = DB::table('stories')->insertGetId([
            'user_id' => $user->id,
            'publisher_type' => 'user',
            'publisher_id' => $user->id,
            'visibility' => 'public',
            'media_path' => 'users/'.$user->id.'/stories/story.jpg',
            'media_type' => 'image/jpeg',
            'media_size' => 10,
            'caption' => 'Training laeuft.',
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/stories')
            ->assertOk()
            ->assertJsonPath('data.0.id', $storyId)
            ->assertJsonPath('data.0.caption', 'Training laeuft.')
            ->assertJsonPath('data.0.can_delete', false);

        $this->postJson("/api/v1/stories/{$storyId}/viewed")
            ->assertOk()
            ->assertJsonPath('data.viewed_by_me', true);

        $this->assertDatabaseHas('story_views', [
            'story_id' => $storyId,
            'user_id' => $viewer->id,
        ]);

        $this->postJson("/api/v1/stories/{$storyId}/react", [
            'reaction' => 'heart',
        ])
            ->assertOk()
            ->assertJsonPath('data.my_reaction', 'heart');

        $this->assertDatabaseHas('story_reactions', [
            'story_id' => $storyId,
            'user_id' => $viewer->id,
            'reaction' => 'heart',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/stories/{$storyId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('stories', ['id' => $storyId]);
    }

    public function test_mobile_feed_hides_private_team_posts_from_outsiders(): void
    {
        $author = User::factory()->create();
        $outsider = User::factory()->create();

        Post::query()->create([
            'user_id' => $author->id,
            'visibility' => 'team',
            'post_type' => 'normal',
            'content_origin' => 'self',
            'content' => 'Nur fuer Teammitglieder.',
        ]);

        Sanctum::actingAs($outsider);

        $this->getJson('/api/v1/feed')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_mobile_post_image_proxy_requires_post_visibility_permission(): void
    {
        config([
            'filesystems.uploads_disk' => 'public',
            'filesystems.uploads_url' => '/storage',
        ]);
        Storage::fake('public');

        $author = User::factory()->create();
        $outsider = User::factory()->create();
        Storage::disk('public')->put('posts/private.jpg', 'private image');
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'visibility' => 'organization',
            'moderation_status' => 'approved',
            'image' => 'posts/private.jpg',
        ]);

        $this->getJson("/api/v1/posts/{$post->id}/image")
            ->assertUnauthorized();

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/posts/{$post->id}/image")
            ->assertForbidden();

        Sanctum::actingAs($author);
        $this->get("/api/v1/posts/{$post->id}/image")
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');

        $post->update(['image' => 'posts/missing.jpg']);
        $this->get("/api/v1/posts/{$post->id}/image")
            ->assertNotFound();

        $post->update(['image' => 'posts/private.jpg']);

        $feedPost = $this->getJson('/api/v1/feed')
            ->assertOk()
            ->json('data.0');
        $proxyUrl = route('api.v1.posts.image', $post);
        $this->assertSame($proxyUrl, $feedPost['image']);
        $this->assertSame($proxyUrl, $feedPost['image_url']);
        $this->assertSame($proxyUrl, $feedPost['image_proxy_url']);
        $this->assertArrayNotHasKey('uploads_base_url', $feedPost);
    }

    public function test_mobile_feed_reports_posts_and_comments_for_moderation(): void
    {
        $author = User::factory()->create();
        $reporter = User::factory()->create();
        $viewer = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'visibility' => 'public',
            'moderation_status' => 'approved',
            'content' => 'Bitte pruefen.',
        ]);
        $comment = Comment::query()->create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'moderation_status' => 'approved',
            'content' => 'Kommentar bitte pruefen.',
        ]);

        Sanctum::actingAs($reporter);

        $this->postJson('/api/v1/reports', [
            'type' => 'comment',
            'id' => $comment->id,
            'reason' => 'spam',
            'details' => 'Wirkt automatisiert.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'comment')
            ->assertJsonPath('data.reason', 'spam');

        $this->assertDatabaseHas('content_reports', [
            'reporter_id' => $reporter->id,
            'reportable_type' => Comment::class,
            'reportable_id' => $comment->id,
            'reason' => 'spam',
        ]);
        $this->assertDatabaseHas('moderation_flags', [
            'flaggable_type' => Comment::class,
            'flaggable_id' => $comment->id,
            'source' => 'user_report',
        ]);
        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'moderation_status' => 'reported',
        ]);

        $this->getJson("/api/v1/posts/{$post->id}/comments")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson('/api/v1/reports', [
            'type' => 'post',
            'id' => $post->id,
            'reason' => 'other',
            'details' => 'Unklarer Inhalt.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'post')
            ->assertJsonPath('data.reason', 'other');

        $this->assertDatabaseHas('content_reports', [
            'reporter_id' => $reporter->id,
            'reportable_type' => Post::class,
            'reportable_id' => $post->id,
            'reason' => 'other',
        ]);
        $this->assertDatabaseHas('moderation_flags', [
            'flaggable_type' => Post::class,
            'flaggable_id' => $post->id,
            'source' => 'user_report',
        ]);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'moderation_status' => 'reported',
        ]);

        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/feed')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Sanctum::actingAs($author);

        $this->getJson('/api/v1/feed')
            ->assertOk()
            ->assertJsonPath('data.0.id', $post->id);

        $this->assertSame(2, ContentReport::query()->count());
        $this->assertSame(2, ModerationFlag::query()->where('source', 'user_report')->count());
    }
}
