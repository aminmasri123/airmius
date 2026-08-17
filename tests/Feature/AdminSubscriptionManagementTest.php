<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\PaymentCheckout;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminSubscriptionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_admin_can_open_subscription_overview(): void
    {
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['subscriptions.manage']);

        $clubPlan = SubscriptionPlan::query()->where('slug', 'club')->firstOrFail();
        $owner = User::factory()->create();
        $club = Club::factory()->create([
            'name' => 'Airmius FC',
            'owner_id' => $owner->id,
        ]);
        $club->currentSubscription()->update([
            'subscription_plan_id' => $clubPlan->id,
            'status' => 'active',
        ]);

        $athletePlan = $this->createPlan([
            'slug' => 'test-athlete-pro',
            'target_actor' => 'sportler',
            'name' => 'Test Athlete Pro',
        ]);
        $athlete = User::factory()->create([
            'name' => 'Scout Player',
        ]);
        UserSubscription::query()->create([
            'user_id' => $athlete->id,
            'subscription_plan_id' => $athletePlan->id,
            'status' => 'active',
            'payment_provider' => 'bank_transfer',
        ]);
        PaymentCheckout::query()->create([
            'user_id' => $athlete->id,
            'subscription_plan_id' => $athletePlan->id,
            'provider' => 'bank_transfer',
            'billing_interval' => 'monthly',
            'amount_cents' => 1299,
            'currency' => 'EUR',
            'status' => 'awaiting_transfer',
            'payment_reference' => 'AIRMIUS-TEST-0001',
            'due_at' => now()->addDays(7),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.subscriptions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Admin/Subscriptions/Index')
                ->has('plans')
                ->has('clubs', 1)
                ->where('clubs.0.name', 'Airmius FC')
                ->has('userSubscriptions', 1)
                ->where('userSubscriptions.0.user.name', 'Scout Player')
                ->has('pendingBankTransfers', 1)
                ->where('pendingBankTransfers.0.payment_reference', 'AIRMIUS-TEST-0001')
            );
    }

    public function test_subscription_admin_can_manage_plans_assignments_and_bank_transfers(): void
    {
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['subscriptions.manage']);

        $clubPlan = SubscriptionPlan::query()->where('slug', 'club')->firstOrFail();
        $athletePlan = $this->createPlan([
            'slug' => 'test-sportler-plus',
            'target_actor' => 'sportler',
            'name' => 'Sportler Plus Test',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.subscription-plans.update', $athletePlan), $this->planPayload([
                'target_actor' => 'trainer',
                'monthly_price_cents' => 1299,
                'yearly_price_cents' => 12900,
                'country_prices' => [[
                    'country_code' => 'CH',
                    'currency' => 'CHF',
                    'monthly_price_cents' => 1599,
                    'yearly_price_cents' => 15900,
                    'is_active' => true,
                ]],
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('subscription_plans', [
            'id' => $athletePlan->id,
            'target_actor' => 'trainer',
            'monthly_price_cents' => 1299,
            'yearly_price_cents' => 12900,
        ]);
        $this->assertDatabaseHas('subscription_plan_prices', [
            'subscription_plan_id' => $athletePlan->id,
            'country_code' => 'CH',
            'currency' => 'CHF',
            'monthly_price_cents' => 1599,
        ]);

        $clubOwner = User::factory()->create();
        $club = Club::factory()->create([
            'name' => 'Plan Club',
            'owner_id' => $clubOwner->id,
        ]);
        $this->actingAs($admin)
            ->put(route('admin.clubs.subscription.update', $club), [
                'subscription_plan_id' => $clubPlan->id,
                'status' => 'active',
                'trial_ends_at' => '',
                'current_period_ends_at' => now()->addMonth()->toDateString(),
                'payment_provider' => 'manual',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('club_subscriptions', [
            'club_id' => $club->id,
            'subscription_plan_id' => $clubPlan->id,
            'status' => 'active',
            'payment_provider' => 'manual',
        ]);
        $clubNotification = $clubOwner->appNotifications()
            ->where('type', 'club.subscription.updated')
            ->firstOrFail();
        $this->assertSame('billing', $clubNotification->category);
        $this->assertSame('Abo aktualisiert', $clubNotification->data['title']);
        $this->assertStringContainsString('Plan Club', $clubNotification->data['body']);
        $this->assertSame('/club-cockpit', $clubNotification->data['url']);

        $user = User::factory()->create();
        $this->actingAs($admin)
            ->put(route('admin.users.subscription.update', $user), [
                'user_subscription_id' => '',
                'subscription_plan_id' => $athletePlan->id,
                'status' => 'active',
                'trial_ends_at' => '',
                'current_period_ends_at' => now()->addMonth()->toDateString(),
                'payment_provider' => 'manual',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'subscription_plan_id' => $athletePlan->id,
            'status' => 'active',
            'payment_provider' => 'manual',
        ]);
        $userNotification = $user->appNotifications()
            ->where('type', 'subscription.updated')
            ->firstOrFail();
        $this->assertSame('billing', $userNotification->category);
        $this->assertSame('Abo aktualisiert', $userNotification->data['title']);
        $this->assertStringContainsString('Sportler Plus Test', $userNotification->data['body']);
        $this->assertSame('/settings', $userNotification->data['url']);

        $checkoutBuyer = User::factory()->create();
        $checkout = PaymentCheckout::query()->create([
            'user_id' => $checkoutBuyer->id,
            'subscription_plan_id' => $athletePlan->id,
            'provider' => 'bank_transfer',
            'billing_interval' => 'monthly',
            'amount_cents' => 1299,
            'currency' => 'EUR',
            'status' => 'awaiting_transfer',
            'payment_reference' => 'AIRMIUS-TEST-0002',
            'due_at' => now()->addDays(7),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.subscription-checkouts.mark-paid', $checkout))
            ->assertRedirect();

        $this->assertDatabaseHas('payment_checkouts', [
            'id' => $checkout->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $checkoutBuyer->id,
            'subscription_plan_id' => $athletePlan->id,
            'status' => 'active',
            'payment_provider' => 'bank_transfer',
        ]);
    }

    public function test_subscription_admin_searches_large_user_and_club_lists_on_the_server(): void
    {
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['subscriptions.manage']);
        User::factory()->create(['name' => 'Lifecycle Search Athlete']);
        User::factory()->create(['name' => 'Unrelated Athlete']);
        $clubOwner = User::factory()->create();
        Club::factory()->create(['name' => 'Lifecycle Search Club', 'owner_id' => $clubOwner->id]);
        Club::factory()->create(['name' => 'Unrelated Club', 'owner_id' => $clubOwner->id]);

        $this->actingAs($admin)
            ->get(route('admin.subscriptions.index', [
                'user_query' => 'Lifecycle Search Athlete',
                'club_query' => 'Lifecycle Search Club',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.user_query', 'Lifecycle Search Athlete')
                ->where('filters.club_query', 'Lifecycle Search Club')
                ->has('users', 1)
                ->where('users.0.name', 'Lifecycle Search Athlete')
                ->has('clubs', 1)
                ->where('clubs.0.name', 'Lifecycle Search Club')
            );
    }

    private function createPlan(array $overrides = []): SubscriptionPlan
    {
        return SubscriptionPlan::query()->create(array_merge([
            'slug' => 'test-plan',
            'target_actor' => 'sportler',
            'name' => 'Test Plan',
            'description' => 'Test plan for subscription management.',
            'monthly_price_cents' => 990,
            'yearly_price_cents' => 9900,
            'currency' => 'EUR',
            'member_limit' => null,
            'team_limit' => null,
            'storage_gb' => 5,
            'features' => ['Feature A'],
            'minimum_term_months' => 0,
            'cancellation_notice_days' => 0,
            'cta_label' => 'Start',
            'badge' => 'Test',
            'sort_order' => 999,
            'is_public' => true,
            'is_active' => true,
        ], $overrides));
    }

    private function planPayload(array $overrides = []): array
    {
        return array_merge([
            'target_actor' => 'sportler',
            'description' => 'Updated plan copy.',
            'monthly_price_cents' => 990,
            'yearly_price_cents' => 9900,
            'member_limit' => '',
            'team_limit' => '',
            'storage_gb' => 5,
            'minimum_term_months' => 0,
            'cancellation_notice_days' => 0,
            'cta_label' => 'Start',
            'badge' => 'Test',
            'is_public' => true,
            'is_active' => true,
            'country_prices' => [],
        ], $overrides);
    }

    private function grantPermissions(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);
    }
}
