<?php

namespace Tests\Feature;

use App\Http\Controllers\CommerceCheckoutController;
use App\Models\CommerceOrder;
use App\Models\CommerceShippingRate;
use App\Models\CommerceTaxRate;
use App\Models\CommerceWarehouse;
use App\Models\LearningCoupon;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceProductInventory;
use App\Models\MarketplaceSellerApplication;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_marketplace_can_be_rendered(): void
    {
        $this->createPublishedProduct();

        $response = $this->get('/marketplace');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Guest/Marketplace'));
    }

    public function test_guest_can_open_product_detail(): void
    {
        $product = $this->createPublishedProduct();

        $response = $this->get('/marketplace/products/'.$product->id);

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Guest/MarketplaceProductShow'));
    }

    public function test_unapproved_product_cannot_be_opened_or_checked_out_directly(): void
    {
        $product = $this->createPublishedProduct(['moderation_status' => 'pending']);

        $this->get(route('guest.marketplace.products.show', $product))->assertNotFound();
        $this->post(route('guest.marketplace.products.checkout', $product))->assertNotFound();
    }

    public function test_product_detail_only_exposes_configured_payment_providers(): void
    {
        config([
            'services.stripe.secret' => null,
            'services.paypal.client_id' => null,
            'services.paypal.client_secret' => null,
        ]);
        Setting::setValue('billing_iban', 'DE89370400440532013000');
        $product = $this->createPublishedProduct();

        $this->get('/marketplace/products/'.$product->id)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Guest/MarketplaceProductShow')
                ->has('paymentProviders', 1)
                ->where('paymentProviders.0.value', 'bank_transfer'));
    }

    public function test_unconfigured_payment_provider_cannot_start_checkout(): void
    {
        config([
            'services.stripe.secret' => null,
            'services.paypal.client_id' => null,
            'services.paypal.client_secret' => null,
        ]);
        Setting::setValue('billing_iban', 'DE89370400440532013000');
        $product = $this->createPublishedProduct(['learning_course_id' => null]);

        $this->from('/marketplace/products/'.$product->id)
            ->post('/marketplace/products/'.$product->id.'/checkout', [
                'guest_name' => 'Gast Kaeufer',
                'guest_email' => 'gast@example.com',
                'provider' => 'stripe',
                'accepted_terms' => true,
                'shipping_country' => 'DE',
            ])
            ->assertRedirect('/marketplace/products/'.$product->id)
            ->assertSessionHasErrors(['provider']);

        $this->assertSame(0, CommerceOrder::query()->count());
    }

    public function test_guest_can_start_bank_transfer_checkout(): void
    {
        Setting::setValue('billing_iban', 'DE89370400440532013000');
        Setting::setValue('billing_bank_account_holder', 'Airmius');
        Setting::setValue('billing_payment_terms_days', 14);

        $product = $this->createPublishedProduct(['learning_course_id' => null]);

        $response = $this->post('/marketplace/products/'.$product->id.'/checkout', [
            'guest_name' => 'Gast Kaeufer',
            'guest_email' => 'gast@example.com',
            'provider' => 'bank_transfer',
            'accepted_terms' => true,
            'shipping_country' => 'DE',
        ]);

        $order = CommerceOrder::query()->first();

        $this->assertNotNull($order);
        $this->assertSame('gast@example.com', $order->guest_email);
        $this->assertNotNull($order->access_token);
        $this->assertSame('awaiting_transfer', $order->status);
        $response->assertRedirect(route('commerce-checkout.guest.bank-transfer.show', [$order, $order->access_token]));
    }

    public function test_marketplace_filters_physical_products_by_country_inventory(): void
    {
        $product = $this->createPublishedProduct([
            'title' => 'DE Inventory Schuh',
            'category' => 'product',
            'offer_type' => 'physical_product',
            'product_type' => 'physical',
            'is_shippable' => true,
            'manages_stock' => true,
            'stock_quantity' => 0,
        ]);
        $warehouse = CommerceWarehouse::create([
            'name' => 'DE Lager',
            'country_code' => 'DE',
        ]);
        MarketplaceProductInventory::create([
            'marketplace_product_id' => $product->id,
            'commerce_warehouse_id' => $warehouse->id,
            'country_code' => 'DE',
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $this->get('/marketplace?country=DE')
            ->assertOk()
            ->assertSee('DE Inventory Schuh');

        $this->get('/marketplace?country=FR')
            ->assertOk()
            ->assertDontSee('DE Inventory Schuh');
    }

    public function test_guest_checkout_records_fulfillment_warehouse_for_country_inventory(): void
    {
        Setting::setValue('billing_iban', 'DE89370400440532013000');
        $product = $this->createPublishedProduct([
            'title' => 'Warehouse Checkout Schuh',
            'learning_course_id' => null,
            'category' => 'product',
            'offer_type' => 'physical_product',
            'product_type' => 'physical',
            'is_shippable' => true,
            'manages_stock' => true,
            'stock_quantity' => 0,
        ]);
        $warehouse = CommerceWarehouse::create([
            'name' => 'DE Lager',
            'country_code' => 'DE',
        ]);
        MarketplaceProductInventory::create([
            'marketplace_product_id' => $product->id,
            'commerce_warehouse_id' => $warehouse->id,
            'country_code' => 'DE',
            'stock_quantity' => 3,
            'is_active' => true,
        ]);

        $this->post('/marketplace/products/'.$product->id.'/checkout', [
            'guest_name' => 'Gast Kaeufer',
            'guest_email' => 'gast@example.com',
            'provider' => 'bank_transfer',
            'accepted_terms' => true,
            'shipping_country' => 'DE',
        ])->assertRedirect();

        $order = CommerceOrder::query()->with('items')->first();

        $this->assertNotNull($order);
        $this->assertSame($warehouse->id, $order->items->first()->commerce_warehouse_id);
    }

    public function test_checkout_uses_origin_warehouse_shipping_rate(): void
    {
        Setting::setValue('billing_iban', 'DE89370400440532013000');
        CommerceShippingRate::query()->create([
            'name' => 'FR nach DE Versand',
            'origin_country_code' => 'FR',
            'country_code' => 'DE',
            'amount_cents' => 890,
            'currency' => 'EUR',
            'is_active' => true,
            'priority' => 1,
        ]);
        $product = $this->createPublishedProduct([
            'title' => 'Frankreich Lager Schuh',
            'learning_course_id' => null,
            'category' => 'product',
            'offer_type' => 'physical_product',
            'product_type' => 'physical',
            'is_shippable' => true,
            'manages_stock' => true,
            'stock_quantity' => 2,
        ]);
        $warehouse = CommerceWarehouse::create([
            'name' => 'FR Lager',
            'country_code' => 'FR',
        ]);
        MarketplaceProductInventory::create([
            'marketplace_product_id' => $product->id,
            'commerce_warehouse_id' => $warehouse->id,
            'country_code' => 'DE',
            'stock_quantity' => 2,
            'is_active' => true,
        ]);

        $this->post('/marketplace/products/'.$product->id.'/checkout', [
            'guest_name' => 'Gast Kaeufer',
            'guest_email' => 'gast@example.com',
            'provider' => 'bank_transfer',
            'accepted_terms' => true,
            'shipping_country' => 'DE',
        ])->assertRedirect();

        $order = CommerceOrder::query()->firstOrFail();

        $this->assertSame(890, $order->shipping_cents);
        $this->assertSame('FR nach DE Versand', data_get($order->payload, 'pricing.shipping_label'));
    }

    public function test_valid_eu_b2b_vat_id_uses_reverse_charge(): void
    {
        Setting::setValue('billing_iban', 'DE89370400440532013000');
        Setting::setValue('commerce_company_country', 'DE');
        CommerceTaxRate::query()->create([
            'name' => 'France VAT',
            'country_code' => 'FR',
            'tax_class' => 'standard',
            'tax_label' => 'TVA',
            'rate_percent' => 20,
            'currency' => 'EUR',
            'is_default' => false,
            'is_active' => true,
            'priority' => 1,
        ]);
        $product = $this->createPublishedProduct([
            'learning_course_id' => null,
            'price_cents' => 12000,
        ]);

        $this->post('/marketplace/products/'.$product->id.'/checkout', [
            'guest_name' => 'FR Firma',
            'guest_email' => 'buyer@example.fr',
            'provider' => 'bank_transfer',
            'accepted_terms' => true,
            'shipping_country' => 'FR',
            'customer_type' => 'business',
            'customer_company' => 'Club Pro FR',
            'customer_vat_id' => 'FR AB 123456789',
        ])->assertRedirect();

        $order = CommerceOrder::query()->firstOrFail();

        $this->assertTrue($order->customer_vat_is_valid);
        $this->assertSame(0, $order->tax_cents);
        $this->assertTrue(data_get($order->payload, 'pricing.reverse_charge'));
        $this->assertSame('eu_b2b_reverse_charge', data_get($order->payload, 'pricing.tax_rule'));
    }

    public function test_invalid_eu_b2b_vat_id_does_not_use_reverse_charge(): void
    {
        Setting::setValue('billing_iban', 'DE89370400440532013000');
        Setting::setValue('commerce_company_country', 'DE');
        CommerceTaxRate::query()->create([
            'name' => 'France VAT',
            'country_code' => 'FR',
            'tax_class' => 'standard',
            'tax_label' => 'TVA',
            'rate_percent' => 20,
            'currency' => 'EUR',
            'is_default' => false,
            'is_active' => true,
            'priority' => 1,
        ]);
        $product = $this->createPublishedProduct([
            'learning_course_id' => null,
            'price_cents' => 12000,
        ]);

        $this->post('/marketplace/products/'.$product->id.'/checkout', [
            'guest_name' => 'FR Firma',
            'guest_email' => 'buyer@example.fr',
            'provider' => 'bank_transfer',
            'accepted_terms' => true,
            'shipping_country' => 'FR',
            'customer_type' => 'business',
            'customer_company' => 'Club Pro FR',
            'customer_vat_id' => 'FR123',
        ])->assertRedirect();

        $order = CommerceOrder::query()->firstOrFail();

        $this->assertFalse($order->customer_vat_is_valid);
        $this->assertGreaterThan(0, $order->tax_cents);
        $this->assertFalse(data_get($order->payload, 'pricing.reverse_charge'));
        $this->assertSame('eu_b2c_destination', data_get($order->payload, 'pricing.tax_rule'));
    }

    public function test_paid_warehouse_order_decrements_country_inventory(): void
    {
        $product = $this->createPublishedProduct([
            'title' => 'Warehouse Paid Schuh',
            'category' => 'product',
            'offer_type' => 'physical_product',
            'product_type' => 'physical',
            'is_shippable' => true,
            'manages_stock' => true,
            'stock_quantity' => 0,
        ]);
        $warehouse = CommerceWarehouse::create([
            'name' => 'DE Lager',
            'country_code' => 'DE',
        ]);
        $inventory = MarketplaceProductInventory::create([
            'marketplace_product_id' => $product->id,
            'commerce_warehouse_id' => $warehouse->id,
            'country_code' => 'DE',
            'stock_quantity' => 4,
            'is_active' => true,
        ]);
        $order = CommerceOrder::query()->create([
            'orderable_type' => $product::class,
            'orderable_id' => $product->id,
            'type' => 'marketplace_product',
            'provider' => 'bank_transfer',
            'amount_cents' => 4900,
            'currency' => 'EUR',
            'status' => 'pending',
            'payload' => ['pricing' => ['gross_cents' => 4900]],
        ]);
        $order->items()->create([
            'orderable_type' => $product::class,
            'orderable_id' => $product->id,
            'commerce_warehouse_id' => $warehouse->id,
            'title' => $product->title,
            'quantity' => 2,
            'unit_gross_cents' => 4900,
            'total_cents' => 9800,
            'currency' => 'EUR',
            'is_shippable' => true,
        ]);

        app(CommerceCheckoutController::class)->activate($order);

        $this->assertSame(2, $inventory->fresh()->stock_quantity);
    }

    public function test_seller_can_create_product_with_country_inventory(): void
    {
        $seller = User::factory()->create();
        MarketplaceSellerApplication::query()->create([
            'user_id' => $seller->id,
            'status' => 'approved',
            'applicant_type' => 'private',
            'accepted_rules' => [
                'product_truth',
                'rights',
                'shipping_returns',
                'commission',
                'data_privacy',
            ],
        ]);

        $this->actingAs($seller)
            ->post(route('auth.commerce.products.store'), [
                'title' => 'Internationaler Laufschuh',
                'description' => 'Ein Schuh mit Laenderbestand.',
                'offer_type' => 'physical_product',
                'category' => 'equipment',
                'product_type' => 'single',
                'is_shippable' => true,
                'manages_stock' => true,
                'stock_quantity' => 8,
                'price_cents' => 9900,
                'tax_class' => 'standard',
                'inventories' => [
                    [
                        'country_code' => 'DE',
                        'stock_quantity' => 5,
                        'low_stock_threshold' => 1,
                        'lead_time_days' => 2,
                        'city' => 'Berlin',
                    ],
                    [
                        'country_code' => 'FR',
                        'stock_quantity' => 3,
                        'low_stock_threshold' => 1,
                        'lead_time_days' => 4,
                        'city' => 'Paris',
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $product = MarketplaceProduct::query()->where('title', 'Internationaler Laufschuh')->firstOrFail();

        $this->assertSame(8, $product->stock_quantity);
        $this->assertSame(['DE', 'FR'], $product->available_countries);
        $this->assertSame(2, $product->inventories()->where('is_active', true)->count());
        $this->assertTrue(CommerceWarehouse::query()->where('user_id', $seller->id)->where('country_code', 'DE')->exists());
    }

    public function test_paid_learning_product_grants_course_access_after_completed_order(): void
    {
        $tutor = User::factory()->create();
        $buyer = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Premium Sprintkurs',
            'slug' => 'premium-sprintkurs',
            'category' => 'training',
            'level' => 'advanced',
            'status' => 'published',
            'is_public' => true,
            'is_free' => false,
            'price_cents' => 4900,
            'published_at' => now(),
        ]);
        $product = $this->createPublishedProduct([
            'learning_course_id' => $course->id,
            'offer_type' => 'online_course',
            'product_type' => 'digital',
            'is_shippable' => false,
            'manages_stock' => false,
        ]);
        $order = CommerceOrder::query()->create([
            'user_id' => $buyer->id,
            'orderable_type' => $product::class,
            'orderable_id' => $product->id,
            'type' => 'marketplace_product',
            'provider' => 'bank_transfer',
            'amount_cents' => 4900,
            'currency' => 'EUR',
            'status' => 'pending',
            'payload' => ['pricing' => ['gross_cents' => 4900]],
        ]);
        $order->items()->create([
            'orderable_type' => $product::class,
            'orderable_id' => $product->id,
            'title' => $product->title,
            'quantity' => 1,
            'unit_gross_cents' => 4900,
            'total_cents' => 4900,
            'currency' => 'EUR',
            'is_shippable' => false,
        ]);

        app(CommerceCheckoutController::class)->activate($order);

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertTrue(
            LearningEnrollment::query()
                ->where('learning_course_id', $course->id)
                ->where('user_id', $buyer->id)
                ->where('status', 'active')
                ->exists()
        );

        $this->actingAs($buyer)
            ->get(route('auth.commerce.index', ['tab' => 'invoices']))
            ->assertOk()
            ->assertSee('learning_course')
            ->assertSee('Premium Sprintkurs');
    }

    public function test_guest_must_login_before_buying_linked_learning_product(): void
    {
        $this->app['auth']->guard()->logout();

        $tutor = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Login Kurs',
            'slug' => 'login-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
        ]);
        $product = $this->createPublishedProduct([
            'learning_course_id' => $course->id,
            'offer_type' => 'online_course',
            'product_type' => 'digital',
            'is_shippable' => false,
            'manages_stock' => false,
        ]);

        $response = $this->from('/marketplace/products/'.$product->id)->post('/marketplace/products/'.$product->id.'/checkout', [
            'guest_name' => 'Gast Kaeufer',
            'guest_email' => 'gast@example.com',
            'provider' => 'bank_transfer',
            'accepted_terms' => true,
            'shipping_country' => 'DE',
        ]);

        $response->assertRedirect('/marketplace/products/'.$product->id);
        $response->assertSessionHasErrors(['login_checkout']);
        $this->assertSame(0, CommerceOrder::query()->count());
    }

    public function test_learning_coupon_reduces_checkout_amount(): void
    {
        Setting::setValue('billing_iban', 'DE89370400440532013000');
        Setting::setValue('billing_bank_account_holder', 'Airmius');
        Setting::setValue('billing_payment_terms_days', 14);

        $tutor = User::factory()->create();
        $buyer = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Coupon Kurs',
            'slug' => 'coupon-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'is_free' => false,
            'price_cents' => 4900,
            'published_at' => now(),
        ]);
        $product = $this->createPublishedProduct([
            'learning_course_id' => $course->id,
            'price_cents' => 4900,
        ]);
        LearningCoupon::query()->create([
            'learning_course_id' => $course->id,
            'code' => 'TEAM50',
            'discount_type' => 'percent',
            'discount_value' => 50,
            'is_active' => true,
        ]);

        $this->actingAs($buyer)
            ->post(route('auth.commerce.products.checkout', $product), [
                'provider' => 'bank_transfer',
                'accepted_terms' => true,
                'shipping_country' => 'DE',
                'coupon_code' => 'TEAM50',
            ])
            ->assertRedirect();

        $order = CommerceOrder::query()->firstOrFail();
        $this->assertSame(2450, $order->amount_cents);
        $this->assertSame('TEAM50', data_get($order->payload, 'pricing.learning_coupon.code'));
        $this->assertSame(2450, $order->items()->first()->total_cents);
    }

    public function test_cancelled_learning_order_revokes_course_access(): void
    {
        $tutor = User::factory()->create();
        $buyer = User::factory()->create();
        $course = LearningCourse::query()->create([
            'user_id' => $tutor->id,
            'title' => 'Storno Kurs',
            'slug' => 'storno-kurs',
            'category' => 'training',
            'level' => 'beginner',
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
        ]);
        $product = $this->createPublishedProduct([
            'learning_course_id' => $course->id,
            'offer_type' => 'online_course',
            'product_type' => 'digital',
            'is_shippable' => false,
            'manages_stock' => false,
        ]);
        $order = CommerceOrder::query()->create([
            'user_id' => $buyer->id,
            'orderable_type' => $product::class,
            'orderable_id' => $product->id,
            'type' => 'marketplace_product',
            'provider' => 'bank_transfer',
            'amount_cents' => 4900,
            'currency' => 'EUR',
            'status' => 'completed',
            'payload' => ['pricing' => ['gross_cents' => 4900]],
        ]);
        $order->items()->create([
            'orderable_type' => $product::class,
            'orderable_id' => $product->id,
            'title' => $product->title,
            'quantity' => 1,
            'unit_gross_cents' => 4900,
            'total_cents' => 4900,
            'currency' => 'EUR',
            'is_shippable' => false,
        ]);
        $enrollment = LearningEnrollment::query()->create([
            'learning_course_id' => $course->id,
            'user_id' => $buyer->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->actingAs($buyer)
            ->post(route('auth.commerce.orders.cancel', $order))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('cancelled', $enrollment->fresh()->status);
    }

    private function createPublishedProduct(array $overrides = []): MarketplaceProduct
    {
        return MarketplaceProduct::create(array_merge([
            'title' => 'Lauftechnik Kurs',
            'description' => 'Ein Kurs fuer bessere Lauftechnik.',
            'category' => 'course',
            'offer_type' => 'online_course',
            'product_type' => 'digital',
            'is_shippable' => false,
            'manages_stock' => false,
            'price_cents' => 4900,
            'currency' => 'EUR',
            'status' => 'published',
            'moderation_status' => 'approved',
            'commission_percent' => 10,
            'payout_status' => 'pending_sales',
        ], $overrides));
    }
}
