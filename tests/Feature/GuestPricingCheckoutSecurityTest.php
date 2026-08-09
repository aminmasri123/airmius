<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\PaymentCheckout;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuestPricingCheckoutSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_pricing_checkout_uses_post_and_replays_duplicate_request_once(): void
    {
        config(['services.stripe.secret' => 'sk_test_guest_pricing']);
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_guest_pricing',
                'url' => 'https://checkout.stripe.test/guest-pricing',
            ]),
        ]);

        $user = User::factory()->create(['country' => 'DE']);
        $plan = $this->paidPlan();
        $payload = $this->checkoutPayload();
        $headers = $this->checkoutHeaders('pricing:duplicate-request');

        $this->actingAs($user);

        $this->withHeaders($headers)
            ->postJson(route('subscription-checkout.store', $plan), $payload)
            ->assertOk()
            ->assertJsonPath('redirect_url', 'https://checkout.stripe.test/guest-pricing');

        $this->withHeaders($headers)
            ->postJson(route('subscription-checkout.store', $plan), $payload)
            ->assertOk()
            ->assertHeader('X-Idempotent-Replay', 'true')
            ->assertJsonPath('redirect_url', 'https://checkout.stripe.test/guest-pricing');

        $this->withHeaders($headers)
            ->postJson(route('subscription-checkout.store', $plan), array_merge($payload, ['provider' => 'paypal']))
            ->assertConflict()
            ->assertJsonPath('error.code', 'idempotency_payload_conflict');

        $this->assertDatabaseCount('payment_checkouts', 1);
        $this->assertDatabaseCount('subscription_invoices', 1);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains((string) $request['cancel_url'], 'signature='));
    }

    public function test_removed_get_start_route_cannot_create_a_checkout(): void
    {
        $user = User::factory()->create();
        $plan = $this->paidPlan();

        $this->assertFalse(Route::has('subscription-checkout.start'));

        $this->actingAs($user)
            ->get("/checkout/subscriptions/{$plan->id}/start")
            ->assertNotFound();

        $this->assertDatabaseCount('payment_checkouts', 0);
    }

    public function test_hidden_plan_and_foreign_club_cannot_be_purchased(): void
    {
        config(['services.stripe.secret' => 'sk_test_guest_pricing']);
        Http::fake();

        $buyer = User::factory()->create(['country' => 'DE']);
        $otherOwner = User::factory()->create();
        $foreignClub = Club::factory()->create(['owner_id' => $otherOwner->id]);
        $hiddenPlan = $this->paidPlan(['slug' => 'hidden-guest-plan', 'is_public' => false]);
        $clubPlan = $this->paidPlan(['slug' => 'secure-club-plan', 'target_actor' => 'verein']);

        $this->actingAs($buyer);

        $this->withHeaders($this->checkoutHeaders('pricing:hidden-plan'))
            ->postJson(route('subscription-checkout.store', $hiddenPlan), $this->checkoutPayload())
            ->assertNotFound();

        $this->withHeaders($this->checkoutHeaders('pricing:foreign-club'))
            ->postJson(route('subscription-checkout.store', $clubPlan), $this->checkoutPayload([
                'club_id' => $foreignClub->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('club_id');

        $this->assertDatabaseCount('payment_checkouts', 0);
    }

    public function test_provider_failure_closes_checkout_and_invoice(): void
    {
        config(['services.stripe.secret' => 'sk_test_guest_pricing']);
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'error' => ['message' => 'Sensitive provider detail'],
            ], 503),
        ]);

        $user = User::factory()->create(['country' => 'DE']);
        $plan = $this->paidPlan();

        $this->actingAs($user)
            ->withHeaders($this->checkoutHeaders('pricing:failed-provider'))
            ->postJson(route('subscription-checkout.store', $plan), $this->checkoutPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('checkout');

        $this->assertDatabaseHas('payment_checkouts', [
            'user_id' => $user->id,
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('subscription_invoices', [
            'user_id' => $user->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_mobile_provider_failure_also_closes_checkout_and_invoice(): void
    {
        config(['services.stripe.secret' => 'sk_test_mobile_pricing']);
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([], 503),
        ]);

        $user = User::factory()->create(['country' => 'DE']);
        $plan = $this->paidPlan(['slug' => 'failed-mobile-provider-plan']);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/subscription-plans/{$plan->id}/checkout", [
            'provider' => 'stripe',
            'billing_interval' => 'monthly',
            'accepted_terms' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('checkout');

        $this->assertDatabaseHas('payment_checkouts', ['user_id' => $user->id, 'status' => 'failed']);
        $this->assertDatabaseHas('subscription_invoices', ['user_id' => $user->id, 'status' => 'cancelled']);
    }

    public function test_checkout_cancel_requires_signature_and_cancels_invoice_atomically(): void
    {
        $user = User::factory()->create();
        $plan = $this->paidPlan();
        $checkout = PaymentCheckout::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'provider' => 'stripe',
            'billing_interval' => 'monthly',
            'amount_cents' => 1299,
            'currency' => 'EUR',
            'status' => 'pending',
        ]);
        SubscriptionInvoice::query()->create([
            'payment_checkout_id' => $checkout->id,
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'subscription_type' => 'user',
            'number' => 'AIR-TEST-CANCEL-1',
            'title' => 'Test invoice',
            'amount_cents' => 1299,
            'currency' => 'EUR',
            'status' => 'open',
        ]);

        $this->actingAs($user)
            ->get(route('subscription-checkout.cancel', $checkout))
            ->assertForbidden();

        $signedUrl = URL::temporarySignedRoute(
            'subscription-checkout.cancel',
            now()->addMinute(),
            ['checkout' => $checkout],
        );

        $this->get($signedUrl)
            ->assertRedirect(route('guest.pricing'));

        $this->assertDatabaseHas('payment_checkouts', ['id' => $checkout->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('subscription_invoices', ['payment_checkout_id' => $checkout->id, 'status' => 'cancelled']);
    }

    public function test_pricing_exposes_only_manageable_clubs_and_active_club_plan(): void
    {
        $user = User::factory()->create();
        $otherOwner = User::factory()->create();
        $managedClub = Club::factory()->create(['name' => 'Managed Club', 'owner_id' => $user->id]);
        Club::factory()->create(['name' => 'Foreign Club', 'owner_id' => $otherOwner->id]);
        $plan = $this->paidPlan(['slug' => 'owned-club-plan', 'target_actor' => 'verein']);
        $managedClub->currentSubscription()->updateOrCreate([], [
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('guest.pricing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Guest/Pricing')
                ->has('checkoutClubs', 1)
                ->where('checkoutClubs.0.name', 'Managed Club')
                ->where("planGroups.verein.{$this->planIndex($plan)}.is_owned", true));
    }

    public function test_pricing_source_contract_is_ajax_multilingual_and_idempotent(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Guest/Pricing.vue'));

        $this->assertStringContainsString("axios.post(route('subscription-checkout.store'", $source);
        $this->assertStringContainsString("'Idempotency-Key': checkoutModal.value.requestId", $source);
        $this->assertStringNotContainsString('/checkout/subscriptions/${plan.id}/start', $source);
        $this->assertStringContainsString('checkoutClubs', $source);

        foreach (['de:', 'en:', 'fr:', 'ar:'] as $locale) {
            $this->assertStringContainsString($locale, $source);
        }
    }

    private function paidPlan(array $attributes = []): SubscriptionPlan
    {
        return SubscriptionPlan::query()->create(array_merge([
            'slug' => 'guest-pricing-athlete-pro',
            'target_actor' => 'sportler',
            'name' => 'Guest Pricing Pro',
            'description' => 'Secure guest pricing checkout.',
            'monthly_price_cents' => 1299,
            'yearly_price_cents' => 12990,
            'currency' => 'EUR',
            'sort_order' => 999,
            'is_public' => true,
            'is_active' => true,
        ], $attributes));
    }

    private function checkoutPayload(array $attributes = []): array
    {
        return array_merge([
            'provider' => 'stripe',
            'billing_interval' => 'monthly',
            'coupon_code' => null,
            'accepted_terms' => true,
            'club_id' => null,
        ], $attributes);
    }

    private function checkoutHeaders(string $idempotencyKey): array
    {
        return [
            'Referer' => route('guest.pricing'),
            'X-Checkout-Mode' => 'json',
            'Idempotency-Key' => $idempotencyKey,
        ];
    }

    private function planIndex(SubscriptionPlan $plan): int
    {
        return SubscriptionPlan::query()
            ->where('is_public', true)
            ->where('is_active', true)
            ->where('target_actor', 'verein')
            ->orderBy('sort_order')
            ->pluck('id')
            ->search($plan->id);
    }
}
