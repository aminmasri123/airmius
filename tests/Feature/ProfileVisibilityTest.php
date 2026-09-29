<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\User;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_private_and_friends_are_distinct_and_fail_closed(): void
    {
        $owner = User::factory()->create(['birth_date' => '1990-01-01']);
        $friend = User::factory()->create();
        $stranger = User::factory()->create();
        Friendship::create(['user_id' => $owner->id, 'friend_id' => $friend->id]);
        Friendship::create(['user_id' => $friend->id, 'friend_id' => $owner->id]);
        $owner->followers()->create(['follower_id' => $stranger->id]);

        foreach (['public' => [true, true], 'private' => [false, false], 'friends' => [true, false], 'invalid' => [false, false]] as $value => [$friendAllowed, $strangerAllowed]) {
            $owner->profile_visibility = $value;
            $this->assertTrue($owner->isProfileVisibleTo($owner));
            $this->assertSame($friendAllowed, $owner->isProfileVisibleTo($friend), $value);
            $this->assertSame($strangerAllowed, $owner->isProfileVisibleTo($stranger), $value);
            $this->assertSame($value === 'public', $owner->isProfileVisibleTo(null));
        }
        $owner->profile_visibility = 'friends';
        $owner->blockedUsers()->create(['blocked_user_id' => $friend->id]);
        $this->assertFalse($owner->isProfileVisibleTo($friend));
    }

    public function test_all_three_values_can_be_saved_from_web_and_mobile(): void
    {
        $user = User::factory()->create(['birth_date' => '1990-01-01', 'country' => 'DE']);
        Sanctum::actingAs($user);
        foreach (['public', 'private', 'friends'] as $value) {
            $this->patchJson('/api/v1/settings', ['country' => 'DE', 'profile_visibility' => $value])->assertOk()->assertJsonPath('data.profile_visibility', $value);
            $this->putJson('/api/v1/me/profile', [
                'first_name' => 'Test', 'last_name' => 'User', 'country' => 'DE',
                'birth_date' => '1990-01-01', 'gender' => 'not_specified', 'profile_visibility' => $value,
            ])->assertOk()->assertJsonPath('data.profile_visibility', $value);
            $this->actingAs($user)->put('/user/profile-information', [
                'first_name' => 'Test', 'last_name' => 'User', 'email' => $user->email, 'profile_visibility' => $value,
            ])->assertSessionHasNoErrors();
            $this->assertSame($value, $user->fresh()->profile_visibility);
        }
        $this->patchJson('/api/v1/settings', ['country' => 'DE', 'profile_visibility' => 'invalid'])->assertUnprocessable();
    }

    public function test_profile_posts_require_friendship_and_become_hidden_when_private(): void
    {
        $owner = User::factory()->create(['profile_visibility' => 'friends', 'birth_date' => '1990-01-01']);
        $viewer = User::factory()->create();
        Post::factory()->create(['user_id' => $owner->id, 'visibility' => 'public', 'moderation_status' => 'approved']);
        Sanctum::actingAs($viewer);
        $this->getJson('/api/v1/users/'.$owner->id.'/posts')->assertOk()->assertJsonCount(0, 'data');
        Friendship::create(['user_id' => $owner->id, 'friend_id' => $viewer->id]);
        Friendship::create(['user_id' => $viewer->id, 'friend_id' => $owner->id]);
        $this->getJson('/api/v1/users/'.$owner->id.'/posts')->assertOk()->assertJsonCount(1, 'data');
        $owner->update(['profile_visibility' => 'private']);
        $this->getJson('/api/v1/users/'.$owner->id.'/posts')->assertOk()->assertJsonCount(0, 'data');
    }
}
