<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommunicationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_club_and_coach_roles_do_not_open_private_conversations_or_foreign_comments(): void
    {
        $participant = User::factory()->create();
        $postAuthor = User::factory()->create();
        $commentAuthor = User::factory()->create();
        $globalRoleHolder = User::factory()->create();
        Role::findOrCreate('club_admin')->users()->attach($globalRoleHolder);
        Role::findOrCreate('coach')->users()->attach($globalRoleHolder);

        $conversation = Conversation::query()->create(['type' => 'direct']);
        $conversation->users()->attach($participant->id, ['joined_at' => now()]);
        $post = Post::factory()->create([
            'user_id' => $postAuthor->id,
            'visibility' => 'public',
            'moderation_status' => 'approved',
        ]);
        $comment = Comment::query()->create([
            'post_id' => $post->id,
            'user_id' => $commentAuthor->id,
            'content' => 'Private Verantwortungsgrenzen',
            'moderation_status' => 'approved',
        ]);

        $this->assertTrue(Gate::forUser($participant)->allows('view', $conversation));
        $this->assertFalse(Gate::forUser($globalRoleHolder)->allows('view', $conversation));
        $this->assertTrue(Gate::forUser($commentAuthor)->allows('update', $comment));
        $this->assertTrue(Gate::forUser($commentAuthor)->allows('delete', $comment));
        $this->assertTrue(Gate::forUser($postAuthor)->allows('delete', $comment));
        $this->assertFalse(Gate::forUser($globalRoleHolder)->allows('update', $comment));
        $this->assertFalse(Gate::forUser($globalRoleHolder)->allows('delete', $comment));

        Sanctum::actingAs($globalRoleHolder);
        $this->putJson("/api/v1/comments/{$comment->id}", ['content' => 'Unzulässige Änderung'])
            ->assertForbidden();
        $this->deleteJson("/api/v1/comments/{$comment->id}")->assertForbidden();

        Sanctum::actingAs($postAuthor);
        $this->deleteJson("/api/v1/comments/{$comment->id}")->assertNoContent();
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }
}
