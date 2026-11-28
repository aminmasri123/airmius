<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\AdEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdConversionConsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Laravel's HTTP test client does not retain response cookies automatically.
        // Keep the same browser session for all requests in each scenario.
        $this->withCredentials()->withCookie(config('session.cookie'), str_repeat('a', 40));
    }

    private function campaign(string $objective = 'leads'): AdCampaign
    {
        return AdCampaign::create([
            'name' => 'T29-10 isolated test', 'objective' => $objective,
            'placement' => 'feed', 'target_url' => 'https://example.test',
            'budget_cents' => 10000, 'status' => 'active',
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
        ]);
    }

    public function test_lead_and_sale_replays_do_not_duplicate_events_or_charges(): void
    {
        $this->freezeTime();
        $this->actingAs(User::factory()->create(['ads_measurement_consent' => true]));
        foreach (['lead' => 'leads', 'sale' => 'sales'] as $type => $objective) {
            $campaign = $this->campaign($objective);
            $payload = ['event_type' => $type, 'value_cents' => 1000, 'metadata' => ['order_id' => 123]];
            $this->postJson(route('ads.conversion', $campaign), $payload)->assertOk();
            $spent = $campaign->fresh()->spent_cents;
            $this->assertGreaterThan(0, $spent);
            $this->postJson(route('ads.conversion', $campaign), $payload)->assertOk();
            $this->assertSame(1, AdEvent::where('ad_campaign_id', $campaign->id)->count());
            $this->assertSame($spent, $campaign->fresh()->spent_cents);
            $payload['metadata']['order_id'] = 124;
            $this->postJson(route('ads.conversion', $campaign), $payload)->assertOk();
            $this->assertSame(2, AdEvent::where('ad_campaign_id', $campaign->id)->count());
        }
    }

    public function test_conversion_deduplication_window_is_sixty_minutes(): void
    {
        $this->freezeTime();
        $this->actingAs(User::factory()->create(['ads_measurement_consent' => true]));
        $campaign = $this->campaign();
        $payload = ['event_type' => 'lead', 'metadata' => ['order_id' => 123]];
        $this->postJson(route('ads.conversion', $campaign), $payload)->assertOk();
        $this->travel(59)->minutes();
        $this->postJson(route('ads.conversion', $campaign), $payload)->assertOk();
        $this->assertDatabaseCount('ad_events', 1);
        $this->travel(2)->minutes();
        $this->postJson(route('ads.conversion', $campaign), $payload)->assertOk();
        $this->assertDatabaseCount('ad_events', 2);
    }

    public function test_app_consent_withdrawal_blocks_fresh_conversions_even_after_deduplication_expires(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['ads_measurement_consent' => true]);
        $this->actingAs($user);
        $campaign = $this->campaign();
        $this->postJson(route('ads.conversion', $campaign), ['event_type' => 'lead'])->assertOk();
        $spent = $campaign->fresh()->spent_cents;
        $this->postJson('/api/v1/privacy/withdraw-consents', ['consents' => ['ads_measurement']])
            ->assertOk()->assertJsonPath('data.privacy_settings.ads_measurement_consent', false);
        $this->assertFalse((bool) $user->fresh()->ads_measurement_consent);
        $this->actingAs($user->fresh());
        $this->travel(61)->minutes();
        foreach (['lead', 'sale'] as $type) {
            $this->postJson(route('ads.conversion', $campaign), [
                'event_type' => $type, 'metadata' => ['order_id' => 999],
            ])->assertForbidden();
        }
        $this->assertDatabaseCount('ad_events', 1);
        $this->assertSame($spent, $campaign->fresh()->spent_cents);
    }

    public function test_missing_consent_and_unsupported_events_are_rejected(): void
    {
        $campaign = $this->campaign();
        $this->postJson(route('ads.conversion', $campaign), ['event_type' => 'lead'])->assertForbidden();
        $user = User::factory()->create(['ads_measurement_consent' => false]);
        $this->actingAs($user)->postJson(route('ads.conversion', $campaign), ['event_type' => 'sale'])->assertForbidden();
        $user->update(['ads_measurement_consent' => true]);
        $this->actingAs($user->fresh())->postJson(route('ads.conversion', $campaign), ['event_type' => 'unsupported'])->assertUnprocessable();
        $this->assertDatabaseCount('ad_events', 0);
    }
}
