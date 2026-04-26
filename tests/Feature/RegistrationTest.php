<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_screen_cannot_be_rendered_if_support_is_disabled(): void
    {
        if (Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_new_users_can_register(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $this->seed(RolesPermissionsSeeder::class);
        Mail::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'birth_date' => now()->subYears(16)->subDay()->toDateString(),
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->hasRole('player'));
        Mail::assertNothingSent();
        $response->assertRedirect(route('auth.dashboard', absolute: false));
    }

    public function test_minor_users_need_guardian_email(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $this->seed(RolesPermissionsSeeder::class);
        Mail::fake();

        $response = $this->post('/register', [
            'name' => 'Minor User',
            'email' => 'minor@example.com',
            'birth_date' => now()->subYears(15)->toDateString(),
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $response->assertSessionHasErrors('guardian_email');
        $this->assertGuest();
    }

    public function test_minor_users_are_registered_pending_guardian_consent(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $this->seed(RolesPermissionsSeeder::class);

        $response = $this->post('/register', [
            'name' => 'Minor User',
            'email' => 'minor@example.com',
            'birth_date' => now()->subYears(15)->toDateString(),
            'guardian_email' => 'parent@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->hasRole('minor_pending_consent'));
        $this->assertDatabaseHas('users', [
            'email' => 'minor@example.com',
            'guardian_email' => 'parent@example.com',
        ]);
        $this->assertNotNull(auth()->user()->guardian_consent_token);
        $response->assertRedirect(route('auth.dashboard', absolute: false));
    }

    public function test_guardian_can_approve_minor_registration(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $minor = User::factory()->create([
            'birth_date' => now()->subYears(15)->toDateString(),
            'guardian_email' => 'parent@example.com',
            'guardian_consent_requested_at' => now(),
            'guardian_consent_token' => Str::random(64),
        ]);
        $minor->assignRole('minor_pending_consent');

        $response = $this->post(route('guardian-consent.approve', $minor->guardian_consent_token), [
            'guardian_confirmation' => '1',
        ]);

        $response->assertRedirect(route('login', absolute: false));

        $minor->refresh();
        $this->assertNotNull($minor->guardian_consent_at);
        $this->assertNull($minor->guardian_consent_token);
        $this->assertFalse($minor->hasRole('minor_pending_consent'));
        $this->assertTrue($minor->hasRole('minor_player'));
    }
}
