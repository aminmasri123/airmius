<?php

namespace Tests\Feature;

use App\Models\ConnectedSportAccount;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\PrivacyCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrivacyCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_the_privacy_center_projection(): void
    {
        $this->getJson('/api/v1/privacy')->assertUnauthorized();
    }

    public function test_address_correction_returns_and_updates_street_and_house_number(): void
    {
        $user = User::factory()->create([
            'street' => 'Teststrasse',
            'house_number' => '12a',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/privacy')->assertOk()
            ->assertJsonPath('data.profile_address.street', 'Teststrasse')
            ->assertJsonPath('data.profile_address.house_number', '12a');

        $this->patchJson('/api/v1/privacy/correction', [
            'street' => 'Neue Strasse',
            'house_number' => '24b',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'street' => 'Neue Strasse',
            'house_number' => '24b',
        ]);
        $this->getJson('/api/v1/privacy')->assertOk()
            ->assertJsonPath('data.profile_address.street', 'Neue Strasse')
            ->assertJsonPath('data.profile_address.house_number', '24b');
    }

    public function test_privacy_center_returns_consents_rights_and_minimized_provider_metadata(): void
    {
        $user = User::factory()->create([
            'ads_personalization_consent' => true,
            'ads_measurement_consent' => false,
            'product_analytics_consent' => true,
            'profile_visibility' => 'private',
        ]);

        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'external-secret-id',
            'email' => 'linked@example.test',
            'access_token' => 'social-secret-token',
        ]);
        ConnectedSportAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'strava',
            'provider_user_id' => 'sport-secret-id',
            'display_name' => 'Private athlete name',
            'status' => 'connected',
            'scopes' => ['activity:read_all'],
            'access_token' => 'sport-secret-token',
            'last_synced_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/privacy')
            ->assertOk()
            ->assertJsonPath('data.version', PrivacyCenterService::VERSION)
            ->assertJsonPath('data.privacy_settings.profile_visibility', 'private')
            ->assertJsonPath('data.privacy_settings.ads_personalization_consent', true)
            ->assertJsonPath('data.privacy_settings.ads_measurement_consent', false)
            ->assertJsonPath('data.privacy_settings.product_analytics_consent', true)
            ->assertJsonPath('data.connected_providers.summary.total', 2)
            ->assertJsonPath('data.connected_providers.summary.login', 1)
            ->assertJsonPath('data.connected_providers.summary.sport', 1)
            ->assertJsonPath('data.connected_providers.items.0.kind', 'login')
            ->assertJsonPath('data.connected_providers.items.0.provider', 'google')
            ->assertJsonPath('data.connected_providers.items.1.kind', 'sport')
            ->assertJsonPath('data.connected_providers.items.1.provider', 'strava')
            ->assertJsonPath('data.rights.export', '/api/v1/privacy/export');

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);

        $payload = $response->json('data.connected_providers.items');
        $serialized = json_encode($payload);

        $this->assertStringNotContainsString('linked@example.test', $serialized);
        $this->assertStringNotContainsString('external-secret-id', $serialized);
        $this->assertStringNotContainsString('Private athlete name', $serialized);
        $this->assertStringNotContainsString('activity:read_all', $serialized);
        $this->assertStringNotContainsString('secret-token', $serialized);
    }

    public function test_privacy_center_projection_has_a_constant_small_query_budget(): void
    {
        $user = User::factory()->create();

        foreach (['google', 'microsoft'] as $provider) {
            SocialAccount::query()->create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_user_id' => $provider.'-'.$user->id,
            ]);
        }
        foreach (['strava', 'garmin', 'polar'] as $provider) {
            ConnectedSportAccount::query()->create([
                'user_id' => $user->id,
                'provider' => $provider,
                'status' => 'connected',
            ]);
        }

        Sanctum::actingAs($user);
        $selects = 0;
        DB::listen(function ($query) use (&$selects): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $selects++;
            }
        });

        $this->getJson('/api/v1/privacy')
            ->assertOk()
            ->assertJsonPath('data.connected_providers.summary.total', 5);

        $this->assertLessThanOrEqual(3, $selects);
    }

    public function test_web_privacy_feedback_uses_the_account_language(): void
    {
        $frenchUser = User::factory()->create(['language' => 'fr']);

        $this->actingAs($frenchUser)
            ->patch(route('auth.settings.privacy.correct'), ['city' => 'Lyon'])
            ->assertRedirect()
            ->assertHeader('Content-Language', 'fr')
            ->assertSessionHas('success', 'Vos données ont été rectifiées.');

        $arabicUser = User::factory()->create([
            'language' => 'ar',
            'ads_measurement_consent' => true,
        ]);

        $this->actingAs($arabicUser)
            ->post(route('auth.settings.privacy.withdraw-consents'), [
                'consents' => ['ads_measurement'],
            ])
            ->assertRedirect()
            ->assertHeader('Content-Language', 'ar')
            ->assertSessionHas('success', 'تم سحب موافقتك.');
    }
}
