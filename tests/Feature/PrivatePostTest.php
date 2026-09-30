<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrivatePostTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_posts_can_be_created_and_updated_without_retaining_club_scope(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);
        $payload = ['visibility' => 'private', 'content' => 'Private note', 'post_type' => 'normal', 'content_origin' => 'self', 'club_id' => $club->id];
        $id = $this->postJson('/api/v1/feed', $payload)->assertCreated()
            ->assertJsonPath('data.visibility', 'private')->assertJsonPath('data.club_id', null)->json('data.id');
        $this->putJson('/api/v1/posts/'.$id, [...$payload, 'content' => 'Updated private note'])
            ->assertOk()->assertJsonPath('data.content', 'Updated private note');

        $this->actingAs($owner)->post(route('auth.posts.store'), $payload)->assertRedirect();
        $post = Post::latest('id')->first();
        $this->assertSame('private', $post->visibility);
        $this->assertNull($post->club_id);
        $this->post(route('auth.posts.update', $post), [...$payload, '_method' => 'PUT'])->assertRedirect();

        $this->putJson('/api/v1/posts/'.$id, [...$payload, 'visibility' => 'public'])->assertOk();
        $this->putJson('/api/v1/posts/'.$id, $payload)->assertOk();
        $this->assertSame('private', Post::findOrFail($id)->visibility);
    }

    public function test_private_post_is_visible_only_to_its_author(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $owner->id, 'visibility' => 'private', 'content' => 'Hidden private note', 'moderation_status' => 'approved']);
        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/posts/'.$post->id)->assertOk();
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/posts/'.$post->id)->assertForbidden();
        $this->getJson('/api/v1/posts/'.$post->id.'/comments')->assertForbidden();
        $this->getJson('/api/v1/feed')->assertOk()->assertDontSee('Hidden private note');
        $this->actingAs($other)->get(route('auth.feed.index'))->assertOk()->assertDontSee('Hidden private note');
        \Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web');
        $other->assignRole('super_admin');
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($other)->allows('view', $post));
    }

    public function test_existing_media_is_protected_when_post_becomes_private(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::fake('local');
        config(['filesystems.uploads_disk' => 'public']);
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $post = Post::factory()->create(['user_id' => $owner->id, 'visibility' => 'public', 'image' => 'posts/photo.png']);
        $file = \App\Models\File::create([
            'user_id' => $owner->id, 'club_id' => $club->id, 'path' => 'posts/note.txt',
            'display_name' => 'note.txt', 'type' => 'text/plain', 'size' => 4,
        ]);
        $post->attachments()->create(['file_id' => $file->id]);
        \Illuminate\Support\Facades\Storage::disk('public')->put('posts/photo.png', 'photo');
        \Illuminate\Support\Facades\Storage::disk('public')->put('posts/note.txt', 'note');
        Sanctum::actingAs($owner);
        $this->putJson('/api/v1/posts/'.$post->id, [
            'content' => 'My private media', 'visibility' => 'private', 'post_type' => 'normal', 'content_origin' => 'self',
        ])->assertOk();
        $post->refresh();
        $file->refresh();
        $this->assertNull($file->club_id);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing('posts/photo.png');
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing('posts/note.txt');
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($post->image);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($file->path);
        $url = \App\Support\UploadStorage::url($file->path);
        $this->get($url)->assertOk();
        $this->getJson('/api/v1/posts/'.$post->id.'/image')->assertOk();
        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $this->getJson($url)->assertForbidden();
        $this->getJson('/api/v1/files/'.$file->id.'/preview')->assertForbidden();
        $this->getJson('/api/v1/posts/'.$post->id.'/image')->assertForbidden();
    }
}
