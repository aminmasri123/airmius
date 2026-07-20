<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CookieTrackingConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_inertia_payload_disables_optional_ad_tracking_by_default(): void
    {
        $this->get(route('welcome'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('privacyConsent.ads_personalization_consent', false)
                ->where('privacyConsent.ads_measurement_consent', false)
                ->where('privacyConsent.source', 'none')
            );
    }

    public function test_authenticated_inertia_payload_uses_user_ad_consents(): void
    {
        $user = User::factory()->create([
            'ads_personalization_consent' => false,
            'ads_measurement_consent' => true,
        ]);

        $this->actingAs($user)
            ->get(route('welcome'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('auth.user.ads_personalization_consent', false)
                ->where('auth.user.ads_measurement_consent', true)
                ->where('privacyConsent.ads_personalization_consent', false)
                ->where('privacyConsent.ads_measurement_consent', true)
                ->where('privacyConsent.source', 'user_settings')
            );
    }

    public function test_app_shell_does_not_embed_external_marketing_or_analytics_tags(): void
    {
        $body = $this->get(route('welcome'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('googletagmanager.com', $body);
        $this->assertStringNotContainsString('google-analytics.com', $body);
        $this->assertStringNotContainsString('connect.facebook.net', $body);
        $this->assertStringNotContainsString('plausible.io', $body);
        $this->assertStringNotContainsString('matomo', strtolower($body));
        $this->assertStringNotContainsString('hotjar', strtolower($body));
        $this->assertStringNotContainsString('clarity.ms', $body);
    }

    public function test_cookie_page_documents_current_tracking_consent_boundary(): void
    {
        $this->get(route('legal.cookies'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Legal/Show')
                ->where('title', 'Cookie-Hinweise')
                ->where('sections.1.title', 'Optionale Technologien')
                ->where('sections.1.body.1', 'Aktuell bindet Airmius im Weblayout keine externen Analytics-, Marketing-, Retargeting- oder Pixel-Skripte ein.')
                ->where('sections.1.body.2', 'Clientseitige Landingpage-Events an dataLayer oder gtag werden technisch blockiert, solange keine Einwilligung zur Werbe-/Conversion-Messung gespeichert ist.')
            );
    }
}
