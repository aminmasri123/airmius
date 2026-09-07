<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdEvent;
use App\Models\Sponsor;
use App\Models\User;
use App\Models\WebsiteRequest;
use App\Services\SponsorWorkspaceService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SponsorAgencyWorkspaceContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_future_ad_events_are_excluded_from_current_sponsor_outcomes(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        [$owner, , $campaign] = $this->fixtures();
        $baseline = app(SponsorWorkspaceService::class)->payload($owner);
        $this->assertSame(1, $baseline['campaigns']->first()['leads_28d']);
        $this->assertSame(1200, $baseline['campaigns']->first()['value_cents_28d']);
        AdEvent::query()->create([
            'ad_campaign_id' => $campaign->id,
            'event_type' => 'sale',
            'cost_cents' => 99999,
            'value_cents' => 999999,
            'occurred_at' => now()->addDay(),
        ]);
        $after = app(SponsorWorkspaceService::class)->payload($owner);
        $this->assertSame($baseline['campaigns']->all(), $after['campaigns']->all());
        $this->assertSame($baseline['outcome_timeline'], $after['outcome_timeline']);
    }

    public function test_owner_receives_connected_growth_flow_without_foreign_or_private_agency_data(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        [$owner, $foreign, $campaign, $brief] = $this->fixtures();

        $response = $this->actingAs($owner)
            ->getJson(route('api.v1.sponsor-workspace.index'))
            ->assertOk()
            ->assertJsonPath('data.growth.contract', 'growth-workspace.v1')
            ->assertJsonPath('data.growth.stages.0.key', 'brief')
            ->assertJsonPath('data.growth.stages.0.count', 1)
            ->assertJsonPath('data.growth.stages.1.key', 'deal')
            ->assertJsonPath('data.growth.stages.2.key', 'asset')
            ->assertJsonPath('data.growth.stages.2.count', 2)
            ->assertJsonPath('data.growth.stages.3.key', 'campaign')
            ->assertJsonPath('data.growth.stages.4.key', 'outcome')
            ->assertJsonPath('data.deals.0.workflow_state', 'measuring')
            ->assertJsonPath('data.deals.0.campaigns_count', 1)
            ->assertJsonPath('data.deals.0.assets_count', 2)
            ->assertJsonPath('data.assets.0.campaign_id', $campaign->id)
            ->assertJsonPath('data.agency_briefs.0.id', $brief->id)
            ->assertJsonPath('data.agency_briefs.0.has_domain', true);

        $encoded = $response->getContent();
        $this->assertStringNotContainsString('Vertrauliches Wachstumsziel', $encoded);
        $this->assertStringNotContainsString('Interne Agenturnotiz', $encoded);
        $this->assertStringNotContainsString('owner-agency@example.test', $encoded);
        $this->assertStringNotContainsString('Fremder Deal', $encoded);
        $this->assertStringNotContainsString('Fremde Kampagne', $encoded);
        $this->assertStringNotContainsString('foreign-agency@example.test', $encoded);

        foreach ($response->json('data.agency_briefs') as $projectedBrief) {
            foreach (['guest_name', 'guest_email', 'guest_phone', 'goals', 'notes', 'user_id', 'status_changed_by'] as $privateField) {
                $this->assertArrayNotHasKey($privateField, $projectedBrief);
            }
        }

        foreach ($response->json('data.assets') as $asset) {
            $this->assertArrayNotHasKey('target_url', $asset);
            $this->assertArrayNotHasKey('primary_text', $asset);
        }

        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertNotSame($foreign->id, $owner->id);
    }

    public function test_growth_projection_remains_bounded_with_one_asset_and_two_agency_queries(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        [$owner] = $this->fixtures();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $payload = app(SponsorWorkspaceService::class)->payload($owner);
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(16, $queries->count());
        $this->assertSame(1, $queries->filter(fn (array $query) => str_contains(strtolower($query['query']), 'ad_creatives'))->count());
        $this->assertSame(2, $queries->filter(fn (array $query) => str_contains(strtolower($query['query']), 'website_requests'))->count());
        $this->assertCount(2, $payload['assets']);
        $this->assertCount(1, $payload['agency_briefs']);
        $this->assertSame(8, $payload['limits']['agency_briefs']);
        $this->assertSame(24, $payload['limits']['assets']);
    }

    public function test_sponsor_workspace_ui_exposes_connected_flow_and_partial_refresh_contract(): void
    {
        $source = File::get(resource_path('js/Pages/Auth/Dashboard/SponsorWorkspace/Index.vue'));
        $messages = json_decode(
            File::get(resource_path('js/Pages/Auth/Dashboard/SponsorWorkspace/messages.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        foreach (['props.workspace.growth', 'connected_deals', 'creative_assets', 'agency_briefs'] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }

        $this->assertStringContainsString(':only="[\'workspace\']"', $source);
        $this->assertStringContainsString('preserve-scroll', $source);
        $this->assertStringContainsString('loading="lazy"', $source);
        $this->assertStringContainsString('decoding="async"', $source);
        $this->assertStringNotContainsString('brief.goals', $source);
        $this->assertStringNotContainsString('brief.notes', $source);
        $this->assertStringNotContainsString('brief.guest_email', $source);
        $this->assertStringNotContainsString('setInterval(', $source);
        $this->assertStringContainsString("import sponsorWorkspaceMessages from './messages.json'", $source);

        $reference = Arr::dot($messages['de']);
        ksort($reference);
        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            $localized = Arr::dot($messages[$locale]);
            ksort($localized);

            $this->assertSame(array_keys($reference), array_keys($localized), "{$locale} sponsor workspace key parity");
            foreach ($reference as $key => $value) {
                preg_match_all('/\{[a-z_]+\}/i', $value, $referencePlaceholders);
                preg_match_all('/\{[a-z_]+\}/i', $localized[$key], $localizedPlaceholders);
                sort($referencePlaceholders[0]);
                sort($localizedPlaceholders[0]);
                $this->assertSame($referencePlaceholders[0], $localizedPlaceholders[0], "{$locale}.{$key} placeholder parity");
            }
        }
    }

    private function fixtures(): array
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $owner->assignRole('sponsor');
        $foreign->assignRole('sponsor');

        $sponsor = Sponsor::query()->create([
            'owner_user_id' => $owner->id,
            'scope' => 'platform',
            'name' => 'Verbundener Deal',
            'verification_status' => 'verified',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
        Sponsor::query()->create([
            'owner_user_id' => $foreign->id,
            'scope' => 'platform',
            'name' => 'Fremder Deal',
        ]);
        $campaign = AdCampaign::query()->create([
            'user_id' => $owner->id,
            'sponsor_id' => $sponsor->id,
            'name' => 'Verbundene Kampagne',
            'headline' => 'Schneller gemeinsam',
            'creative_format' => 'feed_square',
            'creative_image_url' => 'https://assets.example.test/campaign.webp',
            'status' => 'active',
            'impressions' => 100,
            'clicks' => 10,
        ]);
        AdCreative::query()->create([
            'ad_campaign_id' => $campaign->id,
            'ad_name' => 'Variante Sprint',
            'name' => 'Variante A',
            'headline' => 'Sprint',
            'creative_format' => 'story_vertical',
            'creative_image_url' => 'https://assets.example.test/creative.webp',
            'is_active' => true,
            'impressions' => 50,
            'clicks' => 7,
        ]);
        AdEvent::query()->create([
            'ad_campaign_id' => $campaign->id,
            'event_type' => 'lead',
            'cost_cents' => 100,
            'value_cents' => 1200,
            'occurred_at' => now(),
        ]);
        $foreignCampaign = AdCampaign::query()->create([
            'user_id' => $foreign->id,
            'name' => 'Fremde Kampagne',
            'status' => 'active',
        ]);
        AdCreative::query()->create([
            'ad_campaign_id' => $foreignCampaign->id,
            'name' => 'Fremdes Creative',
        ]);
        $brief = WebsiteRequest::query()->create([
            'user_id' => $owner->id,
            'guest_name' => 'Private Kontaktperson',
            'guest_email' => 'owner-agency@example.test',
            'guest_phone' => '+49 000 0000',
            'club_name' => 'Privater Verein',
            'status' => 'contacted',
            'package' => 'website_plus',
            'domain' => 'sport.example.test',
            'goals' => 'Vertrauliches Wachstumsziel',
            'notes' => 'Interne Agenturnotiz',
            'consent_at' => now(),
            'status_changed_at' => now(),
            'retention_expires_at' => now()->addYear(),
        ]);
        WebsiteRequest::query()->create([
            'user_id' => $foreign->id,
            'guest_name' => 'Fremde Person',
            'guest_email' => 'foreign-agency@example.test',
            'club_name' => 'Fremder Verein',
            'status' => 'new',
            'package' => 'website_plus',
            'goals' => 'Fremdes Ziel',
            'consent_at' => now(),
            'status_changed_at' => now(),
            'retention_expires_at' => now()->addYear(),
        ]);

        return [$owner, $foreign, $campaign, $brief];
    }
}
