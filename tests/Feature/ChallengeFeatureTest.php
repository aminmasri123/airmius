<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\Club;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChallengeFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_friends_can_send_accept_complete_and_comment_on_a_daily_challenge(): void
    {
        $creator = User::factory()->create();
        $friend = User::factory()->create();
        Friendship::query()->create(['user_id' => $creator->id, 'friend_id' => $friend->id]);
        Friendship::query()->create(['user_id' => $friend->id, 'friend_id' => $creator->id]);
        $sport = Sport::query()->create(['name' => 'Gehen', 'slug' => 'walking', 'category' => 'endurance', 'is_active' => true]);

        Sanctum::actingAs($creator);
        $challengeId = $this->postJson('/api/v1/challenges', [
            'title' => 'Jeden Tag 10.000 Schritte',
            'description' => 'Gemeinsam bleiben wir in Bewegung.',
            'sport_id' => $sport->id,
            'visibility' => 'invite_only',
            'metric' => 'steps',
            'target_value' => 10000,
            'unit' => 'Schritte',
            'frequency' => 'daily',
            'verification' => 'manual',
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addDays(6)->toDateString(),
            'invitee_ids' => [$friend->id],
        ])->assertCreated()
            ->assertJsonPath('data.my_participation.status', 'accepted')
            ->assertJsonPath('data.participants.1.status', 'pending')
            ->json('data.id');

        Sanctum::actingAs($friend);
        $this->getJson('/api/v1/challenges')->assertOk()->assertJsonPath('data.0.id', $challengeId);
        $this->putJson("/api/v1/challenges/{$challengeId}/invitation", ['status' => 'accepted'])
            ->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->putJson("/api/v1/challenges/{$challengeId}/check-ins/".today()->toDateString(), [
            'value' => 10250,
            'note' => 'Nach dem Spaziergang geschafft.',
        ])->assertOk()->assertJsonPath('data.completed', true);
        $this->postJson("/api/v1/challenges/{$challengeId}/comments", ['content' => 'Tag eins ist geschafft!'])
            ->assertCreated()->assertJsonPath('data.content', 'Tag eins ist geschafft!');

        $this->getJson("/api/v1/challenges/{$challengeId}")
            ->assertOk()
            ->assertJsonPath('data.progress.completed', 1)
            ->assertJsonPath('data.my_checkins.0.value', 10250)
            ->assertJsonPath('data.comments.0.content', 'Tag eins ist geschafft!');
    }

    public function test_private_challenges_are_not_visible_to_uninvited_users(): void
    {
        $creator = User::factory()->create();
        $stranger = User::factory()->create();
        Sanctum::actingAs($creator);
        $challengeId = $this->postJson('/api/v1/challenges', [
            'title' => 'Private Challenge',
            'visibility' => 'invite_only',
            'metric' => 'sessions',
            'target_value' => 1,
            'frequency' => 'daily',
            'verification' => 'manual',
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addDay()->toDateString(),
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($stranger);
        $this->getJson("/api/v1/challenges/{$challengeId}")->assertNotFound();
        $this->getJson('/api/v1/challenges')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_regular_users_cannot_publish_public_challenges(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/challenges', [
            'title' => 'Public Challenge',
            'visibility' => 'public',
            'metric' => 'steps',
            'target_value' => 10000,
            'frequency' => 'daily',
            'verification' => 'manual',
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addWeek()->toDateString(),
        ])->assertForbidden();
    }

    public function test_admin_can_publish_a_public_challenge_that_any_user_can_join(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('admin')->users()->attach($admin);
        $member = User::factory()->create();
        Sanctum::actingAs($admin);
        $challengeId = $this->postJson('/api/v1/challenges', [
            'title' => 'Airmius Schritte-Woche',
            'visibility' => 'public',
            'metric' => 'steps',
            'target_value' => 10000,
            'frequency' => 'daily',
            'verification' => 'manual',
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addWeek()->toDateString(),
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/challenges')->assertOk()->assertJsonPath('data.0.can_join', true);
        $this->postJson("/api/v1/challenges/{$challengeId}/join")
            ->assertOk()->assertJsonPath('data.status', 'accepted');
    }

    public function test_club_challenges_are_only_visible_inside_the_club(): void
    {
        $trainer = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $trainer->id]);
        $club->users()->attach($member->id, ['role' => 'member']);

        Sanctum::actingAs($trainer);
        $challengeId = $this->postJson('/api/v1/challenges', [
            'title' => 'Interne Vereins-Challenge',
            'visibility' => 'club',
            'club_id' => $club->id,
            'metric' => 'duration_minutes',
            'target_value' => 30,
            'frequency' => 'daily',
            'verification' => 'manual',
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addDays(3)->toDateString(),
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/challenges/{$challengeId}")->assertOk();
        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/challenges/{$challengeId}")->assertNotFound();
    }
}
