<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\User;
use App\Notifications\GuardianConsentRequested;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MinorSafetyConceptTest extends TestCase
{
    use RefreshDatabase;

    public function test_minor_registration_sets_private_visibility_and_friend_only_messages(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $this->seed(RolesPermissionsSeeder::class);
        Notification::fake();

        $this->post('/register', [
            'first_name' => 'Minor',
            'last_name' => 'Safe',
            'email' => 'minor-safe@example.test',
            'country' => 'DE',
            'birth_date' => now()->subYears(15)->toDateString(),
            'guardian_email' => 'parent-safe@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
        ])->assertRedirect(config('fortify.home'));

        $minor = User::where('email', 'minor-safe@example.test')->firstOrFail();

        $this->assertSame('private', $minor->profile_visibility);
        $this->assertSame('friends', $minor->direct_message_privacy);
        $this->assertSame('friends', $minor->friend_request_privacy);
        $this->assertTrue($minor->hasRole('minor_pending_consent'));
        Notification::assertSentOnDemand(GuardianConsentRequested::class);
    }

    public function test_pending_minor_can_reach_consent_and_account_safety_but_not_social_api(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        Notification::fake();

        $minor = User::factory()->create([
            'birth_date' => now()->subYears(15)->toDateString(),
            'guardian_email' => 'parent-gate@example.test',
            'guardian_consent_requested_at' => now(),
            'guardian_consent_token' => Str::random(64),
            'profile_visibility' => 'private',
            'direct_message_privacy' => 'friends',
            'friend_request_privacy' => 'friends',
        ]);
        $minor->assignRole('minor_pending_consent');

        $this->actingAs($minor)
            ->get('/feed')
            ->assertRedirect(route('guardian-consent.pending'));

        Sanctum::actingAs($minor);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'minor_pending_consent');

        $this->getJson('/api/v1/guardian/consent')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.privacy.direct_messages_enabled', false);

        $this->postJson('/api/v1/feed', [
            'content' => 'Dieser Beitrag darf noch nicht angelegt werden.',
            'visibility' => 'public',
            'post_type' => 'normal',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden')
            ->assertJsonPath('message', __('guardian.validation.consent_required'));

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_minor_cannot_make_profile_public_or_messages_everyone_via_api_settings(): void
    {
        $minor = User::factory()->create([
            'birth_date' => now()->subYears(15)->toDateString(),
            'guardian_email' => 'parent@example.test',
            'guardian_consent_at' => now(),
            'profile_visibility' => 'private',
            'direct_message_privacy' => 'friends',
            'friend_request_privacy' => 'friends',
            'country' => 'DE',
        ]);

        Sanctum::actingAs($minor);

        $this->patchJson('/api/v1/settings', [
            'country' => 'DE',
            'profile_visibility' => 'public',
            'direct_message_privacy' => 'everyone',
            'friend_request_privacy' => 'everyone',
            'ads_personalization_consent' => true,
            'ads_measurement_consent' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.profile_visibility', 'private')
            ->assertJsonPath('data.direct_message_privacy', 'friends')
            ->assertJsonPath('data.friend_request_privacy', 'friends');

        $fresh = $minor->fresh();

        $this->assertSame('private', $fresh->profile_visibility);
        $this->assertSame('friends', $fresh->direct_message_privacy);
        $this->assertSame('friends', $fresh->friend_request_privacy);
    }

    public function test_minor_profile_visibility_and_direct_messages_require_consent_and_friendship(): void
    {
        $minor = User::factory()->create([
            'birth_date' => now()->subYears(15)->toDateString(),
            'guardian_email' => 'parent@example.test',
            'guardian_consent_at' => now(),
            'profile_visibility' => 'public',
            'direct_message_privacy' => 'everyone',
        ]);
        $viewer = User::factory()->create();
        $pendingMinor = User::factory()->create([
            'birth_date' => now()->subYears(15)->toDateString(),
            'guardian_email' => 'parent@example.test',
            'profile_visibility' => 'public',
            'direct_message_privacy' => 'everyone',
        ]);

        $this->assertFalse($minor->isProfileVisibleTo($viewer));
        $this->assertFalse($minor->allowsDirectMessagesFrom($viewer));

        $this->befriend($minor, $viewer);

        $this->assertTrue($minor->isProfileVisibleTo($viewer));
        $this->assertTrue($minor->allowsDirectMessagesFrom($viewer));

        $this->befriend($pendingMinor, $viewer);

        $this->assertFalse($pendingMinor->allowsDirectMessagesFrom($viewer));
    }

    public function test_direct_chat_with_pending_minor_is_blocked_in_web_and_api(): void
    {
        $actor = User::factory()->create();
        $pendingMinor = User::factory()->create([
            'birth_date' => now()->subYears(15)->toDateString(),
            'guardian_email' => 'parent@example.test',
            'direct_message_privacy' => 'everyone',
        ]);
        $approvedMinor = User::factory()->create([
            'birth_date' => now()->subYears(15)->toDateString(),
            'guardian_email' => 'parent@example.test',
            'guardian_consent_at' => now(),
            'direct_message_privacy' => 'friends',
        ]);

        $this->befriend($actor, $pendingMinor);
        $this->befriend($actor, $approvedMinor);

        $this->actingAs($actor)
            ->post(route('auth.conversations.store'), [
                'type' => 'direct',
                'participant_ids' => [$pendingMinor->id],
                'message' => 'Hallo.',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('conversation_users', [
            'user_id' => $pendingMinor->id,
        ]);

        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/chat/conversations', [
            'type' => 'direct',
            'participant_ids' => [$pendingMinor->id],
            'message' => 'Hallo API.',
        ])->assertForbidden();

        $this->postJson('/api/v1/chat/conversations', [
            'type' => 'direct',
            'participant_ids' => [$approvedMinor->id],
            'message' => 'Training nur nach Freigabe.',
        ])->assertCreated();
    }

    private function befriend(User $first, User $second): void
    {
        Friendship::create(['user_id' => $first->id, 'friend_id' => $second->id]);
        Friendship::create(['user_id' => $second->id, 'friend_id' => $first->id]);
    }
}
