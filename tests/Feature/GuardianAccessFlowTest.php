<?php

namespace Tests\Feature;

use App\Models\GuardianAccessCode;
use App\Models\Notification as AppNotification;
use App\Models\User;
use App\Notifications\GuardianAccessCodeRequested;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GuardianAccessFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_signed_decision_links_open_review_without_deciding(): void
    {
        $minor = User::factory()->create([
            'birth_date' => now()->subYears(12)->toDateString(),
            'guardian_email' => 'review-parent@example.test',
            'guardian_consent_requested_at' => now(),
            'guardian_consent_token' => Str::random(64),
            'guardian_consent_version' => config('guardian.consent_version'),
        ]);

        foreach (['guardian-consent.approve-direct', 'guardian-consent.reject-direct'] as $route) {
            $url = URL::temporarySignedRoute($route, now()->addMinutes(5), [
                'token' => $minor->guardian_consent_token,
            ]);

            $this->get($url)->assertRedirect(route(
                'guardian-consent.show',
                $minor->guardian_consent_token,
                absolute: false,
            ));

            $minor->refresh();
            $this->assertNull($minor->guardian_consent_at);
            $this->assertNull($minor->guardian_consent_rejected_at);
            $this->assertNotNull($minor->guardian_consent_token);
        }
    }

    public function test_guardian_can_use_one_time_code_to_open_linked_child(): void
    {
        Notification::fake();

        $minor = User::factory()->create([
            'name' => 'Luca Code-Test',
            'birth_date' => now()->subYears(12)->toDateString(),
            'guardian_email' => 'code-parent@example.test',
            'guardian_consent_requested_at' => now(),
            'guardian_consent_version' => config('guardian.consent_version'),
        ]);

        $this->post(route('guardian-access.store'), [
            'email' => 'code-parent@example.test',
        ])->assertRedirect(route('guardian-access.verify', absolute: false));

        Notification::assertSentOnDemand(GuardianAccessCodeRequested::class);

        $this->get(route('guardian-access.verify'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('email', 'code-parent@example.test')
            );

        $accessCode = GuardianAccessCode::query()->sole();
        $accessCode->update(['code_hash' => Hash::make('123456')]);

        $this->post(route('guardian-access.confirm'), [
            'code' => '123456',
        ])->assertRedirect(route('guardian-access.children', absolute: false));

        $this->assertNotNull($accessCode->fresh()->used_at);

        $this->get(route('guardian-access.children'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('email', 'code-parent@example.test')
                ->where('hasAuthenticatedAccount', false)
                ->has('children', 1)
                ->where('children.0.id', $minor->id)
                ->where('children.0.consent_version', config('guardian.consent_version'))
            );
    }

    public function test_guardian_consent_approval_links_authenticated_guardian_and_notifies_child(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $guardian = User::factory()->create([
            'email' => 'parent@example.com',
            'birth_date' => now()->subYears(35)->toDateString(),
        ]);
        $minor = User::factory()->create([
            'birth_date' => now()->subYears(12)->toDateString(),
            'guardian_email' => 'parent@example.com',
            'guardian_consent_requested_at' => now(),
            'guardian_consent_token' => Str::random(64),
        ]);
        $minor->assignRole('minor_pending_consent');

        $this->get(route('guardian-consent.show', $minor->guardian_consent_token))
            ->assertOk()
            ->assertSee($minor->name)
            ->assertSee('Einwilligungsversion:')
            ->assertSee(config('guardian.consent_version'))
            ->assertSee('später im Elternbereich widerrufen');

        $this->actingAs($guardian)
            ->post(route('guardian-consent.approve', $minor->guardian_consent_token), [
                'guardian_confirmation' => '1',
            ])
            ->assertRedirect(route('auth.dashboard', absolute: false));

        $minor->refresh();
        $guardian->refresh();

        $this->assertSame($guardian->id, $minor->guardian_user_id);
        $this->assertNotNull($minor->guardian_consent_at);
        $this->assertNull($minor->guardian_consent_token);
        $this->assertSame(config('guardian.consent_version'), $minor->guardian_consent_version);
        $this->assertTrue($minor->hasRole('minor_player'));
        $this->assertFalse($minor->hasRole('minor_pending_consent'));
        $this->assertTrue($guardian->hasRole('guardian'));
        $this->assertDatabaseHas('notifications', [
            'user_id' => $minor->id,
            'type' => 'guardian.consent_approved',
        ]);
    }

    public function test_guardian_account_creation_links_existing_children(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $minor = User::factory()->create([
            'birth_date' => now()->subYears(11)->toDateString(),
            'guardian_email' => 'parent@example.com',
            'guardian_user_id' => null,
            'guardian_consent_at' => now(),
        ]);
        $minor->assignRole('minor_player');

        $this->withSession([
            'guardian_access_verified_email' => 'parent@example.com',
            'guardian_access_verified_at' => now()->toIso8601String(),
        ])
            ->post(route('guardian-access.account.store'), [
                'first_name' => 'Parent',
                'last_name' => 'Example',
                'birth_date' => now()->subYears(35)->toDateString(),
                'country' => 'DE',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertRedirect(route('login', absolute: false));

        $guardian = User::query()->where('email', 'parent@example.com')->firstOrFail();

        $this->assertTrue($guardian->hasRole('guardian'));
        $this->assertSame($guardian->id, $minor->fresh()->guardian_user_id);
    }

    public function test_guardian_consent_rejection_keeps_minor_pending_and_notifies_child(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $minor = User::factory()->create([
            'birth_date' => now()->subYears(12)->toDateString(),
            'guardian_email' => 'parent@example.com',
            'guardian_consent_requested_at' => now(),
            'guardian_consent_token' => Str::random(64),
        ]);
        $minor->assignRole('minor_pending_consent');

        $this->delete(route('guardian-consent.reject', $minor->guardian_consent_token))
            ->assertRedirect(route('login', absolute: false));

        $minor->refresh();

        $this->assertNull($minor->guardian_consent_at);
        $this->assertNotNull($minor->guardian_consent_rejected_at);
        $this->assertNull($minor->guardian_consent_token);
        $this->assertTrue($minor->hasRole('minor_pending_consent'));
        $this->assertFalse($minor->hasRole('minor_player'));
        $this->assertDatabaseHas('notifications', [
            'user_id' => $minor->id,
            'type' => 'guardian.consent_rejected',
        ]);
    }

    public function test_guardian_children_area_revokes_and_reapproves_consent_with_notifications(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $guardian = User::factory()->create([
            'email' => 'parent@example.com',
            'birth_date' => now()->subYears(35)->toDateString(),
        ]);
        $guardian->assignRole('guardian');

        $minor = User::factory()->create([
            'birth_date' => now()->subYears(13)->toDateString(),
            'guardian_email' => 'parent@example.com',
            'guardian_user_id' => $guardian->id,
            'guardian_consent_at' => now(),
        ]);
        $minor->assignRole('minor_player');

        $otherMinor = User::factory()->create([
            'birth_date' => now()->subYears(12)->toDateString(),
            'guardian_email' => 'other-parent@example.com',
            'guardian_consent_at' => now(),
        ]);
        $otherMinor->assignRole('minor_player');

        $this->actingAs($guardian)
            ->get(route('guardian-access.children'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('email', 'parent@example.com')
                ->where('hasAuthenticatedAccount', true)
                ->has('children', 1)
                ->where('children.0.id', $minor->id)
            );

        $this->actingAs($guardian)
            ->put(route('guardian-access.children.revoke', $otherMinor))
            ->assertRedirect()
            ->assertSessionHasErrors('code');

        $this->actingAs($guardian)
            ->put(route('guardian-access.children.revoke', $minor))
            ->assertRedirect();

        $minor->refresh();

        $this->assertNull($minor->guardian_consent_at);
        $this->assertNotNull($minor->guardian_consent_revoked_at);
        $this->assertTrue($minor->hasRole('minor_pending_consent'));
        $this->assertFalse($minor->hasRole('minor_player'));
        $this->assertDatabaseHas('notifications', [
            'user_id' => $minor->id,
            'type' => 'guardian.consent_revoked',
        ]);

        $this->actingAs($guardian)
            ->put(route('guardian-access.children.approve', $minor))
            ->assertRedirect();

        $minor->refresh();

        $this->assertNotNull($minor->guardian_consent_at);
        $this->assertNull($minor->guardian_consent_revoked_at);
        $this->assertTrue($minor->hasRole('minor_player'));
        $this->assertFalse($minor->hasRole('minor_pending_consent'));
        $this->assertSame(1, AppNotification::query()
            ->where('user_id', $minor->id)
            ->where('type', 'guardian.consent_revoked')
            ->count());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $minor->id,
            'type' => 'guardian.consent_approved',
        ]);
    }
}
