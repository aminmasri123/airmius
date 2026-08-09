<?php

namespace Tests\Feature;

use App\Models\ApiIdempotencyKey;
use App\Models\CommerceOrder;
use App\Models\MarketplaceProduct;
use App\Models\OutfitSubscriptionPlan;
use App\Models\Setting;
use App\Models\User;
use App\Services\CommerceCartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutIdempotencySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_marketplace_checkout_replays_once_per_session_without_duplicate_order(): void
    {
        $this->configureBankTransfer();
        $this->withSession(['checkout_context' => 'guest-marketplace']);
        $this->withCookie(config('session.cookie'), $this->app['session']->getId())
            ->withCredentials();
        $product = $this->product();
        $payload = $this->guestProductPayload();
        $headers = $this->checkoutHeaders('guest-product:one-order');

        $first = $this->withHeaders($headers)
            ->postJson(route('guest.marketplace.products.checkout', $product), $payload)
            ->assertCreated();

        $second = $this->withHeaders($headers)
            ->postJson(route('guest.marketplace.products.checkout', $product), $payload)
            ->assertCreated()
            ->assertHeader('X-Idempotent-Replay', 'true');

        $this->assertSame($first->json('redirect_url'), $second->json('redirect_url'));
        $this->assertStringContainsString('/checkout/guest-commerce/', (string) $first->json('redirect_url'));
        $this->assertDatabaseCount('commerce_orders', 1);
        $this->assertDatabaseCount('commerce_order_items', 1);
        $this->assertStringStartsWith(
            'guest-session:',
            ApiIdempotencyKey::query()->sole()->actor_key,
        );
    }

    public function test_idempotency_scope_distinguishes_product_route_parameters(): void
    {
        $this->configureBankTransfer();
        $user = User::factory()->create();
        $firstProduct = $this->product(['title' => 'First product']);
        $secondProduct = $this->product(['title' => 'Second product']);
        $payload = $this->authenticatedProductPayload();
        $headers = $this->checkoutHeaders('auth-product:same-key-different-product');

        $this->actingAs($user)
            ->withHeaders($headers)
            ->postJson(route('auth.commerce.products.checkout', $firstProduct), $payload)
            ->assertCreated()
            ->assertHeaderMissing('X-Idempotent-Replay');

        $this->actingAs($user)
            ->withHeaders($headers)
            ->postJson(route('auth.commerce.products.checkout', $secondProduct), $payload)
            ->assertCreated()
            ->assertHeaderMissing('X-Idempotent-Replay');

        $this->assertDatabaseCount('commerce_orders', 2);
        $this->assertDatabaseCount('api_idempotency_keys', 2);
        $this->assertCount(2, ApiIdempotencyKey::query()->pluck('scope')->unique());
    }

    public function test_cart_checkout_replays_after_cart_is_atomically_consumed(): void
    {
        $this->configureBankTransfer();
        $user = User::factory()->create();
        $product = $this->product();
        $cart = app(CommerceCartService::class)->cartFor($user);
        $cart->items()->create([
            'marketplace_product_id' => $product->id,
            'quantity' => 2,
        ]);
        $headers = $this->checkoutHeaders('commerce-cart:network-retry');
        $payload = [
            'provider' => 'bank_transfer',
            'accepted_terms' => true,
            'shipping_country' => 'DE',
            'customer_type' => 'consumer',
        ];

        $first = $this->actingAs($user)
            ->withHeaders($headers)
            ->postJson(route('auth.commerce.cart.checkout'), $payload)
            ->assertCreated();

        $this->assertDatabaseCount('commerce_cart_items', 0);

        $second = $this->actingAs($user)
            ->withHeaders($headers)
            ->postJson(route('auth.commerce.cart.checkout'), $payload)
            ->assertCreated()
            ->assertHeader('X-Idempotent-Replay', 'true');

        $this->assertSame($first->json('redirect_url'), $second->json('redirect_url'));
        $this->assertDatabaseCount('commerce_orders', 1);
        $this->assertDatabaseCount('commerce_order_items', 1);
        $this->assertSame(2, CommerceOrder::query()->sole()->items()->sole()->quantity);
    }

    public function test_outfit_checkout_replays_and_same_key_can_target_a_different_plan(): void
    {
        $this->configureBankTransfer();
        $user = User::factory()->create();
        $firstPlan = $this->outfitPlan(['name' => 'Runner Box', 'slug' => 'runner-box']);
        $secondPlan = $this->outfitPlan(['name' => 'Team Box', 'slug' => 'team-box']);
        $payload = $this->outfitPayload();
        $headers = $this->checkoutHeaders('outfit:same-key-route-scope');

        $this->actingAs($user)
            ->withHeaders($headers)
            ->postJson(route('auth.outfit-subscriptions.store', $firstPlan), $payload)
            ->assertCreated()
            ->assertJsonPath('payment_action.type', 'bank_transfer');

        $this->actingAs($user)
            ->withHeaders($headers)
            ->postJson(route('auth.outfit-subscriptions.store', $firstPlan), $payload)
            ->assertCreated()
            ->assertHeader('X-Idempotent-Replay', 'true');

        $this->actingAs($user)
            ->withHeaders($headers)
            ->postJson(route('auth.outfit-subscriptions.store', $secondPlan), $payload)
            ->assertCreated()
            ->assertHeaderMissing('X-Idempotent-Replay');

        $this->assertDatabaseCount('outfit_subscriptions', 2);
        $this->assertDatabaseCount('api_idempotency_keys', 2);
    }

    public function test_authenticated_checkout_rejects_unapproved_product(): void
    {
        $this->configureBankTransfer();
        $user = User::factory()->create();
        $product = $this->product(['moderation_status' => 'pending']);

        $this->actingAs($user)
            ->withHeaders($this->checkoutHeaders('auth-product:unapproved'))
            ->postJson(route('auth.commerce.products.checkout', $product), $this->authenticatedProductPayload())
            ->assertNotFound();

        $this->assertDatabaseCount('commerce_orders', 0);
    }

    public function test_checkout_routes_clients_and_localized_errors_share_the_idempotency_contract(): void
    {
        $this->configureBankTransfer();
        $product = $this->product();

        $this->withHeaders([
            ...$this->checkoutHeaders('bad key'),
            'Accept-Language' => 'ar',
        ])->postJson(route('guest.marketplace.products.checkout', $product), $this->guestProductPayload())
            ->assertBadRequest()
            ->assertJsonPath('code', 'invalid_idempotency_key')
            ->assertJsonPath('message', __('server.idempotency.invalid_key', [], 'ar'));

        $authRoutes = file_get_contents(base_path('routes/auth.php'));
        $guestRoutes = file_get_contents(base_path('routes/guest.php'));
        $helper = file_get_contents(resource_path('js/composables/useIdempotentCheckout.js'));
        $clients = collect([
            resource_path('js/Pages/Guest/MarketplaceProductShow.vue'),
            resource_path('js/Pages/Auth/Dashboard/Commerce/ProductShow.vue'),
            resource_path('js/Pages/Auth/Dashboard/Commerce/Cart.vue'),
            resource_path('js/Pages/Auth/Dashboard/Commerce/Index.vue'),
            resource_path('js/Pages/Auth/Dashboard/OutfitSubscriptions/Index.vue'),
            resource_path('js/composables/useCommerceWorkspace.js'),
        ])->map(fn (string $path) => file_get_contents($path))->implode("\n");

        $this->assertSame(4, substr_count($authRoutes, 'EnsureIdempotentApiRequest::class'));
        $this->assertStringContainsString('EnsureIdempotentApiRequest::class', $guestRoutes);
        $this->assertStringContainsString("'Idempotency-Key': requestId", $helper);
        $this->assertStringContainsString('postIdempotentCheckout', $clients);
        $this->assertStringNotContainsString('form.post(isAuthenticated.value', $clients);
    }

    private function configureBankTransfer(): void
    {
        Setting::setValue('billing_iban', 'DE89370400440532013000');
        Setting::setValue('billing_bank_account_holder', 'Airmius');
        Setting::setValue('billing_payment_terms_days', 14);
    }

    private function product(array $overrides = []): MarketplaceProduct
    {
        return MarketplaceProduct::query()->create(array_merge([
            'title' => 'Idempotent Training Product',
            'description' => 'Safe checkout product.',
            'category' => 'course',
            'offer_type' => 'service',
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

    private function outfitPlan(array $overrides = []): OutfitSubscriptionPlan
    {
        return OutfitSubscriptionPlan::query()->create(array_merge([
            'name' => 'Secure Outfit Box',
            'slug' => 'secure-outfit-box',
            'description' => 'Monthly outfit.',
            'monthly_price_cents' => 2990,
            'sponsor_discount_cents' => 0,
            'currency' => 'EUR',
            'items_per_box' => 3,
            'branding_type' => 'none',
            'is_public' => true,
            'is_active' => true,
        ], $overrides));
    }

    private function guestProductPayload(): array
    {
        return [
            'guest_name' => 'Guest Athlete',
            'guest_email' => 'guest@example.test',
            'provider' => 'bank_transfer',
            'accepted_terms' => true,
            'quantity' => 1,
            'shipping_country' => 'DE',
            'customer_type' => 'consumer',
        ];
    }

    private function authenticatedProductPayload(): array
    {
        return [
            'provider' => 'bank_transfer',
            'accepted_terms' => true,
            'quantity' => 1,
            'shipping_country' => 'DE',
            'customer_type' => 'consumer',
        ];
    }

    private function outfitPayload(): array
    {
        return [
            'accepted_terms' => true,
            'accepted_contract' => true,
            'payment_provider' => 'bank_transfer',
            'shipping_name' => 'Amina Beispiel',
            'shipping_country' => 'DE',
            'shipping_street' => 'Sportallee',
            'shipping_house_number' => '7',
            'shipping_postal_code' => '10115',
            'shipping_city' => 'Berlin',
        ];
    }

    private function checkoutHeaders(string $key): array
    {
        return [
            'Accept' => 'application/json',
            'X-Checkout-Mode' => 'json',
            'Idempotency-Key' => $key,
        ];
    }
}
