<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\OutfitSubscription;
use App\Models\OutfitSubscriptionPlan;
use App\Models\User;
use App\Services\CommercePaymentGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PaymentCancellationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_commerce_cancel_requires_signature_and_only_cancels_pending_order(): void
    {
        $user = User::factory()->create();
        $order = $this->commerceOrder(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('commerce-checkout.cancel', $order))
            ->assertForbidden();

        $signedUrl = app(CommercePaymentGatewayService::class)->orderRoute($order, 'cancel');

        $this->actingAs($user)
            ->get($signedUrl)
            ->assertRedirect(route('auth.commerce.index'));

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertNull($order->checkout_url);
        $this->assertNotEmpty($order->payload['checkout_cancelled_at'] ?? null);
        $this->assertSame($user->id, $order->payload['checkout_cancelled_by'] ?? null);
    }

    public function test_authenticated_commerce_cancel_cannot_change_completed_order(): void
    {
        $user = User::factory()->create();
        $order = $this->commerceOrder([
            'user_id' => $user->id,
            'status' => 'completed',
        ]);

        $signedUrl = app(CommercePaymentGatewayService::class)->orderRoute($order, 'cancel');

        $this->actingAs($user)
            ->get($signedUrl)
            ->assertStatus(422);

        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_guest_commerce_cancel_requires_signature_and_preserves_private_response_headers(): void
    {
        $order = $this->commerceOrder([
            'guest_name' => 'Guest Athlete',
            'guest_email' => 'guest@example.test',
            'access_token' => 'private-guest-token',
        ]);

        $this->get(route('commerce-checkout.guest.cancel', [$order, $order->access_token]))
            ->assertForbidden();

        $signedUrl = app(CommercePaymentGatewayService::class)->orderRoute($order, 'cancel');

        $response = $this->get($signedUrl)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_commerce_provider_failure_marks_pending_order_failed_without_checkout_url(): void
    {
        config(['services.stripe.secret' => 'stripe-test-secret']);
        Http::fake([
            'api.stripe.com/*' => Http::response(['error' => ['message' => 'provider-private-detail']], 503),
        ]);

        $order = $this->commerceOrder([
            'provider' => 'stripe',
            'checkout_url' => 'https://stale.example.test/checkout',
        ]);

        try {
            app(CommercePaymentGatewayService::class)->createProviderCheckout($order);
            $this->fail('Expected the failed provider checkout to abort.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $order->refresh();
        $this->assertSame('failed', $order->status);
        $this->assertNull($order->checkout_url);
        $this->assertNotEmpty($order->payload['provider_failed_at'] ?? null);
        $this->assertStringNotContainsString('provider-private-detail', json_encode($order->payload));
    }

    public function test_outfit_checkout_cancel_requires_signature_and_only_cancels_pending_payment(): void
    {
        $user = User::factory()->create();
        $subscription = $this->outfitSubscription($user);

        $this->actingAs($user)
            ->get(route('outfit-subscription-checkout.cancel', $subscription))
            ->assertForbidden();

        $signedUrl = URL::temporarySignedRoute(
            'outfit-subscription-checkout.cancel',
            now()->addDay(),
            ['subscription' => $subscription],
        );

        $this->actingAs($user)
            ->get($signedUrl)
            ->assertRedirect(route('auth.outfit-subscriptions.index'));

        $subscription->refresh();
        $this->assertSame('cancelled', $subscription->status);
        $this->assertSame('cancelled', $subscription->payment_status);
        $this->assertNull($subscription->checkout_url);
        $this->assertNotNull($subscription->cancelled_at);
    }

    public function test_outfit_checkout_cancel_cannot_change_paid_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = $this->outfitSubscription($user, [
            'status' => 'active',
            'payment_status' => 'paid',
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'outfit-subscription-checkout.cancel',
            now()->addDay(),
            ['subscription' => $subscription],
        );

        $this->actingAs($user)
            ->get($signedUrl)
            ->assertStatus(422);

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertSame('paid', $subscription->payment_status);
    }

    public function test_outfit_paypal_provider_failure_marks_created_subscription_failed(): void
    {
        config([
            'services.paypal.client_id' => 'paypal-test-client',
            'services.paypal.client_secret' => 'paypal-test-secret',
            'services.paypal.mode' => 'sandbox',
        ]);
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'paypal-access-token']),
            '*/v1/catalogs/products' => Http::response(['id' => 'PRODUCT-1'], 201),
            '*/v1/billing/plans' => Http::response(['id' => 'PLAN-1'], 201),
            '*/v1/billing/subscriptions' => Http::response(['debug' => 'provider-private-detail'], 503),
        ]);

        $user = User::factory()->create();
        $plan = $this->outfitPlan();

        $this->actingAs($user)
            ->postJson(route('auth.outfit-subscriptions.store', $plan), [
                'accepted_terms' => true,
                'accepted_contract' => true,
                'payment_provider' => 'paypal',
                'shipping_name' => 'Amina Beispiel',
                'shipping_country' => 'DE',
                'shipping_street' => 'Sportallee',
                'shipping_house_number' => '7',
                'shipping_postal_code' => '10115',
                'shipping_city' => 'Berlin',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_provider');

        $subscription = OutfitSubscription::query()->sole();
        $this->assertSame('pending_payment', $subscription->status);
        $this->assertSame('failed', $subscription->payment_status);
        $this->assertNull($subscription->checkout_url);
        $this->assertNotEmpty($subscription->payment_payload['provider_failed_at'] ?? null);
        $this->assertStringNotContainsString('provider-private-detail', json_encode($subscription->payment_payload));
    }

    public function test_payment_cancellation_routes_and_provider_logs_keep_security_contract(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $commerceService = file_get_contents(app_path('Services/CommercePaymentGatewayService.php'));
        $outfitController = file_get_contents(app_path('Http/Controllers/OutfitSubscriptionController.php'));

        $this->assertSame(4, substr_count($routes, "->middleware(['signed', 'throttle:payment-actions'])"));
        $this->assertStringContainsString('temporarySignedRoute', $commerceService);
        $this->assertStringContainsString('temporarySignedRoute', $outfitController);
        $this->assertStringNotContainsString("['body' => \$response->json()]", $commerceService);
        $this->assertStringNotContainsString("['body' => \$response->json()]", $outfitController);

        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            $this->app->setLocale($locale);
            $this->assertNotSame(
                'commerce.validation.provider_checkout_failed',
                __('commerce.validation.provider_checkout_failed', ['provider' => 'Stripe']),
            );
            $this->assertNotSame(
                'outfit_subscription.validation.provider_checkout_failed',
                __('outfit_subscription.validation.provider_checkout_failed'),
            );
        }
    }

    private function commerceOrder(array $overrides = []): CommerceOrder
    {
        return CommerceOrder::query()->create(array_merge([
            'type' => 'marketplace_product',
            'provider' => 'paypal',
            'amount_cents' => 4900,
            'currency' => 'EUR',
            'status' => 'pending',
            'checkout_url' => 'https://checkout.example.test/session',
            'payload' => ['pricing' => ['gross_cents' => 4900]],
        ], $overrides));
    }

    private function outfitPlan(): OutfitSubscriptionPlan
    {
        return OutfitSubscriptionPlan::query()->create([
            'name' => 'Secure Runner Box',
            'slug' => 'secure-runner-box',
            'description' => 'Monthly running outfit.',
            'monthly_price_cents' => 2990,
            'sponsor_discount_cents' => 0,
            'currency' => 'EUR',
            'items_per_box' => 3,
            'branding_type' => 'none',
            'is_public' => true,
            'is_active' => true,
        ]);
    }

    private function outfitSubscription(User $user, array $overrides = []): OutfitSubscription
    {
        $plan = $this->outfitPlan();

        return OutfitSubscription::query()->create(array_merge([
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'pending_payment',
            'payment_provider' => 'paypal',
            'payment_status' => 'pending',
            'monthly_price_cents' => $plan->monthly_price_cents,
            'currency' => 'EUR',
            'checkout_url' => 'https://checkout.example.test/subscription',
        ], $overrides));
    }
}
