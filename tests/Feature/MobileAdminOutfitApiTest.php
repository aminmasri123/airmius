<?php

namespace Tests\Feature;

use App\Models\OutfitDelivery;
use App\Models\OutfitSubscription;
use App\Models\OutfitSubscriptionPlan;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileAdminOutfitApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_outfit_manager_receives_real_data_without_provider_payloads(): void
    {
        $admin = $this->manager();
        Sanctum::actingAs($admin);

        $customer = User::factory()->create(['name' => 'Outfit Kunde']);
        $plan = $this->plan([
            'paypal_product_id' => 'secret-product',
            'paypal_plan_id' => 'secret-plan',
            'paypal_plan_signature' => 'secret-signature',
            'paypal_payload' => ['token' => 'secret-token'],
        ]);
        $subscription = OutfitSubscription::query()->create([
            'user_id' => $customer->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'pending_payment',
            'payment_provider' => 'bank_transfer',
            'payment_status' => 'pending',
            'payment_reference' => 'OUTFIT-MOBILE-1',
            'payment_payload' => ['account' => 'must-not-leak'],
            'monthly_price_cents' => 2990,
            'currency' => 'EUR',
            'shipping_name' => 'Outfit Kunde',
            'shipping_country' => 'DE',
            'shipping_street' => 'Sportweg',
            'shipping_postal_code' => '10115',
            'shipping_city' => 'Berlin',
        ]);
        OutfitDelivery::query()->create([
            'outfit_subscription_id' => $subscription->id,
            'status' => 'preparing',
            'delivery_month' => now()->startOfMonth(),
            'items' => ['Trikot', 'Shorts'],
            'issue_status' => 'open',
            'issue_description' => 'Falsche Größe',
        ]);

        $response = $this->getJson('/api/v1/admin/outfits')
            ->assertOk()
            ->assertJsonPath('data.abilities.manage', true)
            ->assertJsonPath('data.summary.plans', 1)
            ->assertJsonPath('data.summary.subscriptions', 1)
            ->assertJsonPath('data.summary.deliveries', 1)
            ->assertJsonPath('data.summary.open_delivery_issues', 1)
            ->assertJsonFragment(['payment_reference' => 'OUTFIT-MOBILE-1'])
            ->assertJsonFragment(['description' => 'Falsche Größe']);

        $payload = $response->getContent();
        $this->assertStringNotContainsString('secret-product', $payload);
        $this->assertStringNotContainsString('secret-plan', $payload);
        $this->assertStringNotContainsString('secret-signature', $payload);
        $this->assertStringNotContainsString('secret-token', $payload);
        $this->assertStringNotContainsString('must-not-leak', $payload);
    }

    public function test_outfit_manager_can_run_mobile_plan_subscription_and_delivery_actions(): void
    {
        Notification::fake();
        Mail::fake();
        $admin = $this->manager();
        Sanctum::actingAs($admin);

        $customer = User::factory()->create();
        $plan = $this->plan();
        $subscription = OutfitSubscription::query()->create([
            'user_id' => $customer->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'pending_payment',
            'payment_provider' => 'bank_transfer',
            'payment_status' => 'pending',
            'monthly_price_cents' => 2990,
            'currency' => 'EUR',
            'shipping_name' => 'Alt Name',
            'shipping_country' => 'DE',
            'shipping_street' => 'Altweg',
            'shipping_postal_code' => '10115',
            'shipping_city' => 'Berlin',
        ]);

        $this->putJson(
            "/api/v1/admin/outfits/plans/{$plan->id}",
            $this->planPayload(['monthly_price_cents' => 3490]),
        )
            ->assertOk()
            ->assertJsonPath('data.monthly_price_cents', 3490);

        $this->putJson(
            "/api/v1/admin/outfits/subscriptions/{$subscription->id}/shipping-address",
            [
                'shipping_name' => 'Neu Name',
                'shipping_country' => 'at',
                'shipping_street' => 'Neue Straße',
                'shipping_house_number' => '7',
                'shipping_postal_code' => '1010',
                'shipping_city' => 'Wien',
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.shipping_address.country', 'AT');

        $this->postJson(
            "/api/v1/admin/outfits/subscriptions/{$subscription->id}/mark-paid",
            ['payment_note' => 'Mobil bestätigt'],
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.payment_status', 'paid');

        $delivery = $subscription->deliveries()->firstOrFail();
        $this->postJson(
            "/api/v1/admin/outfits/deliveries/{$delivery->id}/shipped",
            [
                'tracking_number' => 'TRACK-123',
                'tracking_url' => 'https://tracking.example.test/TRACK-123',
                'carrier' => 'DHL',
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'shipped')
            ->assertJsonPath('data.tracking_number', 'TRACK-123');

        $this->postJson(
            "/api/v1/admin/outfits/deliveries/{$delivery->id}/delivered",
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');

        $this->postJson('/api/v1/admin/outfits/visuals', [
            'hero_source' => '/images/outfit-mobile.webp',
        ])
            ->assertOk()
            ->assertJsonPath('data.source', '/images/outfit-mobile.webp');
    }

    public function test_outfit_mobile_admin_routes_require_authentication_and_permission(): void
    {
        $this->getJson('/api/v1/admin/outfits')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/outfits')->assertForbidden();
        $this->postJson('/api/v1/admin/outfits/plans', $this->planPayload())
            ->assertForbidden();
    }

    private function manager(): User
    {
        Permission::firstOrCreate(['name' => 'outfit-subscriptions.manage']);
        $user = User::factory()->create();
        $user->givePermissionTo('outfit-subscriptions.manage');

        return $user;
    }

    private function plan(array $overrides = []): OutfitSubscriptionPlan
    {
        return OutfitSubscriptionPlan::query()->create(array_merge([
            'name' => 'Mobile Outfit Box',
            'slug' => 'mobile-outfit-box',
            'monthly_price_cents' => 2990,
            'sponsor_discount_cents' => 0,
            'currency' => 'EUR',
            'target_gender' => 'all',
            'sizes' => ['M', 'L'],
            'sports' => ['football'],
            'items_per_box' => 3,
            'branding_type' => 'airmius',
            'is_public' => true,
            'is_active' => true,
        ], $overrides));
    }

    private function planPayload(array $overrides = []): array
    {
        return array_merge([
            'sponsor_id' => null,
            'name' => 'Mobile Outfit Box',
            'description' => 'Drei Sportartikel pro Box.',
            'contract_title' => 'Outfit Vertrag',
            'contract_terms' => ['Monatliche Lieferung'],
            'minimum_term_months' => 0,
            'pause_allowed_after_months' => 0,
            'cancellation_notice_days' => 14,
            'monthly_price_cents' => 2990,
            'sponsor_discount_cents' => 0,
            'currency' => 'EUR',
            'target_gender' => 'all',
            'sizes' => ['M', 'L'],
            'sports' => ['football'],
            'items_per_box' => 3,
            'branding_type' => 'airmius',
            'sort_order' => 1,
            'is_public' => true,
            'is_active' => true,
        ], $overrides);
    }
}
