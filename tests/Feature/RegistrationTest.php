<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserRoleApplication;
use App\Notifications\AccountWelcomeNotification;
use App\Notifications\GuardianConsentRequested;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Laravel\Sanctum\Sanctum;
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

    public function test_registration_required_date_error_is_localized_for_every_supported_locale(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $expectedMessages = [
            'de' => 'Geburtsdatum ist ein Pflichtfeld.',
            'en' => 'The date of birth field is required.',
            'fr' => 'Le champ date de naissance est obligatoire.',
            'ar' => 'حقل تاريخ الميلاد مطلوب.',
        ];

        foreach ($expectedMessages as $locale => $expectedMessage) {
            $this->withHeader('X-App-Locale', $locale)
                ->postJson('/api/v1/auth/register', [
                    'first_name' => 'Locale',
                    'last_name' => strtoupper($locale),
                    'email' => "registration-{$locale}@example.test",
                    'country' => 'DE',
                    'gender' => 'not_specified',
                    'password' => 'Airmius-QA-2026!',
                    'password_confirmation' => 'Airmius-QA-2026!',
                    'terms' => true,
                    'device_name' => 'airmius-localization-test',
                ])
                ->assertUnprocessable()
                ->assertJsonPath('errors.birth_date.0', $expectedMessage);
        }
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
        Notification::fake();

        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'country' => 'DE',
            'birth_date' => now()->subYears(16)->subDay()->toDateString(),
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
        ]);

        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->hasRole('player'));
        Notification::assertSentTo(auth()->user(), AccountWelcomeNotification::class);
        $response->assertRedirect(config('fortify.home'));
    }

    public function test_new_users_can_register_through_mobile_api(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $this->seed(RolesPermissionsSeeder::class);
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Mobile',
            'last_name' => 'User',
            'email' => 'mobile@example.com',
            'country' => 'DE',
            'birth_date' => now()->subYears(16)->subDay()->toDateString(),
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
            'device_name' => 'airmius-mobile-test',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'mobile@example.com');

        $this->assertNotEmpty($response->json('data.token'));
        $user = User::where('email', 'mobile@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('player'));
        Notification::assertSentTo($user, AccountWelcomeNotification::class);
    }

    public function test_mobile_user_can_complete_profile_after_registration(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $user = User::factory()->create([
            'name' => 'New User',
            'first_name' => null,
            'last_name' => null,
            'birth_date' => null,
            'gender' => null,
            'country' => null,
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/me/profile', [
            'first_name' => 'Mobile',
            'last_name' => 'Member',
            'birth_date' => now()->subYears(20)->toDateString(),
            'gender' => 'not_specified',
            'country' => 'DE',
            'account_type' => 'athlete',
        ])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Mobile')
            ->assertJsonPath('data.last_name', 'Member');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Mobile Member',
            'first_name' => 'Mobile',
            'last_name' => 'Member',
            'country' => 'DE',
        ]);
        $this->assertTrue($user->fresh()->hasRole('player'));
    }

    public function test_adult_can_register_as_sponsor_and_receives_sponsor_role(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $this->seed(RolesPermissionsSeeder::class);
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Sponsor',
            'last_name' => 'User',
            'email' => 'sponsor@example.com',
            'country' => 'DE',
            'birth_date' => now()->subYears(30)->toDateString(),
            'account_type' => 'sponsor',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
            'device_name' => 'airmius-mobile-test',
        ]);

        $response->assertCreated();

        $user = User::where('email', 'sponsor@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('sponsor'));
        $this->assertFalse($user->hasRole('player'));
    }

    public function test_coach_can_register_as_a_normal_user_and_set_up_later(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $this->seed(RolesPermissionsSeeder::class);
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Later',
            'last_name' => 'Coach',
            'email' => 'later-coach@example.com',
            'country' => 'DE',
            'birth_date' => now()->subYears(30)->toDateString(),
            'gender' => 'not_specified',
            'account_type' => 'coach',
            'setup_mode' => 'later',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
        ])->assertCreated();

        $user = User::where('email', 'later-coach@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('player'));
        $this->assertFalse($user->hasRole('coach'));
        $this->assertDatabaseCount('user_role_applications', 0);
    }

    public function test_web_coach_registration_opens_the_trainer_setup_after_registration(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $this->seed(RolesPermissionsSeeder::class);
        Notification::fake();

        $this->post('/register', [
            'first_name' => 'Web',
            'last_name' => 'Coach',
            'email' => 'web-coach@example.com',
            'country' => 'DE',
            'birth_date' => now()->subYears(30)->toDateString(),
            'gender' => 'not_specified',
            'account_type' => 'coach',
            'setup_mode' => 'now',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
        ])->assertRedirect(config('fortify.home'));

        $webCoach = User::where('email', 'web-coach@example.com')->firstOrFail();
        $webCoach->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($webCoach->fresh());

        $this->get('/home')
            ->assertRedirect(route('auth.settings', ['tab' => 'roles', 'onboarding' => 'trainer']));
    }

    public function test_trainer_application_stores_the_trainer_data_from_the_app(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $this->seed(RolesPermissionsSeeder::class);
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Ready',
            'last_name' => 'Coach',
            'email' => 'ready-coach@example.com',
            'country' => 'DE',
            'birth_date' => now()->subYears(30)->toDateString(),
            'gender' => 'not_specified',
            'account_type' => 'coach',
            'setup_mode' => 'now',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
        ])->assertCreated();

        $user = User::where('email', 'ready-coach@example.com')->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/role-applications', [
            'type' => 'trainer',
            'application_data' => [
                'specialties' => 'Fußball',
                'experience' => '10 Jahre Jugendtraining',
                'certification' => 'C-Lizenz',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.application.application_data.specialties', 'Fußball');

        $this->assertDatabaseHas('user_role_applications', [
            'user_id' => $user->id,
            'type' => UserRoleApplication::TYPE_TRAINER,
        ]);
    }

    public function test_mobile_api_rejects_duplicate_registration_email(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $this->seed(RolesPermissionsSeeder::class);
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Existing',
            'last_name' => 'User',
            'email' => 'existing@example.com',
            'country' => 'DE',
            'birth_date' => now()->subYears(16)->subDay()->toDateString(),
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'Dieses Konto existiert bereits. Bitte melde dich an oder nutze Passwort vergessen.');
    }

    public function test_mobile_api_can_check_existing_registration_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->getJson('/api/v1/auth/register/email?email=existing@example.com');

        $response
            ->assertOk()
            ->assertJsonPath('data.exists', true)
            ->assertJsonPath('data.message', 'Dieses Konto existiert bereits. Bitte melde dich an oder nutze Passwort vergessen.');
    }

    public function test_minor_users_need_guardian_email(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $this->seed(RolesPermissionsSeeder::class);
        Notification::fake();

        $response = $this->post('/register', [
            'first_name' => 'Minor',
            'last_name' => 'User',
            'email' => 'minor@example.com',
            'country' => 'DE',
            'birth_date' => now()->subYears(15)->toDateString(),
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
        ]);

        $response->assertSessionHasErrors('guardian_email');
        $this->assertGuest();
        Notification::assertNothingSent();
    }

    public function test_minor_users_need_guardian_email_through_mobile_api(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $this->seed(RolesPermissionsSeeder::class);
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Minor',
            'last_name' => 'Mobile',
            'email' => 'minor-mobile@example.com',
            'country' => 'DE',
            'birth_date' => now()->subYears(15)->toDateString(),
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('guardian_email');

        $this->assertDatabaseMissing('users', [
            'email' => 'minor-mobile@example.com',
        ]);
        Notification::assertNothingSent();
    }

    public function test_minor_users_are_registered_pending_guardian_consent(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $this->seed(RolesPermissionsSeeder::class);
        Notification::fake();

        $response = $this->post('/register', [
            'first_name' => 'Minor',
            'last_name' => 'User',
            'email' => 'minor@example.com',
            'country' => 'DE',
            'birth_date' => now()->subYears(15)->toDateString(),
            'guardian_email' => 'parent@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
        ]);

        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->hasRole('minor_pending_consent'));
        $this->assertDatabaseHas('users', [
            'email' => 'minor@example.com',
            'guardian_email' => 'parent@example.com',
            'guardian_consent_version' => config('guardian.consent_version'),
        ]);
        $this->assertNotNull(auth()->user()->guardian_consent_token);
        Notification::assertSentTo(auth()->user(), AccountWelcomeNotification::class);
        Notification::assertSentOnDemand(GuardianConsentRequested::class);
        $response->assertRedirect(config('fortify.home'));
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
