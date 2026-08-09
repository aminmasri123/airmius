<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceProduct;
use App\Models\Notification;
use App\Models\PayoutProfile;
use App\Models\User;
use App\Services\MarketplacePayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MarketplacePayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_request_is_atomic_uses_actual_id_for_reference_and_preserves_currency(): void
    {
        $seller = User::factory()->create(['language' => 'en']);
        $otherSeller = User::factory()->create();
        PayoutProfile::query()->create([
            'user_id' => $seller->id,
            'account_holder' => 'Safe Seller',
            'paypal_email' => 'seller@example.test',
            'status' => 'approved',
        ]);
        MarketplacePayout::query()->create([
            'user_id' => $otherSeller->id,
            'currency' => 'EUR',
            'gross_cents' => 100,
            'commission_cents' => 10,
            'amount_cents' => 90,
            'status' => 'paid',
        ]);
        $order = $this->eligibleOrder($seller, 12_500, 1_250, 'USD');

        Sanctum::actingAs($seller);

        $response = $this->postJson('/api/v1/commerce/seller/payouts', [
            'method' => 'paypal',
            'notes' => 'Weekly payout',
        ])
            ->assertCreated()
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.gross_cents', 12_500)
            ->assertJsonPath('data.amount_cents', 11_250)
            ->assertJsonPath('data.orders_count', 1)
            ->assertJsonPath('data.status', 'requested');

        $payoutId = (int) $response->json('data.id');
        $response->assertJsonPath(
            'data.reference',
            'AIR-PAY-'.now()->format('Y').'-'.str_pad((string) $payoutId, 6, '0', STR_PAD_LEFT),
        );

        $this->assertDatabaseHas('commerce_orders', [
            'id' => $order->id,
            'payout_id' => $payoutId,
            'payout_status' => 'requested',
        ]);
        $this->assertDatabaseHas('commerce_audit_logs', [
            'auditable_type' => MarketplacePayout::class,
            'auditable_id' => $payoutId,
            'action' => 'payout.requested',
        ]);

        $this->postJson('/api/v1/commerce/seller/payouts', [
            'method' => 'paypal',
        ])->assertUnprocessable();

        $this->assertSame(2, MarketplacePayout::query()->count());
    }

    public function test_mixed_currencies_are_rejected_without_assigning_any_order(): void
    {
        $seller = User::factory()->create();
        $first = $this->eligibleOrder($seller, 5_000, 500, 'EUR');
        $second = $this->eligibleOrder($seller, 6_000, 600, 'USD');
        $this->actingAs($seller);

        try {
            app(MarketplacePayoutService::class)->create($seller, 'bank_transfer');
            $this->fail('A mixed-currency payout should have failed validation.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                __('commerce.validation.payout_mixed_currencies'),
                $exception->errors()['payout'][0] ?? null,
            );
        }

        $this->assertDatabaseCount('marketplace_payouts', 0);
        $this->assertDatabaseHas('commerce_orders', [
            'id' => $first->id,
            'payout_id' => null,
            'payout_status' => 'pending',
        ]);
        $this->assertDatabaseHas('commerce_orders', [
            'id' => $second->id,
            'payout_id' => null,
            'payout_status' => 'pending',
        ]);
    }

    public function test_admin_api_returns_exact_created_payout_and_paid_flow_is_localized_and_audited(): void
    {
        $admin = $this->commerceAdmin();
        $seller = User::factory()->create(['language' => 'fr']);
        $order = $this->eligibleOrder($seller, 4_000, 400);
        Sanctum::actingAs($admin);

        $created = $this->postJson("/api/v1/admin/commerce/payouts/users/{$seller->id}", [
            'method' => 'bank_transfer',
            'notes' => 'Prepared from finance desk',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $seller->id)
            ->assertJsonPath('data.amount_cents', 3_600)
            ->assertJsonPath('data.status', 'prepared');

        $payoutId = (int) $created->json('data.id');
        $created->assertJsonPath(
            'data.reference',
            'AIR-PAY-'.now()->format('Y').'-'.str_pad((string) $payoutId, 6, '0', STR_PAD_LEFT),
        );

        $preparedNotification = Notification::query()
            ->where('user_id', $seller->id)
            ->where('type', 'commerce.payout.prepared')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('fr', data_get($preparedNotification->data, 'locale'));
        $this->assertSame(
            __('commerce.notifications.payout_prepared_title', locale: 'fr'),
            data_get($preparedNotification->data, 'title'),
        );

        $this->patchJson("/api/v1/admin/commerce/payouts/{$payoutId}/paid", [
            'notes' => 'Transfer confirmed',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $payoutId)
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('commerce_orders', [
            'id' => $order->id,
            'payout_id' => $payoutId,
            'payout_status' => 'paid',
        ]);
        $this->assertDatabaseHas('commerce_audit_logs', [
            'auditable_type' => MarketplacePayout::class,
            'auditable_id' => $payoutId,
            'action' => 'payout.paid',
        ]);

        $paidNotification = Notification::query()
            ->where('user_id', $seller->id)
            ->where('type', 'commerce.payout.paid')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('fr', data_get($paidNotification->data, 'locale'));
        $this->assertSame(
            'commerce.notifications.payout_paid_body',
            data_get($paidNotification->data, 'i18n.body_key'),
        );

        $this->patchJson("/api/v1/admin/commerce/payouts/{$payoutId}/paid")
            ->assertUnprocessable();
    }

    public function test_summary_and_candidates_exclude_mixed_seller_carts_with_constant_summary_queries(): void
    {
        $seller = User::factory()->create();
        $otherSeller = User::factory()->create();
        $this->eligibleOrder($seller, 7_000, 700);

        $mixedCart = CommerceOrder::query()->create([
            'user_id' => User::factory()->create()->id,
            'type' => 'marketplace_cart',
            'provider' => 'bank_transfer',
            'amount_cents' => 10_000,
            'commission_cents' => 1_000,
            'currency' => 'EUR',
            'status' => 'completed',
            'shipping_status' => 'open',
            'payout_status' => 'pending',
            'issue_status' => 'none',
            'completed_at' => now()->subDays(20),
        ]);
        $this->addDigitalItem($mixedCart, $this->productFor($seller, 'EUR'), 4_000);
        $this->addDigitalItem($mixedCart, $this->productFor($otherSeller, 'EUR'), 6_000);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $summary = app(MarketplacePayoutService::class)->summary($seller);
        $summaryQueries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(3, count($summaryQueries));
        $this->assertSame(1, $summary['pending_orders']);
        $this->assertSame(1, $summary['eligible_orders']);
        $this->assertSame(6_300, $summary['amount_cents']);

        $candidates = app(MarketplacePayoutService::class)->candidates();
        $this->assertCount(1, $candidates);
        $this->assertSame($seller->id, $candidates[0]['user_id']);
        $this->assertSame(1, $candidates[0]['orders_count']);
        $this->assertFalse($candidates[0]['has_mixed_currencies']);
    }

    private function eligibleOrder(
        User $seller,
        int $amountCents,
        int $commissionCents,
        string $currency = 'EUR',
    ): CommerceOrder {
        $product = $this->productFor($seller, $currency);

        return CommerceOrder::query()->create([
            'user_id' => User::factory()->create()->id,
            'orderable_type' => MarketplaceProduct::class,
            'orderable_id' => $product->id,
            'type' => 'marketplace_product',
            'provider' => 'bank_transfer',
            'amount_cents' => $amountCents,
            'commission_cents' => $commissionCents,
            'currency' => $currency,
            'status' => 'completed',
            'shipping_status' => 'open',
            'payout_status' => 'pending',
            'issue_status' => 'none',
            'completed_at' => now()->subDays(20),
        ]);
    }

    private function productFor(User $seller, string $currency): MarketplaceProduct
    {
        return MarketplaceProduct::query()->create([
            'user_id' => $seller->id,
            'title' => 'Digital training '.$currency.' '.uniqid(),
            'category' => 'digital_products',
            'offer_type' => 'training_plan',
            'product_type' => 'digital',
            'is_shippable' => false,
            'manages_stock' => false,
            'price_cents' => 4_000,
            'currency' => $currency,
            'status' => 'published',
            'moderation_status' => 'approved',
            'commission_percent' => 10,
            'payout_status' => 'pending_sales',
        ]);
    }

    private function addDigitalItem(CommerceOrder $order, MarketplaceProduct $product, int $totalCents): void
    {
        $order->items()->create([
            'orderable_type' => MarketplaceProduct::class,
            'orderable_id' => $product->id,
            'title' => $product->title,
            'quantity' => 1,
            'unit_gross_cents' => $totalCents,
            'shipping_cents' => 0,
            'net_cents' => $totalCents,
            'tax_cents' => 0,
            'total_cents' => $totalCents,
            'currency' => $product->currency,
            'tax_rate_percent' => 0,
            'is_shippable' => false,
        ]);
    }

    private function commerceAdmin(): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('marketplace.manage');

        $admin = User::factory()->create();
        $admin->givePermissionTo('marketplace.manage');

        return $admin;
    }
}
