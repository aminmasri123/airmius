<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Notification as AppNotification;
use App\Models\TrainingLog;
use App\Models\User;
use App\Notifications\GuardianConsentRequested;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileGuardianApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_sees_only_linked_minor_children_without_secret_tokens(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $guardian = $this->guardian('Parent@Example.test');
        $linkedById = $this->minor([
            'name' => 'Linked Child',
            'guardian_user_id' => $guardian->id,
            'guardian_email' => 'different@example.test',
            'guardian_consent_at' => now(),
            'profile_visibility' => 'public',
            'direct_message_privacy' => 'everyone',
            'friend_request_privacy' => 'everyone',
        ], 'minor_player');
        $linkedByEmail = $this->minor([
            'name' => 'Email Child',
            'guardian_email' => ' parent@example.test ',
            'guardian_consent_token' => Str::random(64),
        ]);
        $foreignChild = $this->minor([
            'name' => 'Foreign Child',
            'guardian_email' => 'foreign@example.test',
        ]);

        Sanctum::actingAs($guardian);

        $this->getJson('/api/v1/guardian/children')
            ->assertOk()
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.summary.children', 2)
            ->assertJsonPath('data.summary.approved', 1)
            ->assertJsonPath('data.summary.pending', 1)
            ->assertJsonPath('data.children.0.id', $linkedByEmail->id)
            ->assertJsonPath('data.children.1.id', $linkedById->id)
            ->assertJsonPath('data.children.1.privacy.profile_visibility', 'private')
            ->assertJsonPath('data.children.1.privacy.direct_message_privacy', 'friends')
            ->assertJsonPath('data.children.1.privacy.friend_request_privacy', 'friends')
            ->assertJsonMissingPath('data.children.0.guardian_consent_token')
            ->assertJsonMissing(['id' => $foreignChild->id]);

        $this->assertSame('private', $linkedById->fresh()->profile_visibility);
        $this->assertSame('friends', $linkedById->fresh()->direct_message_privacy);
    }

    public function test_guardian_approval_revoke_and_resend_lifecycle_is_enforced(): void
    {
        Notification::fake();
        $this->seed(RolesPermissionsSeeder::class);

        $guardian = $this->guardian();
        $guardian->forceFill(['language' => 'fr'])->save();
        $child = $this->minor([
            'language' => 'ar',
            'guardian_user_id' => $guardian->id,
            'guardian_email' => $guardian->email,
            'guardian_consent_requested_at' => now()->subMinutes(5),
            'guardian_consent_token' => Str::random(64),
        ]);

        Sanctum::actingAs($guardian);

        $this->withHeader('X-App-Locale', 'fr')
            ->postJson("/api/v1/guardian/children/{$child->id}/approve")
            ->assertOk()
            ->assertJsonPath('message', 'guardian_consent_approved')
            ->assertJsonPath('message_text', __('guardian.responses.consent_approved', locale: 'fr'))
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.privacy.direct_messages_enabled', true)
            ->assertJsonMissingPath('data.guardian_consent_token');

        $child->refresh();
        $this->assertSame($guardian->id, $child->guardian_user_id);
        $this->assertNotNull($child->guardian_consent_at);
        $this->assertNull($child->guardian_consent_token);
        $this->assertTrue($child->hasRole('minor_player'));
        $this->assertFalse($child->hasRole('minor_pending_consent'));

        $approvalNotification = AppNotification::query()
            ->where('user_id', $child->id)
            ->where('type', 'guardian.consent_approved')
            ->firstOrFail();
        $this->assertSame('ar', data_get($approvalNotification->data, 'locale'));
        $this->assertSame(
            __('guardian.notifications.approved_title', locale: 'ar'),
            data_get($approvalNotification->data, 'title'),
        );

        $this->withHeader('X-App-Locale', 'fr')
            ->postJson("/api/v1/guardian/children/{$child->id}/revoke")
            ->assertOk()
            ->assertJsonPath('message', 'guardian_consent_revoked')
            ->assertJsonPath('message_text', __('guardian.responses.consent_revoked', locale: 'fr'))
            ->assertJsonPath('data.status', 'revoked')
            ->assertJsonPath('data.privacy.direct_messages_enabled', false);

        $child->refresh();
        $this->assertNull($child->guardian_consent_at);
        $this->assertNotNull($child->guardian_consent_revoked_at);
        $this->assertTrue($child->hasRole('minor_pending_consent'));
        $this->assertFalse($child->hasRole('minor_player'));

        $revocationNotification = AppNotification::query()
            ->where('user_id', $child->id)
            ->where('type', 'guardian.consent_revoked')
            ->firstOrFail();
        $this->assertSame('ar', data_get($revocationNotification->data, 'locale'));
        $this->assertSame(
            __('guardian.notifications.revoked_title', locale: 'ar'),
            data_get($revocationNotification->data, 'title'),
        );

        $this->postJson("/api/v1/guardian/children/{$child->id}/resend")
            ->assertUnprocessable();

        $child->forceFill(['guardian_consent_requested_at' => now()->subMinutes(2)])->save();

        $this->withHeader('X-App-Locale', 'fr')
            ->postJson("/api/v1/guardian/children/{$child->id}/resend")
            ->assertOk()
            ->assertJsonPath('message', 'guardian_consent_resent')
            ->assertJsonPath('message_text', __('guardian.responses.consent_resent', locale: 'fr'))
            ->assertJsonPath('data.status', 'revoked')
            ->assertJsonMissingPath('data.guardian_consent_token');

        $this->assertNotNull($child->fresh()->guardian_consent_token);
        Notification::assertSentTo($guardian, GuardianConsentRequested::class);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $child->id,
            'type' => 'guardian.consent_approved',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $child->id,
            'type' => 'guardian.consent_revoked',
        ]);
    }

    public function test_guardian_child_overview_exposes_only_safe_aggregates_after_consent(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $guardian = $this->guardian();
        $approved = $this->minor([
            'name' => 'Approved Child',
            'guardian_user_id' => $guardian->id,
            'guardian_email' => $guardian->email,
            'guardian_consent_at' => now(),
        ], 'minor_player');
        $pending = $this->minor([
            'name' => 'Pending Child',
            'guardian_user_id' => $guardian->id,
            'guardian_email' => $guardian->email,
        ]);
        $foreignGuardian = $this->guardian('foreign@example.test');
        $foreign = $this->minor([
            'guardian_user_id' => $foreignGuardian->id,
            'guardian_email' => $foreignGuardian->email,
        ]);

        $event = Event::query()->create([
            'user_id' => $guardian->id,
            'title' => 'Geschütztes Training',
            'type' => 'training',
            'visibility' => 'private',
            'status' => 'scheduled',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'location_name' => 'Sporthalle',
            'location_street' => 'Private Straße 7',
            'location_city' => 'Berlin',
            'notes' => 'Private Trainernotiz',
        ]);
        EventParticipant::query()->create([
            'event_id' => $event->id,
            'user_id' => $approved->id,
            'status' => 'yes',
        ]);
        TrainingLog::query()->create([
            'user_id' => $approved->id,
            'created_by' => $approved->id,
            'title' => 'Private Einheit',
            'status' => 'completed',
            'performed_at' => now()->subDays(3),
            'duration_minutes' => 45,
            'distance_meters' => 8500,
            'calories' => 420,
            'notes' => 'Private Gesundheitsnotiz',
        ]);
        TrainingLog::query()->create([
            'user_id' => $approved->id,
            'created_by' => $approved->id,
            'title' => 'Alter Eintrag',
            'status' => 'completed',
            'performed_at' => now()->subDays(40),
            'duration_minutes' => 120,
            'distance_meters' => 20000,
            'calories' => 900,
        ]);

        Sanctum::actingAs($guardian);

        $this->getJson("/api/v1/guardian/children/{$approved->id}")
            ->assertOk()
            ->assertJsonPath('data.child.id', $approved->id)
            ->assertJsonPath('data.access.approved', true)
            ->assertJsonPath('data.access.scope', 'safe_aggregates')
            ->assertJsonPath('data.access.private_details_hidden', true)
            ->assertJsonPath('data.access.notes_visible', false)
            ->assertJsonPath('data.upcoming_events.0.title', 'Geschütztes Training')
            ->assertJsonPath('data.upcoming_events.0.location_name', 'Sporthalle')
            ->assertJsonPath('data.upcoming_events.0.participation_status', 'yes')
            ->assertJsonMissingPath('data.upcoming_events.0.location_street')
            ->assertJsonMissingPath('data.upcoming_events.0.notes')
            ->assertJsonPath('data.training.period_days', 28)
            ->assertJsonPath('data.training.sessions', 1)
            ->assertJsonPath('data.training.duration_minutes', 45)
            ->assertJsonPath('data.training.distance_meters', 8500)
            ->assertJsonPath('data.training.calories', 420)
            ->assertJsonPath('data.training.private_notes_hidden', true)
            ->assertJsonMissing(['notes' => 'Private Gesundheitsnotiz']);

        $this->getJson("/api/v1/guardian/children/{$pending->id}")
            ->assertOk()
            ->assertJsonPath('data.access.approved', false)
            ->assertJsonPath('data.access.scope', 'consent_only')
            ->assertJsonPath('data.upcoming_events', [])
            ->assertJsonPath('data.training.sessions', 0)
            ->assertJsonPath('data.training.private_notes_hidden', true);

        $this->getJson("/api/v1/guardian/children/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_foreign_children_adults_and_non_guardians_cannot_be_managed(): void
    {
        Notification::fake();
        $this->seed(RolesPermissionsSeeder::class);

        $guardian = $this->guardian('guardian@example.test');
        $otherGuardian = $this->guardian('other@example.test');
        $foreignChild = $this->minor([
            'guardian_user_id' => $otherGuardian->id,
            'guardian_email' => $otherGuardian->email,
        ]);
        $adultChild = User::factory()->create([
            'birth_date' => now()->subYears(20)->toDateString(),
            'guardian_user_id' => $guardian->id,
            'guardian_email' => $guardian->email,
        ]);
        $regularUser = User::factory()->create();
        $regularUser->assignRole('player');
        $originalChild = $foreignChild->fresh()->getAttributes();

        Sanctum::actingAs($guardian);
        $this->postJson("/api/v1/guardian/children/{$foreignChild->id}/approve")
            ->assertNotFound();
        $this->postJson("/api/v1/guardian/children/{$foreignChild->id}/revoke")
            ->assertNotFound();
        $this->postJson("/api/v1/guardian/children/{$foreignChild->id}/resend")
            ->assertNotFound();
        $this->assertSame($originalChild, $foreignChild->fresh()->getAttributes());
        Notification::assertNothingSent();
        $this->postJson("/api/v1/guardian/children/{$adultChild->id}/revoke")
            ->assertNotFound();

        Sanctum::actingAs($regularUser);
        $this->getJson('/api/v1/guardian/children')->assertForbidden();
        $this->postJson("/api/v1/guardian/children/{$foreignChild->id}/approve")
            ->assertForbidden();
    }

    public function test_minor_can_view_status_and_securely_resend_own_request(): void
    {
        Notification::fake();
        $this->seed(RolesPermissionsSeeder::class);

        $guardian = $this->guardian();
        $minor = $this->minor([
            'guardian_email' => $guardian->email,
            'guardian_consent_requested_at' => now()->subMinutes(2),
            'guardian_consent_token' => Str::random(64),
            'profile_visibility' => 'public',
        ]);

        Sanctum::actingAs($minor);

        $this->getJson('/api/v1/guardian/consent')
            ->assertOk()
            ->assertJsonPath('data.required', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.consent_version', config('guardian.consent_version'))
            ->assertJsonPath('data.guardian_email', $guardian->email)
            ->assertJsonPath('data.privacy.profile_visibility', 'private')
            ->assertJsonMissingPath('data.guardian_consent_token');

        $this->postJson('/api/v1/guardian/consent/resend')
            ->assertOk()
            ->assertJsonPath('message', 'guardian_consent_resent')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonMissingPath('data.guardian_consent_token');

        Notification::assertSentTo($guardian, GuardianConsentRequested::class);

        $adult = User::factory()->create([
            'birth_date' => now()->subYears(25)->toDateString(),
        ]);
        $adult->assignRole('player');
        Sanctum::actingAs($adult);

        $this->getJson('/api/v1/guardian/consent')
            ->assertOk()
            ->assertJsonPath('data.required', false)
            ->assertJsonPath('data.status', 'not_required');
        $this->postJson('/api/v1/guardian/consent/resend')
            ->assertUnprocessable();
    }

    private function guardian(string $email = 'parent@example.test'): User
    {
        $guardian = User::factory()->create([
            'email' => $email,
            'birth_date' => now()->subYears(35)->toDateString(),
        ]);
        $guardian->assignRole('guardian');

        return $guardian;
    }

    private function minor(array $attributes = [], string $role = 'minor_pending_consent'): User
    {
        $minor = User::factory()->create([
            'birth_date' => now()->subYears(12)->toDateString(),
            ...$attributes,
        ]);
        $minor->assignRole($role);

        return $minor;
    }
}
