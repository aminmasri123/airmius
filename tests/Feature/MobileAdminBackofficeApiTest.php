<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Invoice;
use App\Models\OperatingContract;
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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileAdminBackofficeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_receives_real_backoffice_data_without_provider_secrets(): void
    {
        $admin = $this->admin([
            'subscriptions.manage',
            'billing.manage',
            'finance.view',
            'finance.edit',
        ]);
        Sanctum::actingAs($admin, ['*', AdminTwoFactor::STEP_UP_TOKEN_ABILITY]);

        $customer = User::factory()->create(['name' => 'Mobile Kunde']);
        $club = Club::factory()->create([
            'name' => 'Mobile Verein',
            'owner_id' => $customer->id,
        ]);
        $plan = $this->plan();
        UserSubscription::query()->create([
            'user_id' => $customer->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_provider' => 'manual',
            'billing_interval' => 'monthly',
        ]);
        $club->currentSubscription()->update([
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_provider' => 'manual',
            'billing_interval' => 'monthly',
        ]);
        PaymentCheckout::query()->create([
            'user_id' => $customer->id,
            'subscription_plan_id' => $plan->id,
            'provider' => 'bank_transfer',
            'billing_interval' => 'monthly',
            'amount_cents' => 1299,
            'currency' => 'EUR',
            'status' => 'awaiting_transfer',
            'provider_customer_id' => 'must-not-leak',
            'provider_subscription_id' => 'must-not-leak',
            'payment_reference' => 'AIR-MOBILE-1',
            'due_at' => now()->addDays(7),
        ]);
        SubscriptionInvoice::query()->create([
            'user_id' => $customer->id,
            'subscription_plan_id' => $plan->id,
            'number' => 'SUB-MOBILE-1',
            'title' => 'Mobile Pro',
            'amount_cents' => 1299,
            'currency' => 'EUR',
            'status' => 'open',
            'payment_method' => 'bank_transfer',
            'issued_at' => now(),
            'due_at' => now()->addDays(7),
        ]);
        $invoice = Invoice::query()->create([
            'user_id' => $customer->id,
            'number' => 'MAN-MOBILE-1',
            'title' => 'Mobile Rechnung',
            'amount' => 25,
            'status' => 'open',
            'source' => 'custom',
            'due_date' => now()->addDays(7),
            'issued_at' => now(),
        ]);
        Payment::query()->create([
            'club_id' => $club->id,
            'user_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 10,
            'status' => 'paid',
            'method' => 'bank_transfer',
            'reference' => 'PAY-MOBILE-1',
            'paid_at' => now(),
        ]);
        OperatingContract::query()->create([
            'owner_user_id' => $admin->id,
            'name' => 'Mobile Hosting',
            'vendor' => 'Hoster',
            'category' => 'hosting',
            'status' => 'active',
            'amount' => 120,
            'currency' => 'EUR',
            'billing_interval' => 'yearly',
            'payment_method' => 'invoice',
        ]);

        $response = $this->getJson('/api/v1/admin/backoffice')
            ->assertOk()
            ->assertJsonPath('data.abilities.subscriptions_manage', true)
            ->assertJsonPath('data.abilities.billing_manage', true)
            ->assertJsonPath('data.abilities.finance_edit', true)
            ->assertJsonPath('data.summary.active_user_subscriptions', 1)
            ->assertJsonPath('data.summary.active_club_subscriptions', 1)
            ->assertJsonPath('data.summary.pending_transfers', 1)
            ->assertJsonPath('data.summary.open_subscription_invoices', 1)
            ->assertJsonPath('data.summary.payments', 1)
            ->assertJsonPath('data.summary.open_invoices', 2)
            ->assertJsonPath('data.summary.contracts', 1)
            ->assertJsonFragment(['payment_reference' => 'AIR-MOBILE-1'])
            ->assertJsonFragment(['number' => 'MAN-MOBILE-1'])
            ->assertJsonFragment(['name' => 'Mobile Hosting']);

        $payload = $response->getContent();
        $this->assertStringNotContainsString('must-not-leak', $payload);
        $this->assertStringNotContainsString('two_factor_secret', $payload);
        $this->assertStringNotContainsString('password', $payload);
    }

    public function test_admin_can_manage_subscriptions_billing_and_contracts_through_mobile_contract(): void
    {
        Notification::fake();
        Mail::fake();
        $admin = $this->admin([
            'subscriptions.manage',
            'billing.manage',
            'finance.edit',
        ]);
        Sanctum::actingAs($admin, ['*', AdminTwoFactor::STEP_UP_TOKEN_ABILITY]);

        $customer = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $customer->id]);
        $plan = $this->plan();

        $this->patchJson(
            "/api/v1/admin/backoffice/plans/{$plan->id}",
            $this->planPayload(['monthly_price_cents' => 1499]),
        )
            ->assertOk()
            ->assertJsonPath('data.monthly_price_cents', 1499);

        $subscriptionId = $this->putJson(
            "/api/v1/admin/backoffice/users/{$customer->id}/subscription",
            [
                'user_subscription_id' => null,
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'trial_ends_at' => null,
                'current_period_ends_at' => now()->addMonth()->toDateString(),
                'payment_provider' => 'manual',
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->json('data.id');

        $this->putJson(
            "/api/v1/admin/backoffice/clubs/{$club->id}/subscription",
            [
                'user_subscription_id' => null,
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'trial_ends_at' => null,
                'current_period_ends_at' => now()->addMonth()->toDateString(),
                'payment_provider' => 'manual',
            ],
        )->assertOk()->assertJsonPath('data.status', 'active');

        $this->postJson(
            "/api/v1/admin/backoffice/user-subscriptions/{$subscriptionId}/cancel",
            ['mode' => 'period_end'],
        )->assertOk()->assertJsonPath('data.status', 'cancels_at_period_end');

        $this->postJson(
            "/api/v1/admin/backoffice/user-subscriptions/{$subscriptionId}/renew",
            ['months' => 2],
        )->assertOk()->assertJsonPath('data.status', 'active');

        $checkout = PaymentCheckout::query()->create([
            'user_id' => $customer->id,
            'subscription_plan_id' => $plan->id,
            'provider' => 'bank_transfer',
            'billing_interval' => 'monthly',
            'amount_cents' => 1499,
            'currency' => 'EUR',
            'status' => 'awaiting_transfer',
            'payment_reference' => 'AIR-MOBILE-PAID',
            'due_at' => now()->addDays(7),
        ]);
        $subscriptionInvoice = SubscriptionInvoice::query()->create([
            'payment_checkout_id' => $checkout->id,
            'user_id' => $customer->id,
            'subscription_plan_id' => $plan->id,
            'number' => 'SUB-MOBILE-PAID',
            'title' => 'Mobile Pro',
            'amount_cents' => 1499,
            'currency' => 'EUR',
            'status' => 'awaiting_transfer',
            'payment_method' => 'bank_transfer',
            'issued_at' => now(),
            'due_at' => now()->addDays(7),
        ]);

        $this->postJson(
            "/api/v1/admin/backoffice/transfers/{$checkout->id}/mark-paid",
        )->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertSame('paid', $subscriptionInvoice->fresh()->status);

        $this->postJson('/api/v1/admin/backoffice/invoices', [
            'source' => 'custom',
            'club_id' => null,
            'user_id' => $customer->id,
            'number' => 'MAN-MOBILE-NEW',
            'title' => 'Mobile Beratung',
            'description' => 'Über die App erstellt.',
            'amount' => 49.9,
            'status' => 'open',
            'due_date' => now()->addDays(10)->toDateString(),
            'issued_at' => now()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.saved', true);

        $invoice = Invoice::query()
            ->where('number', 'MAN-MOBILE-NEW')
            ->firstOrFail();

        $this->patchJson(
            "/api/v1/admin/backoffice/invoices/{$invoice->id}/status",
            ['status' => 'paid'],
        )->assertOk()->assertJsonPath('data.status', 'paid');

        $this->postJson('/api/v1/admin/backoffice/payments', [
            'club_id' => $club->id,
            'user_id' => $customer->id,
            'invoice_id' => null,
            'amount' => 12.5,
            'status' => 'paid',
            'method' => 'cash',
            'reference' => 'PAY-MOBILE-NEW',
            'paid_at' => now()->toJSON(),
            'notes' => 'Barzahlung',
        ])->assertCreated();
        $this->assertDatabaseHas('payments', ['reference' => 'PAY-MOBILE-NEW']);

        $this->postJson(
            '/api/v1/admin/backoffice/contracts',
            $this->contractPayload(),
        )->assertCreated();
        $contract = OperatingContract::query()
            ->where('name', 'Mobilfunk Backoffice')
            ->firstOrFail();

        $this->patchJson(
            "/api/v1/admin/backoffice/contracts/{$contract->id}",
            $this->contractPayload(['status' => 'paused', 'amount' => 39.9]),
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'paused')
            ->assertJsonPath('data.amount', 39.9);

        $this->deleteJson(
            "/api/v1/admin/backoffice/contracts/{$contract->id}",
        )->assertOk()->assertJsonPath('data.deleted', true);
    }

    public function test_backoffice_filters_data_and_mutations_by_permission_and_enforces_admin_two_factor(): void
    {
        $financeViewer = User::factory()->create();
        Permission::findOrCreate('finance.view', 'web');
        $financeViewer->givePermissionTo('finance.view');
        Sanctum::actingAs($financeViewer);

        OperatingContract::query()->create([
            'name' => 'Nur lesen',
            'category' => 'software',
            'status' => 'active',
            'amount' => 10,
            'currency' => 'EUR',
            'billing_interval' => 'monthly',
        ]);

        $this->getJson('/api/v1/admin/backoffice')
            ->assertOk()
            ->assertJsonPath('data.abilities.finance_view', true)
            ->assertJsonPath('data.abilities.finance_edit', false)
            ->assertJsonCount(0, 'data.plans')
            ->assertJsonCount(0, 'data.payments')
            ->assertJsonCount(0, 'data.invoices')
            ->assertJsonCount(1, 'data.contracts');

        $this->postJson(
            '/api/v1/admin/backoffice/contracts',
            $this->contractPayload(),
        )->assertForbidden();

        $unprivileged = User::factory()->create();
        Sanctum::actingAs($unprivileged);
        $this->getJson('/api/v1/admin/backoffice')->assertForbidden();

        $adminWithoutTwoFactor = $this->admin(
            ['subscriptions.manage'],
            twoFactor: false,
        );
        Sanctum::actingAs($adminWithoutTwoFactor);
        $this->getJson('/api/v1/admin/backoffice')
            ->assertForbidden()
            ->assertJsonPath('code', AdminTwoFactor::ERROR_CODE);
    }

    private function admin(array $permissions, bool $twoFactor = true): User
    {
        $permissionModels = collect($permissions)
            ->map(fn (string $name) => Permission::findOrCreate($name, 'web'));
        $role = Role::findOrCreate('super_admin', 'web');
        $role->syncPermissions($permissionModels);

        $admin = User::factory()->create($twoFactor ? [
            'two_factor_secret' => 'encrypted-test-secret',
            'two_factor_confirmed_at' => now(),
        ] : []);
        $admin->assignRole($role);

        return $admin;
    }

    private function plan(): SubscriptionPlan
    {
        return SubscriptionPlan::query()->create([
            'slug' => 'mobile-backoffice-pro',
            'target_actor' => 'sportler',
            'name' => 'Mobile Backoffice Pro',
            'description' => 'Testplan für den mobilen Backoffice-Vertrag.',
            'monthly_price_cents' => 1299,
            'yearly_price_cents' => 12990,
            'currency' => 'EUR',
            'storage_gb' => 5,
            'features' => ['Mobile'],
            'minimum_term_months' => 0,
            'cancellation_notice_days' => 0,
            'sort_order' => 900,
            'is_public' => true,
            'is_active' => true,
        ]);
    }

    private function planPayload(array $overrides = []): array
    {
        return array_merge([
            'target_actor' => 'sportler',
            'description' => 'Mobil aktualisiert.',
            'monthly_price_cents' => 1299,
            'yearly_price_cents' => 12990,
            'member_limit' => null,
            'team_limit' => null,
            'storage_gb' => 5,
            'minimum_term_months' => 0,
            'cancellation_notice_days' => 0,
            'cta_label' => 'Start',
            'badge' => 'Pro',
            'is_public' => true,
            'is_active' => true,
            'country_prices' => [],
        ], $overrides);
    }

    private function contractPayload(array $overrides = []): array
    {
        return array_merge([
            'owner_user_id' => null,
            'name' => 'Mobilfunk Backoffice',
            'vendor' => 'Mobil AG',
            'category' => 'mobile',
            'status' => 'active',
            'amount' => 29.9,
            'currency' => 'EUR',
            'billing_interval' => 'monthly',
            'payment_method' => 'direct_debit',
            'next_due_on' => now()->addMonth()->toDateString(),
            'starts_on' => now()->toDateString(),
            'ends_on' => null,
            'notice_until_on' => null,
            'cancellation_period_days' => null,
            'auto_renews' => true,
            'contract_number' => 'MOB-100',
            'account_reference' => 'K-100',
            'contact_email' => 'billing@example.test',
            'website' => 'https://example.test',
            'document_url' => null,
            'notes' => 'Mobiler Vertrag',
        ], $overrides);
    }
}
