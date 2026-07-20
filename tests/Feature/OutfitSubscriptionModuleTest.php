<?php

namespace Tests\Feature;

use App\Models\OutfitSubscription;
use App\Models\OutfitSubscriptionPlan;
use App\Models\CommerceAuditLog;
use App\Models\Invoice;
use App\Models\OutfitDelivery;
use App\Models\Setting;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OutfitSubscriptionModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_outfit_dashboard_without_special_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('auth.outfit-subscriptions.index'))
            ->assertOk();
    }

    public function test_user_can_update_style_profile_and_subscribe(): void
    {
        $user = User::factory()->create();

        $plan = OutfitSubscriptionPlan::query()->create([
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
            'is_public' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->put(route('auth.outfit-subscriptions.profile.update'), [
                'sport_focus' => 'Laufen',
                'sizes' => ['M'],
                'fit_preference' => 'regular',
                'colors' => ['Schwarz'],
                'excluded_colors' => ['Gelb'],
                'brand_style' => 'minimal',
                'notes' => 'Atmungsaktive Materialien.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('outfit_style_profiles', [
            'user_id' => $user->id,
            'sport_focus' => 'Laufen',
        ]);

        Setting::setValue('billing_iban', 'DE89370400440532013000');

        $this->actingAs($user)
            ->post(route('auth.outfit-subscriptions.store', $plan), [
                'accepted_terms' => true,
                'accepted_contract' => true,
                'payment_provider' => 'bank_transfer',
                'shipping_name' => 'Amina Beispiel',
                'shipping_country' => 'DE',
                'shipping_street' => 'Sportallee',
                'shipping_house_number' => '7',
                'shipping_postal_code' => '10115',
                'shipping_city' => 'Berlin',
                'shipping_state' => 'Berlin',
                'shipping_note' => 'Bitte bei der Rezeption abgeben.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('outfit_subscriptions', [
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'monthly_price_cents' => 2490,
            'status' => 'pending_payment',
            'payment_provider' => 'bank_transfer',
            'payment_status' => 'pending',
            'shipping_name' => 'Amina Beispiel',
            'shipping_street' => 'Sportallee',
            'shipping_postal_code' => '10115',
            'shipping_city' => 'Berlin',
        ]);
    }

    public function test_admin_can_manage_outfit_plans_with_sponsor(): void
    {
        Permission::firstOrCreate(['name' => 'outfit-subscriptions.manage']);
        $admin = User::factory()->create();
        $admin->givePermissionTo('outfit-subscriptions.manage');
        $sponsor = Sponsor::query()->create(['name' => 'Airmius Sponsor']);

        $this->actingAs($admin)
            ->post(route('admin.outfit-subscription-plans.store'), [
                'sponsor_id' => $sponsor->id,
                'name' => 'Sponsored Fitness Box',
                'description' => 'Verguenstigte Fitness-Outfits mit Sponsor-Branding.',
                'monthly_price_cents' => 3990,
                'sponsor_discount_cents' => 1000,
                'currency' => 'EUR',
                'target_gender' => 'unisex',
                'sizes' => ['S', 'M', 'L'],
                'sports' => ['Fitness'],
                'items_per_box' => 4,
                'branding_type' => 'sponsor_logo',
                'sort_order' => 1,
                'is_public' => true,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('outfit_subscription_plans', [
            'sponsor_id' => $sponsor->id,
            'name' => 'Sponsored Fitness Box',
            'sponsor_discount_cents' => 1000,
            'branding_type' => 'sponsor_logo',
        ]);
    }

    public function test_plan_with_subscription_is_deactivated_instead_of_deleted(): void
    {
        Permission::firstOrCreate(['name' => 'outfit-subscriptions.manage']);
        $admin = User::factory()->create();
        $admin->givePermissionTo('outfit-subscriptions.manage');
        $user = User::factory()->create();
        $plan = OutfitSubscriptionPlan::query()->create([
            'name' => 'Team Box',
            'slug' => 'team-box',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
            'items_per_box' => 2,
            'branding_type' => 'club_logo',
            'is_public' => true,
            'is_active' => true,
        ]);
        OutfitSubscription::query()->create([
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'active',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.outfit-subscription-plans.destroy', $plan))
            ->assertRedirect();

        $this->assertDatabaseHas('outfit_subscription_plans', [
            'id' => $plan->id,
            'is_active' => false,
            'is_public' => false,
        ]);
    }

    public function test_scheduler_command_plans_due_outfit_deliveries(): void
    {
        $user = User::factory()->create();
        $plan = OutfitSubscriptionPlan::query()->create([
            'name' => 'Monthly Box',
            'slug' => 'monthly-box',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
            'items_per_box' => 2,
            'branding_type' => 'none',
            'is_public' => true,
            'is_active' => true,
        ]);
        OutfitSubscription::query()->create([
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_status' => 'paid',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
            'next_delivery_at' => now()->subDay(),
        ]);

        $this->artisan('airmius:prepare-outfit-deliveries')
            ->assertSuccessful();

        $this->assertDatabaseHas('outfit_deliveries', [
            'status' => 'planned',
            'notes' => 'Automatisch geplante Monatslieferung.',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'outfit.delivery.planned',
        ]);
    }

    public function test_scheduler_skips_unpaid_active_outfit_subscriptions(): void
    {
        $user = User::factory()->create();
        $plan = OutfitSubscriptionPlan::query()->create([
            'name' => 'Unpaid Monthly Box',
            'slug' => 'unpaid-monthly-box',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
            'items_per_box' => 2,
            'branding_type' => 'none',
            'is_public' => true,
            'is_active' => true,
        ]);
        OutfitSubscription::query()->create([
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_status' => 'pending',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
            'next_delivery_at' => now()->subDay(),
        ]);

        $this->artisan('airmius:prepare-outfit-deliveries')
            ->assertSuccessful();

        $this->assertDatabaseCount('outfit_deliveries', 0);
    }

    public function test_user_cannot_pause_outfit_subscription_before_pause_window(): void
    {
        $user = User::factory()->create();
        $plan = OutfitSubscriptionPlan::query()->create([
            'name' => 'Contract Pause Box',
            'slug' => 'contract-pause-box',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
            'items_per_box' => 2,
            'branding_type' => 'none',
            'pause_allowed_after_months' => 3,
            'is_public' => true,
            'is_active' => true,
        ]);
        $subscription = OutfitSubscription::query()->create([
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_status' => 'paid',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
            'accepted_contract_at' => now()->subMonth(),
            'next_delivery_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->post(route('auth.outfit-subscriptions.pause', $subscription))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('outfit_subscriptions', [
            'id' => $subscription->id,
            'status' => 'active',
        ]);
    }

    public function test_user_cannot_cancel_outfit_subscription_before_minimum_term(): void
    {
        $user = User::factory()->create();
        $plan = OutfitSubscriptionPlan::query()->create([
            'name' => 'Contract Cancel Box',
            'slug' => 'contract-cancel-box',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
            'items_per_box' => 2,
            'branding_type' => 'none',
            'minimum_term_months' => 3,
            'is_public' => true,
            'is_active' => true,
        ]);
        $subscription = OutfitSubscription::query()->create([
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_status' => 'paid',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
            'accepted_contract_at' => now()->subMonth(),
            'next_delivery_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->post(route('auth.outfit-subscriptions.cancel', $subscription))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('outfit_subscriptions', [
            'id' => $subscription->id,
            'status' => 'active',
            'cancelled_at' => null,
        ]);
    }

    public function test_user_cancellation_uses_notice_period_as_effective_end_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-16 10:00:00'));

        try {
            $user = User::factory()->create();
            $plan = OutfitSubscriptionPlan::query()->create([
                'name' => 'Notice Period Box',
                'slug' => 'notice-period-box',
                'monthly_price_cents' => 1990,
                'currency' => 'EUR',
                'items_per_box' => 2,
                'branding_type' => 'none',
                'minimum_term_months' => 3,
                'cancellation_notice_days' => 14,
                'is_public' => true,
                'is_active' => true,
            ]);
            $subscription = OutfitSubscription::query()->create([
                'user_id' => $user->id,
                'outfit_subscription_plan_id' => $plan->id,
                'status' => 'active',
                'payment_status' => 'paid',
                'monthly_price_cents' => 1990,
                'currency' => 'EUR',
                'accepted_contract_at' => now()->subMonths(4),
                'current_period_ends_at' => now()->addDays(5),
                'next_delivery_at' => now()->addDays(7),
            ]);

            $this->actingAs($user)
                ->post(route('auth.outfit-subscriptions.cancel', $subscription))
                ->assertRedirect()
                ->assertSessionHas('success');

            $subscription->refresh();

            $this->assertSame('cancels_at_period_end', $subscription->status);
            $this->assertTrue($subscription->current_period_ends_at->isSameDay(now()->addDays(14)));
            $this->assertTrue($subscription->next_delivery_at->isSameDay(now()->addDays(7)));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_lifecycle_command_finalizes_due_outfit_cancellations(): void
    {
        $user = User::factory()->create();
        $plan = OutfitSubscriptionPlan::query()->create([
            'name' => 'Lifecycle Box',
            'slug' => 'lifecycle-box',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
            'items_per_box' => 2,
            'branding_type' => 'none',
            'is_public' => true,
            'is_active' => true,
        ]);
        $subscription = OutfitSubscription::query()->create([
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'cancels_at_period_end',
            'payment_status' => 'paid',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
            'current_period_ends_at' => now()->subDay(),
            'next_delivery_at' => now()->addDay(),
        ]);

        $this->artisan('airmius:process-outfit-subscription-lifecycle')
            ->assertSuccessful();

        $this->assertDatabaseHas('outfit_subscriptions', [
            'id' => $subscription->id,
            'status' => 'cancelled',
            'next_delivery_at' => null,
        ]);
    }

    public function test_paypal_webhook_activates_pending_outfit_subscription(): void
    {
        $this->fakeSuccessfulOutfitPayPalWebhookVerification();

        $user = User::factory()->create();
        $plan = OutfitSubscriptionPlan::query()->create([
            'name' => 'PayPal Box',
            'slug' => 'paypal-box',
            'monthly_price_cents' => 2990,
            'currency' => 'EUR',
            'items_per_box' => 3,
            'branding_type' => 'none',
            'is_public' => true,
            'is_active' => true,
        ]);
        $subscription = OutfitSubscription::query()->create([
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'pending_payment',
            'payment_provider' => 'paypal',
            'payment_status' => 'pending',
            'payment_reference' => 'AIR-OUT-2026-000123',
            'provider_subscription_id' => 'I-PAYPAL123',
            'monthly_price_cents' => 2990,
            'currency' => 'EUR',
        ]);

        $this
            ->withHeaders($this->paypalWebhookHeaders())
            ->post(route('webhooks.outfit-subscriptions.paypal'), [
                'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
                'resource' => [
                    'id' => 'I-PAYPAL123',
                    'status' => 'ACTIVE',
                ],
            ])->assertOk();

        $this->assertDatabaseHas('outfit_subscriptions', [
            'id' => $subscription->id,
            'status' => 'active',
            'payment_status' => 'paid',
        ]);
        $this->assertDatabaseHas('outfit_deliveries', [
            'outfit_subscription_id' => $subscription->id,
            'status' => 'planned',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'outfit.subscription.paid',
        ]);
        $this->assertDatabaseHas('invoices', [
            'user_id' => $user->id,
            'number' => $subscription->fresh()->payment_reference,
            'source' => 'outfit_subscription',
            'status' => 'paid',
        ]);
    }

    public function test_live_paypal_webhook_without_configured_webhook_id_is_rejected(): void
    {
        config([
            'services.paypal.mode' => 'live',
            'services.paypal.outfit_webhook_id' => null,
            'services.paypal.webhook_id' => null,
        ]);

        $this->post(route('webhooks.outfit-subscriptions.paypal'), [
            'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'resource' => [
                'id' => 'I-PAYPAL123',
            ],
        ])->assertStatus(400);
    }

    public function test_paypal_suspended_webhook_pauses_outfit_subscription(): void
    {
        $this->fakeSuccessfulOutfitPayPalWebhookVerification();

        $user = User::factory()->create();
        $plan = OutfitSubscriptionPlan::query()->create([
            'name' => 'PayPal Suspended Box',
            'slug' => 'paypal-suspended-box',
            'monthly_price_cents' => 2990,
            'currency' => 'EUR',
            'items_per_box' => 3,
            'branding_type' => 'none',
            'is_public' => true,
            'is_active' => true,
        ]);
        $subscription = OutfitSubscription::query()->create([
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_provider' => 'paypal',
            'payment_status' => 'paid',
            'provider_subscription_id' => 'I-SUSPENDED123',
            'monthly_price_cents' => 2990,
            'currency' => 'EUR',
            'next_delivery_at' => now()->addMonth(),
        ]);

        $this
            ->withHeaders($this->paypalWebhookHeaders())
            ->post(route('webhooks.outfit-subscriptions.paypal'), [
                'event_type' => 'BILLING.SUBSCRIPTION.SUSPENDED',
                'resource' => [
                    'id' => 'I-SUSPENDED123',
                ],
            ])->assertOk();

        $this->assertDatabaseHas('outfit_subscriptions', [
            'id' => $subscription->id,
            'status' => 'paused',
            'payment_status' => 'paid',
            'cancelled_at' => null,
        ]);
    }

    public function test_paypal_cancelled_webhook_cancels_outfit_subscription(): void
    {
        $this->fakeSuccessfulOutfitPayPalWebhookVerification();

        $user = User::factory()->create();
        $plan = OutfitSubscriptionPlan::query()->create([
            'name' => 'PayPal Cancelled Box',
            'slug' => 'paypal-cancelled-box',
            'monthly_price_cents' => 2990,
            'currency' => 'EUR',
            'items_per_box' => 3,
            'branding_type' => 'none',
            'is_public' => true,
            'is_active' => true,
        ]);
        $subscription = OutfitSubscription::query()->create([
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_provider' => 'paypal',
            'payment_status' => 'paid',
            'provider_subscription_id' => 'I-CANCELLED123',
            'monthly_price_cents' => 2990,
            'currency' => 'EUR',
            'next_delivery_at' => now()->addMonth(),
        ]);

        $this
            ->withHeaders($this->paypalWebhookHeaders())
            ->post(route('webhooks.outfit-subscriptions.paypal'), [
                'event_type' => 'BILLING.SUBSCRIPTION.CANCELLED',
                'resource' => [
                    'id' => 'I-CANCELLED123',
                ],
            ])->assertOk();

        $subscription->refresh();

        $this->assertSame('cancelled', $subscription->status);
        $this->assertSame('paid', $subscription->payment_status);
        $this->assertNotNull($subscription->cancelled_at);
    }

    public function test_paypal_expired_webhook_cancels_pending_outfit_subscription_payment(): void
    {
        $this->fakeSuccessfulOutfitPayPalWebhookVerification();

        $user = User::factory()->create();
        $plan = OutfitSubscriptionPlan::query()->create([
            'name' => 'PayPal Expired Box',
            'slug' => 'paypal-expired-box',
            'monthly_price_cents' => 2990,
            'currency' => 'EUR',
            'items_per_box' => 3,
            'branding_type' => 'none',
            'is_public' => true,
            'is_active' => true,
        ]);
        $subscription = OutfitSubscription::query()->create([
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'pending_payment',
            'payment_provider' => 'paypal',
            'payment_status' => 'pending',
            'provider_subscription_id' => 'I-EXPIRED123',
            'monthly_price_cents' => 2990,
            'currency' => 'EUR',
        ]);

        $this
            ->withHeaders($this->paypalWebhookHeaders())
            ->post(route('webhooks.outfit-subscriptions.paypal'), [
                'event_type' => 'BILLING.SUBSCRIPTION.EXPIRED',
                'resource' => [
                    'id' => 'I-EXPIRED123',
                ],
            ])->assertOk();

        $this->assertDatabaseHas('outfit_subscriptions', [
            'id' => $subscription->id,
            'status' => 'cancelled',
            'payment_status' => 'cancelled',
        ]);
    }

    public function test_admin_marking_outfit_subscription_paid_writes_audit_log(): void
    {
        Permission::firstOrCreate(['name' => 'outfit-subscriptions.manage']);
        $admin = User::factory()->create();
        $admin->givePermissionTo('outfit-subscriptions.manage');
        $user = User::factory()->create();
        $plan = OutfitSubscriptionPlan::query()->create([
            'name' => 'Audit Paid Box',
            'slug' => 'audit-paid-box',
            'monthly_price_cents' => 2990,
            'currency' => 'EUR',
            'items_per_box' => 3,
            'branding_type' => 'none',
            'is_public' => true,
            'is_active' => true,
        ]);
        $subscription = OutfitSubscription::query()->create([
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'pending_payment',
            'payment_provider' => 'bank_transfer',
            'payment_status' => 'pending',
            'monthly_price_cents' => 2990,
            'currency' => 'EUR',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.outfit-subscriptions.mark-paid', $subscription), [
                'payment_note' => 'Bankzahlung manuell abgeglichen.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('commerce_audit_logs', [
            'user_id' => $admin->id,
            'auditable_type' => OutfitSubscription::class,
            'auditable_id' => $subscription->id,
            'action' => 'outfit.subscription.marked_paid',
            'note' => 'Bankzahlung manuell abgeglichen.',
        ]);

        $audit = CommerceAuditLog::query()->where('action', 'outfit.subscription.marked_paid')->firstOrFail();

        $this->assertSame('pending_payment', $audit->before['status']);
        $this->assertSame('active', $audit->after['status']);
        $this->assertSame('paid', $audit->after['payment_status']);

        $this->assertDatabaseHas('invoices', [
            'user_id' => $user->id,
            'source' => 'outfit_subscription',
            'status' => 'paid',
        ]);

        $invoice = Invoice::query()->where('source', 'outfit_subscription')->firstOrFail();

        $this->assertSame('Outfit-Abo: Audit Paid Box', $invoice->title);
        $this->assertSame('29.90', $invoice->amount);
    }

    public function test_admin_can_update_outfit_subscription_shipping_address_with_audit_log(): void
    {
        Permission::firstOrCreate(['name' => 'outfit-subscriptions.manage']);
        $admin = User::factory()->create();
        $admin->givePermissionTo('outfit-subscriptions.manage');
        $user = User::factory()->create();
        $plan = OutfitSubscriptionPlan::query()->create([
            'name' => 'Address Box',
            'slug' => 'address-box',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
            'items_per_box' => 2,
            'branding_type' => 'none',
            'is_public' => true,
            'is_active' => true,
        ]);
        $subscription = OutfitSubscription::query()->create([
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_status' => 'paid',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
            'shipping_name' => 'Alt Name',
            'shipping_country' => 'DE',
            'shipping_street' => 'Alte Strasse',
            'shipping_postal_code' => '10000',
            'shipping_city' => 'Altstadt',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.outfit-subscriptions.shipping-address.update', $subscription), [
                'shipping_name' => 'Neu Name',
                'shipping_country' => 'at',
                'shipping_street' => 'Neue Strasse',
                'shipping_house_number' => '42',
                'shipping_postal_code' => '1010',
                'shipping_city' => 'Wien',
                'shipping_state' => 'Wien',
                'shipping_note' => 'Seiteneingang nutzen.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('outfit_subscriptions', [
            'id' => $subscription->id,
            'shipping_name' => 'Neu Name',
            'shipping_country' => 'AT',
            'shipping_street' => 'Neue Strasse',
            'shipping_house_number' => '42',
            'shipping_postal_code' => '1010',
            'shipping_city' => 'Wien',
            'shipping_state' => 'Wien',
            'shipping_note' => 'Seiteneingang nutzen.',
        ]);

        $audit = CommerceAuditLog::query()
            ->where('action', 'outfit.subscription.shipping_address_updated')
            ->firstOrFail();

        $this->assertSame('Alt Name', $audit->before['shipping_address']['name']);
        $this->assertSame('Neu Name', $audit->after['shipping_address']['name']);
        $this->assertSame('AT', $audit->after['shipping_address']['country']);
    }

    public function test_user_can_request_outfit_delivery_issue(): void
    {
        Permission::firstOrCreate(['name' => 'outfit-subscriptions.manage']);
        $admin = User::factory()->create();
        $admin->givePermissionTo('outfit-subscriptions.manage');
        $user = User::factory()->create();
        $plan = OutfitSubscriptionPlan::query()->create([
            'name' => 'Issue Box',
            'slug' => 'issue-box',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
            'items_per_box' => 2,
            'branding_type' => 'none',
            'is_public' => true,
            'is_active' => true,
        ]);
        $subscription = OutfitSubscription::query()->create([
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_status' => 'paid',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
        ]);
        $delivery = OutfitDelivery::query()->create([
            'outfit_subscription_id' => $subscription->id,
            'status' => 'delivered',
            'delivery_month' => now()->startOfMonth(),
            'items' => ['Laufshirt L'],
        ]);

        $this->actingAs($user)
            ->post(route('auth.outfit-deliveries.issue.request', $delivery), [
                'issue_type' => 'exchange',
                'issue_description' => 'Das Shirt ist zu gross.',
                'issue_requested_resolution' => 'Bitte in M tauschen.',
                'issue_exchange_size' => 'M',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('outfit_deliveries', [
            'id' => $delivery->id,
            'issue_type' => 'exchange',
            'issue_status' => 'open',
            'issue_description' => 'Das Shirt ist zu gross.',
            'issue_requested_resolution' => 'Bitte in M tauschen.',
            'issue_exchange_size' => 'M',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'outfit.delivery.issue_requested',
        ]);
    }

    public function test_admin_can_update_outfit_delivery_issue_with_audit_log(): void
    {
        Permission::firstOrCreate(['name' => 'outfit-subscriptions.manage']);
        $admin = User::factory()->create();
        $admin->givePermissionTo('outfit-subscriptions.manage');
        $user = User::factory()->create();
        $plan = OutfitSubscriptionPlan::query()->create([
            'name' => 'Issue Admin Box',
            'slug' => 'issue-admin-box',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
            'items_per_box' => 2,
            'branding_type' => 'none',
            'is_public' => true,
            'is_active' => true,
        ]);
        $subscription = OutfitSubscription::query()->create([
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_status' => 'paid',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
        ]);
        $delivery = OutfitDelivery::query()->create([
            'outfit_subscription_id' => $subscription->id,
            'status' => 'delivered',
            'delivery_month' => now()->startOfMonth(),
            'items' => ['Laufshirt L'],
            'issue_type' => 'exchange',
            'issue_status' => 'open',
            'issue_description' => 'Das Shirt ist zu gross.',
            'issue_requested_at' => now(),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.outfit-deliveries.issue.update', $delivery), [
                'issue_status' => 'replacement_preparing',
                'issue_admin_note' => 'Ersatz in M wird vorbereitet.',
                'return_tracking_number' => 'RET-123',
                'return_tracking_url' => 'https://tracking.example.test/RET-123',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('outfit_deliveries', [
            'id' => $delivery->id,
            'issue_status' => 'replacement_preparing',
            'issue_admin_note' => 'Ersatz in M wird vorbereitet.',
            'return_tracking_number' => 'RET-123',
            'return_tracking_url' => 'https://tracking.example.test/RET-123',
        ]);
        $this->assertDatabaseHas('commerce_audit_logs', [
            'user_id' => $admin->id,
            'auditable_type' => OutfitDelivery::class,
            'auditable_id' => $delivery->id,
            'action' => 'outfit.delivery.issue_updated',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'outfit.delivery.issue_status_updated',
        ]);

        $audit = CommerceAuditLog::query()->where('action', 'outfit.delivery.issue_updated')->firstOrFail();

        $this->assertSame('open', $audit->before['issue']['status']);
        $this->assertSame('replacement_preparing', $audit->after['issue']['status']);
    }

    public function test_admin_delivery_status_change_writes_audit_log(): void
    {
        Permission::firstOrCreate(['name' => 'outfit-subscriptions.manage']);
        $admin = User::factory()->create();
        $admin->givePermissionTo('outfit-subscriptions.manage');
        $user = User::factory()->create();
        $plan = OutfitSubscriptionPlan::query()->create([
            'name' => 'Audit Delivery Box',
            'slug' => 'audit-delivery-box',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
            'items_per_box' => 2,
            'branding_type' => 'none',
            'is_public' => true,
            'is_active' => true,
        ]);
        $subscription = OutfitSubscription::query()->create([
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_status' => 'paid',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
        ]);
        $delivery = OutfitDelivery::query()->create([
            'outfit_subscription_id' => $subscription->id,
            'status' => 'planned',
            'delivery_month' => now()->startOfMonth(),
            'items' => [],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.outfit-deliveries.shipped', $delivery), [
                'tracking_number' => 'TRACK-123',
                'tracking_url' => 'https://tracking.example.test/TRACK-123',
                'carrier' => 'DHL',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('commerce_audit_logs', [
            'user_id' => $admin->id,
            'auditable_type' => OutfitDelivery::class,
            'auditable_id' => $delivery->id,
            'action' => 'outfit.delivery.marked_shipped',
        ]);

        $audit = CommerceAuditLog::query()->where('action', 'outfit.delivery.marked_shipped')->firstOrFail();

        $this->assertSame('planned', $audit->before['status']);
        $this->assertSame('shipped', $audit->after['status']);
        $this->assertSame('TRACK-123', $audit->after['tracking_number']);
        $this->assertSame('https://tracking.example.test/TRACK-123', $audit->after['tracking_url']);
    }

    private function fakeSuccessfulOutfitPayPalWebhookVerification(): void
    {
        config([
            'services.paypal.mode' => 'sandbox',
            'services.paypal.client_id' => 'paypal-client-id',
            'services.paypal.client_secret' => 'paypal-client-secret',
            'services.paypal.outfit_webhook_id' => 'outfit-webhook-id',
            'services.paypal.webhook_id' => null,
        ]);

        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'SUCCESS',
            ]),
        ]);
    }

    private function paypalWebhookHeaders(): array
    {
        return [
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
            'PAYPAL-CERT-URL' => 'https://api-m.sandbox.paypal.com/certs/test.pem',
            'PAYPAL-TRANSMISSION-ID' => 'transmission-id',
            'PAYPAL-TRANSMISSION-SIG' => 'signature',
            'PAYPAL-TRANSMISSION-TIME' => now()->toIso8601String(),
        ];
    }
}
