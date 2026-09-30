<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\File;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FriendsPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_friends_posts_can_be_created_and_updated_in_web_and_api(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);
        $payload = ['post_type' => 'normal', 'content_origin' => 'self', 'visibility' => 'friends', 'content' => 'Friends note', 'club_id' => $club->id];
        $id = $this->postJson('/api/v1/feed', $payload)->assertCreated()
            ->assertJsonPath('data.visibility', 'friends')->assertJsonPath('data.club_id', null)->json('data.id');
        $this->putJson('/api/v1/posts/'.$id, [...$payload, 'content' => 'Updated'])->assertOk();
        $this->actingAs($owner)->post(route('auth.posts.store'), $payload)->assertRedirect();
        $post = Post::latest('id')->first();
        $this->assertSame('friends', $post->visibility);
        $this->assertNull($post->club_id);
        $this->put(route('auth.posts.update', $post), [...$payload, 'content' => 'Updated web'])->assertRedirect();
        $this->assertSame('Updated web', $post->fresh()->content);
    }

    public function test_only_author_and_friends_can_read_posts_and_media_and_access_is_revoked_after_unfriending(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        config(['filesystems.uploads_disk' => 'public']);
        $owner = User::factory()->create();
        $friend = User::factory()->create();
        $follower = User::factory()->create();
        $friend->friendships()->create(['friend_id' => $owner->id]);
        $owner->friendships()->create(['friend_id' => $friend->id]);
        $follower->following()->create(['followed_id' => $owner->id]);
        $post = Post::factory()->create(['user_id' => $owner->id, 'visibility' => 'public', 'image' => 'posts/photo.png', 'moderation_status' => 'approved']);
        $file = File::create(['user_id' => $owner->id, 'path' => 'posts/note.txt', 'display_name' => 'note.txt', 'type' => 'text/plain', 'size' => 4]);
        $post->attachments()->create(['file_id' => $file->id]);
        Storage::disk('public')->put('posts/photo.png', 'photo');
        Storage::disk('public')->put('posts/note.txt', 'note');
        Sanctum::actingAs($owner);
        $this->putJson('/api/v1/posts/'.$post->id, ['post_type' => 'normal', 'content_origin' => 'self', 'content' => 'Friends secret', 'visibility' => 'friends'])->assertOk();
        $post->refresh();
        $file->refresh();
        Storage::disk('public')->assertMissing('posts/photo.png');
        Storage::disk('public')->assertMissing('posts/note.txt');
        Storage::disk('local')->assertExists($post->image);
        Storage::disk('local')->assertExists($file->path);
        $url = \App\Support\UploadStorage::url($file->path);
        foreach ([$owner, $friend] as $viewer) {
            Sanctum::actingAs($viewer);
            $this->getJson('/api/v1/posts/'.$post->id)->assertOk();
            $this->getJson('/api/v1/feed')->assertOk()->assertSee('Friends secret');
            $this->actingAs($viewer)->get(route('auth.feed.index'))->assertOk()->assertSee('Friends secret');
            $this->getJson('/api/v1/posts/'.$post->id.'/image')->assertOk();
            $this->get($url)->assertOk();
            $this->getJson('/api/v1/files/'.$file->id.'/preview')->assertOk();
        }
        $friend->friendships()->delete();
        $owner->friendships()->delete();
        foreach ([$follower, $friend] as $viewer) {
            Sanctum::actingAs($viewer);
            $this->getJson('/api/v1/posts/'.$post->id)->assertForbidden();
            $this->getJson('/api/v1/posts/'.$post->id.'/comments')->assertForbidden();
            $this->getJson('/api/v1/feed')->assertOk()->assertDontSee('Friends secret');
            $this->actingAs($viewer)->get(route('auth.feed.index'))->assertOk()->assertDontSee('Friends secret');
            $this->getJson('/api/v1/posts/'.$post->id.'/image')->assertForbidden();
            $this->getJson($url)->assertForbidden();
            $this->getJson('/api/v1/files/'.$file->id.'/preview')->assertForbidden();
        }
        \Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web');
        $follower->assignRole('super_admin');
        $this->assertFalse(Gate::forUser($follower)->allows('view', $post));
        $this->assertFalse(Gate::forUser($follower)->allows('view', $file));
    }

    public function test_default_audience_is_shared_by_web_and_mobile_settings(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->patchJson('/api/v1/settings', ['default_post_visibility' => 'friends'])->assertOk();
        $this->assertSame('friends', $user->fresh()->default_post_visibility);
        $this->getJson('/api/v1/settings')->assertOk()->assertJsonPath('data.privacy_settings.default_post_visibility', 'friends');
        $this->assertSame('friends', app(\App\Services\PrivacyCenterService::class)->payload($user->fresh())['privacy_settings']['default_post_visibility']);
        $this->actingAs($user)->put(route('auth.settings.update'), ['country' => 'DE', 'default_post_visibility' => 'private'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('private', $user->fresh()->default_post_visibility);
        $this->getJson('/api/v1/settings')->assertOk()->assertJsonPath('data.privacy_settings.default_post_visibility', 'private');
        $this->patchJson('/api/v1/settings', ['default_post_visibility' => 'invalid'])->assertUnprocessable();
    }
}
