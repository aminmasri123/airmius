<?php

namespace Tests\Feature;

use App\Models\OutfitDelivery;
use App\Models\OutfitSubscription;
use App\Models\OutfitSubscriptionPlan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileOutfitSubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_user_can_load_plans_save_style_and_subscribe_by_bank_transfer(): void
    {
        Setting::setValue('billing_iban', 'DE89370400440532013000');
        Setting::setValue('billing_account_holder', 'Airmius GmbH');
        $user = User::factory()->create();
        $plan = $this->plan();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/outfit-subscriptions')
            ->assertOk()
            ->assertJsonPath('data.plans.0.id', $plan->id)
            ->assertJsonPath('data.contractRules.minimum_term_months', 3)
            ->assertJsonCount(0, 'data.subscriptions');

        $this->putJson('/api/v1/outfit-subscriptions/style-profile', [
            'sport_focus' => 'Laufen',
            'sizes' => ['M'],
            'fit_preference' => 'regular',
            'colors' => ['Schwarz'],
            'excluded_colors' => ['Gelb'],
            'brand_style' => 'minimal',
        ])
            ->assertOk()
            ->assertJsonPath('data.sport_focus', 'Laufen')
            ->assertJsonPath('data.sizes.0', 'M');

        $this->postJson("/api/v1/outfit-subscriptions/plans/{$plan->id}", [
            'accepted_terms' => true,
            'accepted_contract' => true,
            'payment_provider' => 'bank_transfer',
            'shipping_name' => 'Amina Beispiel',
            'shipping_country' => 'DE',
            'shipping_street' => 'Sportallee',
            'shipping_house_number' => '7',
            'shipping_postal_code' => '10115',
            'shipping_city' => 'Berlin',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.monthly_price_cents', 2490)
            ->assertJsonPath('payment_action.type', 'bank_transfer')
            ->assertJsonPath('payment_action.bank_transfer.iban', 'DE89370400440532013000');
    }

    public function test_mobile_subscription_lifecycle_and_delivery_issue_are_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $plan = $this->plan([
            'minimum_term_months' => 0,
            'pause_allowed_after_months' => 0,
            'cancellation_notice_days' => 0,
        ]);
        $subscription = $this->subscription($owner, $plan);
        $delivery = OutfitDelivery::query()->create([
            'outfit_subscription_id' => $subscription->id,
            'status' => 'delivered',
            'delivery_month' => now()->startOfMonth(),
            'items' => [['name' => 'Trainingsshirt', 'size' => 'M']],
            'delivered_at' => now(),
        ]);

        Sanctum::actingAs($other);
        $originalSubscription = $subscription->fresh()->getAttributes();
        $this->postJson("/api/v1/outfit-subscriptions/{$subscription->id}/pause")
            ->assertForbidden();
        $this->postJson("/api/v1/outfit-subscriptions/{$subscription->id}/resume")
            ->assertForbidden();
        $this->postJson("/api/v1/outfit-subscriptions/{$subscription->id}/cancel")
            ->assertForbidden();
        $this->assertSame($originalSubscription, $subscription->fresh()->getAttributes());
        $this->postJson("/api/v1/outfit-deliveries/{$delivery->id}/issue", [
            'issue_type' => 'wrong_item',
            'issue_description' => 'Nicht meine Lieferung.',
        ])->assertForbidden();

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/outfit-subscriptions/{$subscription->id}/pause")
            ->assertOk()
            ->assertJsonPath('data.status', 'paused');
        $this->postJson("/api/v1/outfit-subscriptions/{$subscription->id}/resume")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
        $this->postJson("/api/v1/outfit-deliveries/{$delivery->id}/issue", [
            'issue_type' => 'wrong_item',
            'issue_description' => 'Die gelieferte Größe stimmt nicht.',
            'issue_requested_resolution' => 'Bitte austauschen.',
            'issue_exchange_size' => 'L',
        ])
            ->assertCreated()
            ->assertJsonPath('data.issue_status', 'open')
            ->assertJsonPath('data.issue_exchange_size', 'L');
        $this->postJson("/api/v1/outfit-subscriptions/{$subscription->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_mobile_contract_rules_and_duplicate_subscription_are_enforced(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan();
        $subscription = $this->subscription($user, $plan, [
            'accepted_contract_at' => now()->subMonth(),
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/outfit-subscriptions/{$subscription->id}/pause")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subscription');
        $this->postJson("/api/v1/outfit-subscriptions/{$subscription->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subscription');
        $this->postJson("/api/v1/outfit-subscriptions/plans/{$plan->id}", [
            'accepted_terms' => true,
            'accepted_contract' => true,
            'payment_provider' => 'bank_transfer',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan');
    }

    private function plan(array $overrides = []): OutfitSubscriptionPlan
    {
        return OutfitSubscriptionPlan::query()->create(array_merge([
            'name' => 'Runner Box',
            'slug' => 'runner-box',
            'description' => 'Monatliche Lauf-Outfits.',
            'monthly_price_cents' => 2990,
            'sponsor_discount_cents' => 500,
            'currency' => 'EUR',
            'sizes' => ['S', 'M', 'L'],
            'sports' => ['Laufen'],
            'items_per_box' => 3,
            'branding_type' => 'none',
            'minimum_term_months' => 3,
            'pause_allowed_after_months' => 3,
            'cancellation_notice_days' => 14,
            'is_public' => true,
            'is_active' => true,
        ], $overrides));
    }

    private function subscription(
        User $user,
        OutfitSubscriptionPlan $plan,
        array $overrides = [],
    ): OutfitSubscription {
        return OutfitSubscription::query()->create(array_merge([
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_provider' => 'bank_transfer',
            'payment_status' => 'paid',
            'monthly_price_cents' => $plan->effectiveMonthlyPriceCents(),
            'sponsor_discount_cents' => $plan->sponsor_discount_cents,
            'currency' => 'EUR',
            'accepted_contract_at' => now()->subYear(),
            'current_period_ends_at' => now(),
            'next_delivery_at' => now()->addMonth(),
        ], $overrides));
    }
}
