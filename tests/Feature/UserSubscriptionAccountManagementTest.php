<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserSubscriptionAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_cancel_own_active_subscription_to_period_end(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();

        $subscription = UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->post(route('auth.user-subscriptions.cancel', $subscription->id));

        $response->assertRedirect();
        $this->assertEquals('cancels_at_period_end', $subscription->fresh()->status);
        $this->assertTrue((bool) $subscription->fresh()->cancel_at_period_end);
        $this->assertNotNull($subscription->fresh()->cancelled_at);
    }

    public function test_user_cannot_cancel_subscription_that_is_already_cancelled(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();

        $subscription = UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($user)->post(route('auth.user-subscriptions.cancel', $subscription->id));

        $response->assertSessionHas('error', 'Das Abo wurde bereits gekündigt.');
        $this->assertEquals('cancelled', $subscription->fresh()->status);
    }

    public function test_user_cannot_open_stripe_portal_when_provider_customer_is_missing(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();

        $subscription = UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_provider' => 'stripe',
            'provider_customer_id' => null,
            'provider_subscription_id' => null,
        ]);

        $response = $this->actingAs($user)->post(route('auth.user-subscriptions.provider-portal', $subscription->id));

        $response->assertRedirect();
        $response->assertSessionHasErrors();
    }

    public function test_user_cannot_open_stripe_portal_for_non_stripe_provider(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();

        $subscription = UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_provider' => 'paypal',
            'provider_customer_id' => 'cus_test_456',
            'provider_subscription_id' => 'sub_test_456',
        ]);

        $response = $this->actingAs($user)->post(route('auth.user-subscriptions.provider-portal', $subscription->id));

        $response->assertRedirect();
        $response->assertSessionHasErrors();
    }

    public function test_user_cannot_open_stripe_portal_for_cancelled_subscription(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();

        $subscription = UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'cancelled',
            'payment_provider' => 'stripe',
            'provider_customer_id' => 'cus_test_cancelled',
            'provider_subscription_id' => 'sub_test_cancelled',
        ]);

        $response = $this->actingAs($user)->post(route('auth.user-subscriptions.provider-portal', $subscription->id));

        $response->assertRedirect();
        $response->assertSessionHasErrors();
    }

    public function test_user_can_open_stripe_portal_for_eligible_subscription(): void
    {
        config()->set('services.stripe.secret', 'sk_test_portal');
        Http::fake([
            'https://api.stripe.com/v1/billing_portal/sessions' => Http::response([
                'url' => 'https://billing.stripe.test/session_123',
            ]),
        ]);

        $user = User::factory()->create();
        $plan = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();

        $subscription = UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'past_due',
            'payment_provider' => 'stripe',
            'provider_customer_id' => 'cus_test_eligible',
            'provider_subscription_id' => 'sub_test_eligible',
        ]);

        $response = $this->actingAs($user)->post(route('auth.user-subscriptions.provider-portal', $subscription->id));

        $response->assertRedirect('https://billing.stripe.test/session_123');
    }

    public function test_user_cannot_open_stripe_portal_when_stripe_secret_is_missing(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        $originalSecret = config('services.stripe.secret');

        config()->set('services.stripe.secret', null);

        $subscription = UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_provider' => 'stripe',
            'provider_customer_id' => 'cus_test_789',
            'provider_subscription_id' => 'sub_test_789',
        ]);

        $response = $this->actingAs($user)->post(route('auth.user-subscriptions.provider-portal', $subscription->id));

        $response->assertSessionHas('error', 'Provider-Portal ist für dieses Abo nicht verfügbar.');

        config()->set('services.stripe.secret', $originalSecret);
    }


    public function test_user_cannot_cancel_subscription_of_other_user(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $plan = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();

        $subscription = UserSubscription::query()->create([
            'user_id' => $owner->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($intruder)->post(route('auth.user-subscriptions.cancel', $subscription->id));

        $response->assertRedirect();
        $response->assertSessionHasErrors();
        $this->assertEquals('active', $subscription->fresh()->status);
    }

    public function test_admin_assigning_free_sportler_plan_retires_previous_active_sportler_plan(): void
    {
        $admin = User::factory()->create();
        Permission::findOrCreate('subscriptions.manage', 'web');
        $admin->givePermissionTo('subscriptions.manage');

        $user = User::factory()->create();
        $free = SubscriptionPlan::query()->where('slug', 'sportler-free')->firstOrFail();
        $pro = SubscriptionPlan::query()->where('slug', 'sportler-pro')->firstOrFail();

        $proSubscription = UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $pro->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.subscription.update', $user), [
                'subscription_plan_id' => $free->id,
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertSame('cancelled', $proSubscription->fresh()->status);
        $this->assertNotNull($proSubscription->fresh()->cancelled_at);
        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'subscription_plan_id' => $free->id,
            'status' => 'active',
        ]);
        $this->assertSame(1, app(\App\Services\PlanFeatureService::class)->userStorageSummary($user)['limit_gb']);
    }
}
