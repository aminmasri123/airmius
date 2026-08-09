<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\CommerceOrderItem;
use App\Models\CommerceRefund;
use App\Models\CommerceReturnRequest;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceProduct;
use App\Models\User;
use App\Services\UserPrivacyExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CommerceRefundReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_refund_is_idempotent_and_holds_an_unpaid_marketplace_order(): void
    {
        [$admin, $order] = $this->adminAndOrder();
        Sanctum::actingAs($admin);

        $payload = [
            'amount_cents' => 2500,
            'reason' => 'Partial refund',
            'idempotency_key' => 'mobile-refund-operation-1',
        ];

        $this->postJson("/api/v1/admin/commerce/orders/{$order->id}/refund", $payload)
            ->assertOk()
            ->assertJsonPath('data.refunded_cents', 2500)
            ->assertJsonPath('data.payout_status', 'refund_hold')
            ->assertJsonPath('data.refunds.0.amount_cents', 2500)
            ->assertJsonPath('data.refunds.0.status', 'succeeded');

        $this->postJson("/api/v1/admin/commerce/orders/{$order->id}/refund", $payload)
            ->assertOk()
            ->assertJsonPath('data.refunded_cents', 2500);

        $this->assertSame(1, CommerceRefund::query()->count());
        $this->assertSame(2500, $order->fresh()->refunded_cents);
        $privacyExport = app(UserPrivacyExportService::class)->export($order->user);
        $this->assertSame($order->id, data_get($privacyExport, 'commerce.orders.0.id'));
        $this->assertSame(2500, data_get($privacyExport, 'commerce.orders.0.refunds.0.amount_cents'));

        $this->postJson("/api/v1/admin/commerce/orders/{$order->id}/refund", [
            ...$payload,
            'amount_cents' => 100,
        ])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
    }

    public function test_provider_failure_does_not_change_order_and_same_key_can_be_retried_safely(): void
    {
        config(['services.stripe.secret' => 'stripe-secret']);
        [$admin, $order] = $this->adminAndOrder([
            'provider' => 'stripe',
            'payload' => ['payment_intent' => 'pi_refund_test'],
        ]);
        Sanctum::actingAs($admin);

        Http::fake([
            'https://api.stripe.com/v1/refunds' => Http::sequence()
                ->push(['error' => ['message' => 'temporarily unavailable']], 503)
                ->push(['id' => 're_stripe_123'], 200),
        ]);

        $payload = [
            'amount_cents' => 1000,
            'reason' => 'Provider retry',
            'idempotency_key' => 'stripe-refund-retry-1',
        ];

        $this->postJson("/api/v1/admin/commerce/orders/{$order->id}/refund", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('provider');

        $this->assertSame(0, $order->fresh()->refunded_cents);
        $this->assertNull($order->credit_note_number);
        $this->assertDatabaseHas('commerce_refunds', [
            'commerce_order_id' => $order->id,
            'status' => 'failed',
        ]);

        $this->postJson("/api/v1/admin/commerce/orders/{$order->id}/refund", $payload)
            ->assertOk()
            ->assertJsonPath('data.refunded_cents', 1000);

        $refund = CommerceRefund::query()->sole();
        $this->assertSame('succeeded', $refund->status);
        $this->assertSame('re_stripe_123', $refund->provider_refund_id);
        $this->assertSame(1000, $order->fresh()->refunded_cents);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->hasHeader('Idempotency-Key', hash('sha256', 'stripe-refund-retry-1')));
    }

    public function test_refund_reduces_a_prepared_payout_before_it_can_be_paid(): void
    {
        [$admin, $order] = $this->adminAndOrder();
        $payout = MarketplacePayout::query()->create([
            'user_id' => $order->orderable->user_id,
            'currency' => 'EUR',
            'gross_cents' => 10000,
            'commission_cents' => 1000,
            'amount_cents' => 9000,
            'method' => 'bank_transfer',
            'status' => 'prepared',
            'reference' => 'AIR-PAY-REFUND-1',
        ]);
        $order->update(['payout_id' => $payout->id, 'payout_status' => 'prepared']);
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/commerce/orders/{$order->id}/refund", [
            'amount_cents' => 4000,
            'reason' => 'Partial return',
            'idempotency_key' => 'prepared-payout-refund-1',
        ])->assertOk();

        $payout->refresh();
        $this->assertSame(6000, $payout->gross_cents);
        $this->assertSame(600, $payout->commission_cents);
        $this->assertSame(5400, $payout->amount_cents);
        $this->assertSame(3600, $payout->adjustment_cents);
        $this->assertSame('adjusted_before_payment', $payout->reconciliation_status);
        $this->assertSame('adjusted', $order->fresh()->payout_status);

        $this->patchJson("/api/v1/admin/commerce/payouts/{$payout->id}/paid")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.adjustment_cents', 3600);
    }

    public function test_refund_after_paid_payout_creates_visible_seller_recovery(): void
    {
        [$admin, $order] = $this->adminAndOrder();
        $payout = MarketplacePayout::query()->create([
            'user_id' => $order->orderable->user_id,
            'currency' => 'EUR',
            'gross_cents' => 10000,
            'commission_cents' => 1000,
            'amount_cents' => 9000,
            'method' => 'bank_transfer',
            'status' => 'paid',
            'reference' => 'AIR-PAY-REFUND-2',
            'paid_at' => now(),
        ]);
        $order->update(['payout_id' => $payout->id, 'payout_status' => 'paid']);
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/commerce/orders/{$order->id}/refund", [
            'amount_cents' => 2000,
            'reason' => 'Late return',
            'idempotency_key' => 'paid-payout-refund-1',
        ])
            ->assertOk()
            ->assertJsonPath('data.payout_status', 'recovery_required');

        $payout->refresh();
        $this->assertSame(9000, $payout->amount_cents);
        $this->assertSame(1800, $payout->recovery_cents);
        $this->assertSame('seller_recovery_required', $payout->reconciliation_status);
        $this->assertDatabaseHas('commerce_refunds', [
            'commerce_order_id' => $order->id,
            'payout_impact_cents' => 1800,
            'payout_impact_status' => 'seller_recovery_required',
        ]);

        $this->patchJson("/api/v1/admin/commerce/payouts/{$payout->id}/paid")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payout');
    }

    public function test_return_refund_and_restock_are_both_idempotent(): void
    {
        [$admin, $order, $product] = $this->adminAndOrder([], [
            'manages_stock' => true,
            'stock_quantity' => 2,
            'is_shippable' => true,
        ]);
        $item = CommerceOrderItem::query()->create([
            'commerce_order_id' => $order->id,
            'orderable_type' => MarketplaceProduct::class,
            'orderable_id' => $product->id,
            'title' => $product->title,
            'quantity' => 1,
            'unit_gross_cents' => 10000,
            'total_cents' => 10000,
            'currency' => 'EUR',
            'is_shippable' => true,
        ]);
        $returnRequest = CommerceReturnRequest::query()->create([
            'commerce_order_id' => $order->id,
            'commerce_order_item_id' => $item->id,
            'user_id' => $order->user_id,
            'status' => 'approved',
            'reason' => 'Wrong size',
            'quantity' => 1,
            'requested_amount_cents' => 10000,
            'approved_amount_cents' => 10000,
            'currency' => 'EUR',
            'requested_at' => now(),
            'approved_at' => now(),
        ]);
        Sanctum::actingAs($admin);

        $payload = [
            'status' => 'refunded',
            'resolution_note' => 'Returned and refunded',
            'approved_amount_cents' => 10000,
            'restock' => true,
            'idempotency_key' => 'return-refund-'.$returnRequest->id,
        ];

        $this->patchJson("/api/v1/admin/commerce/returns/{$returnRequest->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded');
        $this->patchJson("/api/v1/admin/commerce/returns/{$returnRequest->id}", $payload)
            ->assertOk();

        $this->assertSame(1, CommerceRefund::query()->count());
        $this->assertSame(10000, $order->fresh()->refunded_cents);
        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertNotNull($returnRequest->fresh()->restocked_at);
        $this->assertDatabaseCount('commerce_stock_movements', 1);
    }

    public function test_refunded_status_cannot_be_set_without_a_booked_refund(): void
    {
        [$admin, $order] = $this->adminAndOrder();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/commerce/orders/{$order->id}/issue", [
            'issue_status' => 'refunded',
            'order_status' => 'refunded',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('issue_status');

        $this->assertSame('completed', $order->fresh()->status);
    }

    /** @return array{User, CommerceOrder, MarketplaceProduct} */
    private function adminAndOrder(array $orderOverrides = [], array $productOverrides = []): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('marketplace.manage');
        $admin = User::factory()->create();
        $admin->givePermissionTo('marketplace.manage');
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $product = MarketplaceProduct::query()->create(array_merge([
            'user_id' => $seller->id,
            'title' => 'Refund-safe training product',
            'category' => 'equipment',
            'product_type' => 'physical',
            'is_shippable' => false,
            'manages_stock' => false,
            'stock_quantity' => 0,
            'price_cents' => 10000,
            'currency' => 'EUR',
            'status' => 'published',
            'moderation_status' => 'approved',
            'commission_percent' => 10,
            'payout_status' => 'pending_sales',
        ], $productOverrides));
        $order = CommerceOrder::query()->create(array_merge([
            'user_id' => $buyer->id,
            'orderable_type' => MarketplaceProduct::class,
            'orderable_id' => $product->id,
            'type' => 'marketplace_product',
            'provider' => 'bank_transfer',
            'amount_cents' => 10000,
            'commission_cents' => 1000,
            'currency' => 'EUR',
            'status' => 'completed',
            'shipping_status' => 'delivered',
            'payout_status' => 'pending',
            'issue_status' => 'none',
            'completed_at' => now()->subDays(20),
            'delivered_at' => now()->subDays(15),
        ], $orderOverrides));

        return [$admin, $order, $product];
    }
}
