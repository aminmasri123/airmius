<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdDeliveryPlacementTest extends TestCase
{
    use RefreshDatabase;

    private function campaign(array $attributes = []): AdCampaign
    {
        return AdCampaign::query()->create(array_merge([
            'name' => 'Verein im Blick',
            'headline' => 'Gemeinsam Sport erleben',
            'objective' => 'traffic',
            'placement' => 'feed',
            'creative_format' => 'feed_square',
            'creative_image_url' => 'https://example.test/ad.jpg',
            'target_url' => 'https://example.test/verein',
            'budget_cents' => 10000,
            'status' => 'active',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ], $attributes));
    }

    public function test_native_click_uses_bearer_consent_without_browser_redirect_and_deduplicates(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['ads_measurement_consent' => true]);
        $campaign = $this->campaign();
        $headers = ['Authorization' => 'Bearer '.$user->createToken('click')->plainTextToken];
        $token = $this->getJson('/api/v1/ads/active?placement=feed&defer_impression=1', $headers)->assertOk()->json('impression_token');
        $this->assertNotEmpty($token);
        foreach (range(1, 2) as $attempt) {
            $this->postJson('/api/v1/ads/click', ['token' => $token], $headers)
                ->assertOk()->assertJsonPath('target_url', $campaign->target_url)->assertHeaderMissing('Location');
        }
        $this->assertSame(1, (int) $campaign->fresh()->clicks);
        $this->assertDatabaseHas('ad_events', ['ad_campaign_id' => $campaign->id, 'user_id' => $user->id, 'event_type' => 'click']);
        $this->postJson('/api/v1/ads/click', ['token' => 'tampered'], $headers)->assertUnprocessable();
        $this->travel(3)->hours();
        $this->postJson('/api/v1/ads/click', ['token' => $token], $headers)->assertForbidden();
    }

    public function test_native_click_opens_destination_without_measurement_consent_and_rejects_foreign_delivery(): void
    {
        $user = User::factory()->create(['ads_measurement_consent' => false]);
        $campaign = $this->campaign();
        $headers = ['Authorization' => 'Bearer '.$user->createToken('click')->plainTextToken];
        $token = $this->getJson('/api/v1/ads/active?placement=feed&defer_impression=1', $headers)->assertOk()->json('impression_token');
        $this->postJson('/api/v1/ads/click', ['token' => $token], $headers)->assertOk()->assertJsonPath('target_url', $campaign->target_url);
        $this->assertSame(0, (int) $campaign->fresh()->clicks);
        $this->assertDatabaseCount('ad_events', 0);
        // A token signed for a different viewer must not count for this user.
        $foreign = \Illuminate\Support\Facades\Crypt::encryptString(json_encode(['campaign' => $campaign->id, 'viewer' => $user->id + 1, 'expires' => now()->addHour()->timestamp]));
        $this->postJson('/api/v1/ads/click', ['token' => $foreign], $headers)->assertForbidden();
        $campaign->update(['status' => 'paused']);
        $this->postJson('/api/v1/ads/click', ['token' => $token], $headers)->assertNotFound();
    }

    public function test_all_placements_and_image_formats_are_delivered_on_web_and_mobile(): void
    {
        $this->freezeTime();
        foreach (['feed', 'sidebar', 'marketplace_card', 'sponsor_section'] as $placement) {
            $campaign = $this->campaign(['placement' => $placement]);
            foreach (['feed_square', 'feed_portrait', 'story_vertical', 'banner_wide'] as $format) {
                $campaign->update(['creative_format' => $format]);
                foreach (['/ads/active', '/api/v1/public/ads/active'] as $endpoint) {
                    $response = $this->getJson($endpoint.'?placement='.$placement)
                        ->assertOk()
                        ->assertJsonPath('id', $campaign->id)
                        ->assertJsonPath('placement', $placement)
                        ->assertJsonPath('creative_format', $format)
                        ->assertJsonPath('ends_at', $campaign->ends_at->toIso8601String())
                        ->assertJsonPath('image_url', 'https://example.test/ad.jpg');
                    $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
                }
            }
            $campaign->delete();
        }
        // Delivery without consent displays contextual ads without recording personal events.
        $this->assertDatabaseCount('ad_events', 0);
    }

    public function test_ineligible_campaigns_are_excluded_at_every_placement(): void
    {
        $this->freezeTime();
        foreach (['feed', 'sidebar', 'marketplace_card', 'sponsor_section'] as $placement) {
            foreach ([
                ['status' => 'draft'], ['status' => 'pending'], ['status' => 'rejected'],
                ['status' => 'paused'], ['status' => 'ended'],
                ['starts_at' => now()->addMinute()],
                ['ends_at' => now()->subSecond()],
                ['ends_at' => now()],
                ['spent_cents' => 10000],
            ] as $attributes) {
                $campaign = $this->campaign(['placement' => $placement, ...$attributes]);
                foreach (['/ads/active', '/api/v1/public/ads/active'] as $endpoint) {
                    $this->getJson($endpoint.'?placement='.$placement)->assertOk()->assertJsonMissingPath('id');
                }
                $campaign->delete();
            }
        }
    }

    public function test_bearer_delivery_uses_consent_and_survives_without_browser_session(): void
    {
        $user = User::factory()->create([
            'ads_measurement_consent' => true,
            'ads_personalization_consent' => true,
        ]);
        $campaign = $this->campaign();
        $headers = ['Authorization' => 'Bearer '.$user->createToken('ads-test')->plainTextToken];
        $this->getJson('/api/v1/ads/active?placement=feed', $headers)
            ->assertOk()->assertJsonPath('id', $campaign->id)
            ->assertHeader('X-Airmius-Module', 'ads')
            ->assertHeader('X-Airmius-Data-Purpose', 'marketing');
        $this->assertDatabaseHas('ad_events', [
            'ad_campaign_id' => $campaign->id, 'user_id' => $user->id, 'event_type' => 'impression',
        ]);
        // Revalidation must not charge or count a second impression within the duplicate window.
        $this->getJson('/api/v1/ads/active?placement=feed', $headers)->assertOk();
        $this->assertDatabaseCount('ad_events', 1);
        $campaign->update(['status' => 'paused']);
        $this->getJson('/api/v1/ads/active?placement=feed', $headers)->assertOk()->assertJsonMissingPath('id');
    }

    public function test_start_and_end_boundaries_are_respected_after_status_changes(): void
    {
        $this->freezeTime();
        $campaign = $this->campaign(['starts_at' => now(), 'ends_at' => now()->addMinute()]);
        $this->getJson('/ads/active?placement=feed')->assertJsonPath('id', $campaign->id);
        $campaign->update(['status' => 'paused']);
        $this->getJson('/ads/active?placement=feed')->assertJsonMissingPath('id');
        $campaign->update(['status' => 'active']);
        $this->getJson('/ads/active?placement=feed')->assertJsonPath('id', $campaign->id);
        $this->travel(1)->minutes();
        $this->getJson('/ads/active?placement=feed')->assertJsonMissingPath('id');
        $this->getJson('/api/v1/public/ads/active?placement=feed')->assertJsonMissingPath('id');
    }

    public function test_native_device_and_app_language_targeting_work_for_each_objective(): void
    {
        foreach (['traffic', 'awareness', 'leads', 'sales'] as $objective) {
            $campaign = $this->campaign([
                'objective' => $objective,
                'audience' => ['devices' => ['mobile'], 'languages' => ['de']],
            ]);
            $this->getJson('/api/v1/public/ads/active?placement=feed', [
                'X-Airmius-Ad-Device' => 'mobile', 'X-Airmius-Locale' => 'de',
            ])->assertOk()->assertJsonPath('id', $campaign->id);
            $this->getJson('/api/v1/public/ads/active?placement=feed', [
                'X-Airmius-Ad-Device' => 'desktop', 'X-Airmius-Locale' => 'de',
            ])->assertOk()->assertJsonMissingPath('id');
            $this->getJson('/api/v1/public/ads/active?placement=feed', [
                'X-Airmius-Ad-Device' => 'mobile', 'X-Airmius-Locale' => 'fr',
            ])->assertOk()->assertJsonMissingPath('id');
            $campaign->delete();
        }
    }

    public function test_daily_budget_pacing_uses_the_elapsed_day_and_still_enforces_limits(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        $campaign = $this->campaign([
            'starts_at' => today(), 'ends_at' => today()->endOfDay(),
            'daily_budget_cents' => 600, 'spent_cents' => 200,
        ]);
        // Store a DATE value as MySQL does; SQLite otherwise keeps Eloquent's time suffix.
        DB::table('ad_campaign_stats')->insert([
            'ad_campaign_id' => $campaign->id, 'date' => today()->toDateString(), 'spent_cents' => 200,
        ]);
        $stat = $campaign->stats()->sole();
        foreach (['/ads/active', '/api/v1/public/ads/active'] as $endpoint) {
            $this->getJson($endpoint.'?placement=feed')->assertOk()->assertJsonPath('id', $campaign->id);
        }
        $stat->update(['spent_cents' => 600]);
        $this->getJson('/ads/active?placement=feed')->assertJsonMissingPath('id');
        $this->getJson('/api/v1/public/ads/active?placement=feed')->assertJsonMissingPath('id');
        $stat->update(['spent_cents' => 200]);
        $this->travelTo(today()->addMinutes(10));
        $this->getJson('/ads/active?placement=feed')->assertJsonMissingPath('id');
    }
}
