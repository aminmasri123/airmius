<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\User;
use App\Models\WebsiteRequest;
use App\Services\UserDataErasureService;
use App\Services\UserPrivacyExportService;
use App\Services\WebsiteRequestService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgencyAdsOptimizationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_agency_request_requires_consent_and_is_idempotent_localized_and_retained(): void
    {
        $payload = [
            'guest_name' => 'Alex Verein',
            'guest_email' => 'alex@example.test',
            'club_name' => 'Sportclub Nord',
            'goals' => 'Neue Mitglieder und Sponsoren erreichen.',
        ];

        $this->postJson('/api/v1/public/agency/requests', $payload, [
            'Idempotency-Key' => 'agency-consent-rejected-0001',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('accepted_privacy');

        $headers = [
            'Idempotency-Key' => 'agency-request-contract-0001',
            'X-App-Locale' => 'fr',
        ];
        $response = $this->postJson('/api/v1/public/agency/requests', [
            ...$payload,
            'accepted_privacy' => true,
        ], $headers)
            ->assertCreated()
            ->assertHeader('Content-Language', 'fr')
            ->assertJsonPath('message', trans('agency.flash.request_sent', locale: 'fr'))
            ->assertJsonPath('data.status', 'new');

        $this->postJson('/api/v1/public/agency/requests', [
            ...$payload,
            'accepted_privacy' => true,
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.id', $response->json('data.id'));

        $agencyRequest = WebsiteRequest::query()->sole();
        $this->assertNotNull($agencyRequest->consent_at);
        $this->assertNotNull($agencyRequest->status_changed_at);
        $this->assertTrue($agencyRequest->retention_expires_at->isAfter(now()->addMonths(11)));
    }

    public function test_agency_workflow_resets_retention_and_expired_requests_are_pruned(): void
    {
        $actor = User::factory()->create();
        $agencyRequest = WebsiteRequest::query()->create([
            'user_id' => $actor->id,
            'status' => 'new',
            'package' => 'website_plus',
            'goals' => 'Website relaunch',
            'consent_at' => now(),
            'status_changed_at' => now(),
            'retention_expires_at' => now()->addYear(),
        ]);

        $updated = app(WebsiteRequestService::class)->updateWorkflow($agencyRequest, $actor, [
            'status' => 'done',
            'notes' => 'Angebot angenommen und Projekt abgeschlossen.',
        ]);

        $this->assertSame('done', $updated->status);
        $this->assertSame($actor->id, $updated->status_changed_by);
        $this->assertTrue($updated->retention_expires_at->isBetween(now()->addMonths(5), now()->addMonths(7)));

        $updated->forceFill(['retention_expires_at' => now()->subMinute()])->save();
        $this->artisan('airmius:prune-website-requests')
            ->expectsOutput('Pruned 1 expired agency requests.')
            ->assertSuccessful();
        $this->assertDatabaseMissing('website_requests', ['id' => $agencyRequest->id]);
    }

    public function test_agency_request_is_available_in_export_and_removed_by_commerce_erasure(): void
    {
        $user = User::factory()->create();
        $agencyRequest = WebsiteRequest::query()->create([
            'user_id' => $user->id,
            'guest_email' => $user->email,
            'guest_name' => $user->name,
            'club_name' => 'Export Club',
            'status' => 'contacted',
            'package' => 'website_plus',
            'goals' => 'Neue Vereinswebsite',
            'consent_at' => now(),
            'status_changed_at' => now(),
            'retention_expires_at' => now()->addYear(),
        ]);

        $export = app(UserPrivacyExportService::class)->export($user);
        $this->assertSame($agencyRequest->id, data_get($export, 'agency.requests.0.id'));
        $this->assertSame('Neue Vereinswebsite', data_get($export, 'agency.requests.0.goals'));

        app(UserDataErasureService::class)->erase($user, ['commerce']);
        $this->assertDatabaseMissing('website_requests', ['id' => $agencyRequest->id]);
    }

    public function test_ad_delivery_batches_candidate_signals_and_localizes_default_cta(): void
    {
        foreach (range(1, 12) as $index) {
            AdCampaign::query()->create([
                'name' => "Campaign {$index}",
                'objective' => 'traffic',
                'placement' => 'feed',
                'creative_format' => 'feed_square',
                'target_url' => 'https://example.test/sport',
                'budget_cents' => 100_000,
                'status' => 'active',
            ]);
        }

        $adEventQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$adEventQueries): void {
            if (str_contains(strtolower($query->sql), 'ad_events')) {
                $adEventQueries++;
            }
        });

        $this->withHeader('X-App-Locale', 'fr')
            ->getJson(route('ads.active', ['placement' => 'feed']))
            ->assertOk()
            ->assertJsonPath('cta_label', trans('commerce.ads.default_cta', locale: 'fr'));

        $this->assertLessThanOrEqual(6, $adEventQueries, 'Ad delivery must not query signals once per candidate campaign.');
    }

    public function test_ad_click_rejects_unsafe_redirect_schemes(): void
    {
        $campaign = AdCampaign::query()->create([
            'name' => 'Unsafe redirect',
            'objective' => 'traffic',
            'placement' => 'feed',
            'creative_format' => 'feed_square',
            'target_url' => 'javascript:alert(1)',
            'budget_cents' => 10_000,
            'status' => 'active',
        ]);

        $this->get(route('ads.click', $campaign))
            ->assertRedirect(route('guest.pricing'));
    }

    public function test_mobile_contract_exposes_the_live_localized_agency_flow(): void
    {
        $this->getJson('/api/v1/meta')
            ->assertOk()
            ->assertJsonPath('data.capabilities.agency.0', 'public_request')
            ->assertJsonPath('data.capabilities.agency.3', 'self_service_erasure');

        $screen = file_get_contents(base_path('mobile/airmius_mobile/lib/screens/guest_ad_agency_screen.dart'));
        $client = file_get_contents(base_path('mobile/airmius_mobile/lib/core/airmius_api_client.dart'));

        $this->assertStringContainsString("t('agencyMobile.title')", $screen);
        $this->assertStringContainsString('submitPublicAgencyRequest', $screen);
        $this->assertStringContainsString("'/api/v1/public/agency/requests'", $client);
        $this->assertStringNotContainsString('Agenturkontakt vorbereiten', $screen);
    }
}
