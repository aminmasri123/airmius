<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\CommerceOrder;
use App\Models\Invoice;
use App\Models\OutfitSubscription;
use App\Models\OutfitSubscriptionPlan;
use App\Models\Payment;
use App\Models\PaymentCheckout;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\AdminTwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileBackofficeParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_search_and_pages_reach_beyond_old_limits_without_changing_totals(): void
    {
        $this->signIn('subscriptions.manage');
        $plan = $this->plan();
        $customer = User::factory()->create(['name' => 'Search Target', 'email' => 'target@example.test']);
        for ($i = 0; $i < 101; $i++) {
            UserSubscription::query()->create([
                'user_id' => $i === 0 ? $customer->id : User::factory()->create(['name' => 'Search Target'])->id, 'subscription_plan_id' => $plan->id,
                'status' => 'active', 'payment_provider' => 'manual',
            ]);
        }
        for ($i = 0; $i < 26; $i++) {
            $club = Club::factory()->create(['name' => "Parity Club {$i}", 'owner_id' => $customer->id]);
            $club->currentSubscription()->update(['subscription_plan_id' => $plan->id, 'status' => 'active']);
        }
        $first = $this->getJson('/api/v1/admin/backoffice')->assertOk()
            ->assertJsonCount(25, 'data.user_subscriptions')
            ->assertJsonCount(25, 'data.club_subscriptions')
            ->assertJsonPath('data.pagination.user_subscriptions.total', 101)
            ->json('data');
        $last = $this->getJson('/api/v1/admin/backoffice?user_subscriptions_page=5&club_subscriptions_page=2')->assertOk()
            ->assertJsonCount(1, 'data.user_subscriptions')->assertJsonCount(1, 'data.club_subscriptions')->json('data');
        $this->assertSame($first['summary'], $last['summary']);
        $this->assertEmpty(array_intersect(array_column($first['user_subscriptions'], 'id'), array_column($last['user_subscriptions'], 'id')));
        $this->getJson('/api/v1/admin/backoffice?user_subscriptions_q=Search%20Target&club_subscriptions_q=Parity%20Plan')->assertOk()
            ->assertJsonPath('data.pagination.user_subscriptions.total', 101)->assertJsonPath('data.pagination.club_subscriptions.total', 26);
        $empty = $this->getJson('/api/v1/admin/backoffice?user_subscriptions_q=absent&club_subscriptions_q=absent')->assertOk()
            ->assertJsonCount(0, 'data.user_subscriptions')->assertJsonCount(0, 'data.club_subscriptions')->json('data.summary');
        $this->assertSame($first['summary'], $empty);
    }

    public function test_transfers_subscription_invoices_and_payments_page_independently_with_global_sums(): void
    {
        $customer = $this->signIn('subscriptions.manage', 'billing.manage');
        $club = Club::factory()->create(['owner_id' => $customer->id]);
        for ($i = 0; $i < 101; $i++) {
            $this->subscriptionInvoice($customer, "SUB-{$i}", $i % 2 ? 'paid' : 'open');
            Payment::query()->create([
                'club_id' => $club->id, 'user_id' => $customer->id, 'amount' => 2.50,
                'status' => $i % 2 ? 'paid' : 'pending', 'method' => 'bank_transfer', 'paid_at' => now(),
            ]);
            if ($i < 51) {
                PaymentCheckout::query()->create([
                    'user_id' => $customer->id, 'subscription_plan_id' => $this->plan()->id, 'provider' => 'bank_transfer', 'status' => 'awaiting_transfer',
                    'billing_interval' => 'monthly', 'amount_cents' => 250, 'currency' => 'EUR',
                ]);
            }
        }
        $first = $this->getJson('/api/v1/admin/backoffice')->assertOk()
            ->assertJsonCount(25, 'data.pending_transfers')->assertJsonCount(50, 'data.subscription_invoices')
            ->assertJsonCount(25, 'data.payments')->assertJsonPath('data.summary.pending_transfers', 51)
            ->assertJsonPath('data.summary.payments', 101)->assertJsonPath('data.summary.payment_revenue_cents', 12500)
            ->assertJsonPath('data.summary.subscription_revenue_cents', 50000)->json('data');
        $last = $this->getJson('/api/v1/admin/backoffice?pending_transfers_page=3&subscription_invoices_page=3&payments_page=5&page=5')->assertOk()
            ->assertJsonCount(1, 'data.pending_transfers')->assertJsonCount(1, 'data.subscription_invoices')
            ->assertJsonCount(1, 'data.payments')->json('data');
        $this->assertSame($first['summary'], $last['summary']);
        foreach (['pending_transfers', 'subscription_invoices', 'payments'] as $list) {
            $this->assertEmpty(array_intersect(array_column($first[$list], 'id'), array_column($last[$list], 'id')));
        }
    }

    public function test_assignment_lookup_searches_all_users_clubs_and_plans_and_is_paginated(): void
    {
        $this->signIn('subscriptions.manage');
        User::factory()->count(251)->create(['name' => 'A Earlier']);
        $target = User::factory()->create(['name' => 'Z Later', 'email' => 'remote-target@example.test']);
        $plan = $this->plan();
        UserSubscription::query()->create(['user_id' => $target->id, 'subscription_plan_id' => $plan->id, 'status' => 'active']);
        $club = Club::factory()->create(['name' => 'Lookup Club', 'owner_id' => $target->id]);
        $club->currentSubscription()->update(['subscription_plan_id' => $plan->id]);
        $this->getJson('/api/v1/admin/backoffice?lookup=users&q=remote-target')->assertOk()->assertJsonPath('data.0.id', $target->id);
        $this->getJson('/api/v1/admin/backoffice?lookup=users&q=Parity%20Plan')->assertOk()->assertJsonPath('data.0.id', $target->id);
        $this->getJson('/api/v1/admin/backoffice?lookup=clubs&q=Parity%20Plan')->assertOk()->assertJsonPath('data.0.id', $club->id);
        $first = $this->getJson('/api/v1/admin/backoffice?lookup=users&q=A%20Earlier')->assertOk()->assertJsonCount(25, 'data')->json('data');
        $second = $this->getJson('/api/v1/admin/backoffice?lookup=users&q=A%20Earlier&page=2')->assertOk()->assertJsonCount(25, 'data')->json('data');
        $this->assertEmpty(array_intersect(array_column($first, 'id'), array_column($second, 'id')));
        $this->getJson('/api/v1/admin/backoffice?lookup=users&q=absent')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/admin/backoffice?lookup=invoices')->assertForbidden();
        $this->signIn('finance.view');
        $this->getJson('/api/v1/admin/backoffice?lookup=users')->assertForbidden();
        $this->getJson('/api/v1/admin/backoffice?lookup=clubs')->assertForbidden();
    }

    public function test_unified_billing_matches_web_sources_pagination_deduplication_and_actions(): void
    {
        $user = $this->signIn('billing.manage', 'subscriptions.manage');
        for ($i = 0; $i < 26; $i++) {
            $this->invoice($user, "MAN-{$i}");
        }
        $subscription = $this->subscriptionInvoice($user, 'SUB-UNIFIED');
        $commerce = $this->order($user);
        $plan = OutfitSubscriptionPlan::query()->create(['name' => 'Outfit Test', 'slug' => 'outfit-test', 'monthly_price_cents' => 1000]);
        $outfit = OutfitSubscription::query()->create([
            'user_id' => $user->id, 'outfit_subscription_plan_id' => $plan->id, 'payment_reference' => 'OUTFIT-UNIFIED',
            'monthly_price_cents' => 1000, 'currency' => 'EUR', 'payment_status' => 'paid',
        ]);
        $duplicate = OutfitSubscription::query()->create([
            'user_id' => $user->id, 'outfit_subscription_plan_id' => $plan->id, 'payment_reference' => 'OUTFIT-DUPLICATE',
            'monthly_price_cents' => 1000, 'currency' => 'EUR',
        ]);
        $this->invoice($user, 'OUTFIT-DUPLICATE')->update(['source' => 'outfit_subscription']);
        $ids = [];
        foreach ([1, 2] as $page) {
            $mobile = $this->getJson('/api/v1/admin/backoffice?page='.$page)->assertOk()->json('data');
            $web = $this->actingAs($user)->get('/admin/invoices?page='.$page, ['X-Inertia' => 'true', 'X-Inertia-Version' => \Inertia\Inertia::getVersion()])->assertOk()->json('props');
            $this->assertSame(array_column($web['invoices']['data'], 'id'), array_column($mobile['invoices'], 'id'));
            $this->assertSame($web['summary'], $mobile['billing_summary']);
            $this->assertSame($web['invoices']['total'], $mobile['pagination']['invoices']['total']);
            foreach ($mobile['invoices'] as $invoice) {
                $ids[] = $invoice['id'];
                $this->assertSame($invoice['type'] === 'membership', $invoice['can_update_status']);
                $this->assertSame(in_array($invoice['type'], ['subscription', 'commerce']), $invoice['can_download']);
            }
        }
        $this->assertCount(30, $ids);
        $this->assertContains('subscription-'.$subscription->id, $ids);
        $this->assertContains('commerce-'.$commerce->id, $ids);
        $this->assertContains('outfit-'.$outfit->id, $ids);
        $this->assertNotContains('outfit-'.$duplicate->id, $ids);
        $this->getJson('/api/v1/admin/backoffice?lookup=invoices&q=MAN-25')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_document_actions_return_real_pdf_bytes_without_urls_and_keep_web_permission_boundary(): void
    {
        $user = $this->signIn('subscriptions.manage');
        $subscription = $this->subscriptionInvoice($user, 'SUB-PDF');
        $commerce = $this->order($user);
        foreach (['subscription' => $subscription, 'commerce' => $commerce] as $type => $model) {
            $response = $this->getJson("/api/v1/admin/backoffice?document_type={$type}&document_id={$model->id}")->assertOk()
                ->assertJsonPath('data.content_type', 'application/pdf')->assertJsonMissingPath('data.url');
            $bytes = base64_decode($response->json('data.content_base64'), true);
            $this->assertStringStartsWith('%PDF-', $bytes);
            $this->assertGreaterThan(1000, strlen($bytes));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
        $this->getJson('/api/v1/admin/backoffice?document_type=subscription&document_id=999999')->assertNotFound();
        $commerce->update(['invoice_number' => null]);
        $this->getJson("/api/v1/admin/backoffice?document_type=commerce&document_id={$commerce->id}")->assertNotFound();
        $this->signIn('billing.manage');
        $this->getJson("/api/v1/admin/backoffice?document_type=subscription&document_id={$subscription->id}")->assertForbidden();
        $this->getJson("/api/v1/admin/backoffice?document_type=commerce&document_id={$commerce->id}")->assertForbidden();
        $this->getJson('/api/v1/admin/backoffice')->assertOk()->assertJsonPath('data.subscription_invoices.0.can_download', false);
    }

    public function test_validation_cross_scope_denial_and_two_factor_apply_to_every_mode(): void
    {
        $user = $this->signIn('subscriptions.manage');
        foreach (['page', 'user_subscriptions_page', 'club_subscriptions_page', 'payments_page', 'pending_transfers_page', 'subscription_invoices_page'] as $page) {
            $this->getJson('/api/v1/admin/backoffice?'.$page.'=0')->assertUnprocessable();
        }
        $this->getJson('/api/v1/admin/backoffice?lookup=unknown')->assertUnprocessable();
        $this->getJson('/api/v1/admin/backoffice?document_type=membership&document_id=1')->assertUnprocessable();
        $this->getJson('/api/v1/admin/backoffice?lookup=users&q='.str_repeat('x', 121))->assertUnprocessable();
        $this->getJson('/api/v1/admin/backoffice')->assertOk()->assertJsonCount(0, 'data.invoices')->assertJsonCount(0, 'data.payments');
        $user->assignRole(Role::findOrCreate('admin', 'web'));
        foreach (['', '?lookup=users', '?document_type=subscription&document_id=1'] as $query) {
            $this->getJson('/api/v1/admin/backoffice'.$query)->assertForbidden()->assertJsonPath('code', AdminTwoFactor::ERROR_CODE);
        }
        $user->forceFill(['two_factor_secret' => 'test-secret', 'two_factor_confirmed_at' => now()])->save();
        Sanctum::actingAs($user, []);
        $this->getJson('/api/v1/admin/backoffice?lookup=users')->assertForbidden()->assertJsonPath('code', AdminTwoFactor::STEP_UP_ERROR_CODE);
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/backoffice')->assertForbidden();
        $this->getJson('/api/v1/admin/backoffice?lookup=users')->assertForbidden();
        $this->getJson('/api/v1/admin/backoffice?document_type=subscription&document_id=1')->assertForbidden();
    }

    private function signIn(string ...$permissions): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        Sanctum::actingAs($user);

        return $user;
    }

    private function plan(): SubscriptionPlan
    {
        return SubscriptionPlan::query()->firstOrCreate(['slug' => 'backoffice-parity-plan'], ['name' => 'Parity Plan', 'target_actor' => 'sportler', 'monthly_price_cents' => 1000]);
    }

    private function subscriptionInvoice(User $user, string $number, string $status = 'open'): SubscriptionInvoice
    {
        return SubscriptionInvoice::query()->create(['user_id' => $user->id, 'subscription_plan_id' => $this->plan()->id, 'number' => $number, 'title' => 'Subscription', 'amount_cents' => 1000, 'currency' => 'EUR', 'status' => $status, 'issued_at' => now(), 'due_at' => now()->addDays(7)]);
    }

    private function invoice(User $user, string $number): Invoice
    {
        return Invoice::query()->create(['user_id' => $user->id, 'number' => $number, 'title' => 'Manual', 'amount' => 10, 'status' => 'open', 'source' => 'custom', 'issued_at' => now(), 'due_date' => now()->addDays(7)]);
    }

    private function order(User $user): CommerceOrder
    {
        return CommerceOrder::query()->create(['user_id' => $user->id, 'type' => 'marketplace_cart', 'provider' => 'manual', 'status' => 'completed', 'invoice_number' => 'COM-PDF', 'amount_cents' => 1000, 'net_cents' => 1000, 'tax_cents' => 0, 'shipping_cents' => 0, 'currency' => 'EUR']);
    }
}
