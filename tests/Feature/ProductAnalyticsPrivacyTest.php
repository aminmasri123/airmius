<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\TrainingLog;
use App\Models\User;
use App\Services\ProductAnalyticsService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductAnalyticsPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_adult_can_separately_grant_and_withdraw_product_analytics_consent(): void
    {
        $user = User::factory()->create([
            'birth_date' => now()->subYears(25)->toDateString(),
            'ads_personalization_consent' => false,
            'ads_measurement_consent' => false,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/settings', [
            'product_analytics_consent' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.product_analytics_consent', true);

        $fresh = $user->fresh();
        $this->assertTrue($fresh->product_analytics_consent);
        $this->assertNotNull($fresh->product_analytics_consented_at);
        $this->assertSame(config('product_analytics.consent_version'), $fresh->product_analytics_consent_version);
        $this->assertFalse($fresh->ads_personalization_consent);
        $this->assertFalse($fresh->ads_measurement_consent);
        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'type' => 'privacy.consent_updated',
        ]);

        $this->postJson('/api/v1/privacy/withdraw-consents', [
            'consents' => ['product_analytics'],
        ])
            ->assertOk()
            ->assertJsonPath('data.withdrawn_consents.0', 'product_analytics')
            ->assertJsonPath('data.privacy_settings.product_analytics_consent', false);

        $this->assertFalse($user->fresh()->product_analytics_consent);
        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'type' => 'privacy.consent_withdrawn',
        ]);
    }

    public function test_minor_cannot_enable_product_or_ad_analytics(): void
    {
        $minor = User::factory()->create([
            'birth_date' => now()->subYears(15)->toDateString(),
        ]);

        Sanctum::actingAs($minor);

        $this->patchJson('/api/v1/settings', [
            'ads_personalization_consent' => true,
            'ads_measurement_consent' => true,
            'product_analytics_consent' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.ads_personalization_consent', false)
            ->assertJsonPath('data.ads_measurement_consent', false)
            ->assertJsonPath('data.product_analytics_consent', false);

        $minor->refresh();
        $this->assertFalse($minor->ads_personalization_consent);
        $this->assertFalse($minor->ads_measurement_consent);
        $this->assertFalse($minor->product_analytics_consent);
        $this->assertNull($minor->product_analytics_consented_at);
    }

    public function test_small_cohorts_are_fully_suppressed_without_identifiers(): void
    {
        config()->set('product_analytics.enabled', true);
        config()->set('product_analytics.minimum_group_size', 5);

        User::factory()->count(4)->create([
            'birth_date' => now()->subYears(25)->toDateString(),
            'product_analytics_consent' => true,
            'product_analytics_consented_at' => now(),
            'product_analytics_consent_version' => 'product-analytics-v1',
        ]);

        $report = app(ProductAnalyticsService::class)->dashboard(28);

        $this->assertSame('minimum_group', $report['status']);
        $this->assertTrue($report['privacy']['suppressed']);
        $this->assertSame(5, $report['privacy']['minimum_group_size']);
        $this->assertTrue($report['privacy']['minors_excluded']);
        $this->assertFalse($report['privacy']['tracking_sdk']);
        $this->assertFalse($report['privacy']['cookies_added']);

        foreach ($report['metrics'] as $metric) {
            $this->assertNull($metric['value']);
            $this->assertNull($metric['denominator']);
            $this->assertNull($metric['rate_percent']);
            $this->assertTrue($metric['suppressed']);
        }

        $this->assertReportHasNoIdentityFields($report);
    }

    public function test_only_consented_adults_contribute_to_aggregate_metrics_with_bounded_queries(): void
    {
        config()->set('product_analytics.enabled', true);
        config()->set('product_analytics.minimum_group_size', 5);

        $eligibleUsers = User::factory()->count(5)->create([
            'birth_date' => now()->subYears(25)->toDateString(),
            'created_at' => now()->subDays(40),
            'last_seen_at' => now(),
            'product_analytics_consent' => true,
            'product_analytics_consented_at' => now()->subDays(35),
            'product_analytics_consent_version' => 'product-analytics-v1',
        ]);

        foreach ($eligibleUsers as $user) {
            $this->createOperationalSignals($user);
        }

        $excluded = User::factory()->create([
            'name' => 'Excluded Analytics Person',
            'email' => 'excluded-analytics@example.test',
            'birth_date' => now()->subYears(25)->toDateString(),
            'created_at' => now()->subDays(40),
            'last_seen_at' => now(),
            'product_analytics_consent' => false,
        ]);
        $this->createOperationalSignals($excluded);

        $selectQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$selectQueries): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $selectQueries++;
            }
        });

        $report = app(ProductAnalyticsService::class)->dashboard(28);
        $metrics = collect($report['metrics'])->keyBy('key');

        $this->assertSame('ready', $report['status']);
        $this->assertSame(5, $metrics['consented_cohort']['value']);
        $this->assertSame(5, $metrics['active_users']['value']);
        $this->assertSame(5, $metrics['seven_day_retention']['value']);
        $this->assertSame(5, $metrics['training_engagement']['value']);
        $this->assertSame(5, $metrics['paid_customers']['value']);
        $this->assertSame(0, $metrics['team_engagement']['value']);
        $this->assertLessThanOrEqual(10, $selectQueries);
        $this->assertStringNotContainsString('Excluded Analytics Person', json_encode($report, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('excluded-analytics@example.test', json_encode($report, JSON_THROW_ON_ERROR));
        $this->assertReportHasNoIdentityFields($report);
    }

    public function test_analytics_workspace_requires_the_dedicated_permission(): void
    {
        config()->set('product_analytics.enabled', false);

        $analyst = User::factory()->create();
        $permission = Permission::findOrCreate('analytics.view', 'web');
        $analyst->givePermissionTo($permission);

        $response = $this->actingAs($analyst)
            ->get(route('admin.product-analytics.index', ['days' => 90]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Admin/ProductAnalytics/Index')
                ->where('dashboard.status', 'disabled')
                ->where('dashboard.period.days', 90)
                ->where('dashboard.privacy.user_identifiers_exposed', false)
            );

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));

        $this->actingAs(User::factory()->create())
            ->get(route('admin.product-analytics.index'))
            ->assertForbidden();
    }

    public function test_product_analytics_catalog_is_complete_in_every_supported_language(): void
    {
        $catalogs = collect(['de', 'en', 'fr', 'ar'])->mapWithKeys(function (string $language): array {
            $catalog = json_decode(
                file_get_contents(resource_path("js/lang/{$language}.json")),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            return [$language => $catalog];
        });

        $referenceKeys = $this->flattenKeys($catalogs['de']['product_analytics']);

        foreach ($catalogs as $language => $catalog) {
            $this->assertSame($referenceKeys, $this->flattenKeys($catalog['product_analytics']), "Missing product analytics translations for {$language}.");
            $this->assertNotEmpty($catalog['settings']['privacy']['product_analytics']);
            $this->assertNotEmpty($catalog['settings']['privacy']['product_analytics_help']);
            $this->assertNotEmpty($catalog['shell']['items']['analytics']);
        }
    }

    private function createOperationalSignals(User $user): void
    {
        TrainingLog::query()->create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Analytics-safe training signal',
            'status' => 'completed',
            'performed_at' => now(),
        ]);

        CommerceOrder::query()->create([
            'user_id' => $user->id,
            'type' => 'product',
            'provider' => 'bank_transfer',
            'amount_cents' => 1000,
            'currency' => 'EUR',
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $report */
    private function assertReportHasNoIdentityFields(array $report): void
    {
        $forbidden = ['id', 'user_id', 'name', 'email', 'birth_date', 'ip', 'location', 'health'];

        foreach ($this->flattenKeys($report) as $key) {
            $leaf = strtolower((string) str($key)->afterLast('.'));
            $this->assertNotContains($leaf, $forbidden, "Identity field [{$key}] leaked into the report.");
        }
    }

    /** @param array<string, mixed> $values
     * @return array<int, string>
     */
    private function flattenKeys(array $values, string $prefix = ''): array
    {
        $keys = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $keys[] = $path;

            if (is_array($value)) {
                $keys = [...$keys, ...$this->flattenKeys($value, $path)];
            }
        }

        sort($keys);

        return $keys;
    }
}
