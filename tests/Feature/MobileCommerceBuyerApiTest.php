<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\MarketplaceProduct;
use App\Models\Notification as AppNotificationModel;
use App\Models\Setting;
use App\Models\SubscriptionCoupon;
use App\Models\User;
use App\Notifications\CommerceOrderAwaitingTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileCommerceBuyerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_manage_only_their_cart_and_unapproved_products_are_rejected(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create(['country' => 'DE']);
        $otherBuyer = User::factory()->create(['country' => 'DE']);
        $product = $this->publishedProduct($seller);
        $unapproved = $this->publishedProduct($seller, [
            'title' => 'Nicht freigegeben',
            'moderation_status' => 'pending',
        ]);

        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/commerce/cart')
            ->assertOk()
            ->assertJsonPath('data.cart.items_count', 0)
            ->assertJsonPath('data.checkout_address.country', 'DE')
            ->assertJsonPath('data.payment_providers.2', 'bank_transfer');

        $response = $this->postJson("/api/v1/commerce/cart/items/{$product->id}", [
            'quantity' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.product.id', $product->id);

        $itemId = (int) $response->json('data.items.0.id');

        Sanctum::actingAs($otherBuyer);
        $this->patchJson("/api/v1/commerce/cart/items/{$itemId}", ['quantity' => 4])
            ->assertForbidden();
        $this->deleteJson("/api/v1/commerce/cart/items/{$itemId}")->assertForbidden();
        $this->getJson('/api/v1/commerce/cart')->assertOk()
            ->assertJsonPath('data.cart.items_count', 0);

        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/commerce/cart')->assertOk()
            ->assertJsonPath('data.cart.items.0.id', $itemId)
            ->assertJsonPath('data.cart.items.0.quantity', 2);
        $this->patchJson("/api/v1/commerce/cart/items/{$itemId}", ['quantity' => 3])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 3);

        $this->postJson("/api/v1/commerce/cart/items/{$unapproved->id}")
            ->assertNotFound();

        $this->deleteJson("/api/v1/commerce/cart/items/{$itemId}")
            ->assertOk()
            ->assertJsonPath('data.items_count', 0);
    }

    public function test_buyer_can_checkout_cart_with_bank_transfer_and_safe_payment_details(): void
    {
        Notification::fake();
        Setting::setValue('billing_bank_account_holder', 'Airmius GmbH');
        Setting::setValue('billing_bank_name', 'Airmius Bank');
        Setting::setValue('billing_iban', 'DE89370400440532013000');
        Setting::setValue('billing_bic', 'COBADEFFXXX');
        Setting::setValue('billing_payment_terms_days', 10);

        $buyer = User::factory()->create(['country' => 'DE', 'language' => 'de']);
        $product = $this->publishedProduct(User::factory()->create(), [
            'title' => 'Athletik Onlinekurs',
            'category' => 'course',
            'offer_type' => 'online_course',
            'is_shippable' => false,
        ]);
        Sanctum::actingAs($buyer);

        $this->postJson("/api/v1/commerce/cart/items/{$product->id}", [
            'quantity' => 2,
        ])->assertCreated();

        $coupon = SubscriptionCoupon::query()->create([
            'code' => 'T30-04',
            'name' => 'Marketplace test',
            'scope' => 'marketplace',
            'type' => 'fixed',
            'value_cents' => 400,
            'max_redemptions' => 1,
            'is_active' => true,
        ]);
        $originalAmount = $this->getJson('/api/v1/commerce/cart')
            ->assertOk()->json('data.cart.summary.amount_cents');
        $this->getJson('/api/v1/commerce/cart?coupon_code=invalid')
            ->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
        $this->getJson('/api/v1/commerce/cart?coupon_code=t30-04')
            ->assertOk()
            ->assertJsonPath('data.cart.summary.coupon_code', 'T30-04')
            ->assertJsonPath('data.cart.summary.discount_cents', 400)
            ->assertJsonPath('data.cart.summary.amount_cents', $originalAmount - 400);
        $this->assertSame(0, (int) $coupon->fresh()->redeemed_count);

        $response = $this->postJson('/api/v1/commerce/cart/checkout', [
            'provider' => 'bank_transfer',
            'coupon_code' => 'T30-04',
            'accepted_terms' => true,
            'shipping_country' => 'DE',
            'shipping_postal_code' => '10115',
            'shipping_city' => 'Berlin',
            'shipping_street' => 'Sportweg',
            'shipping_house_number' => '7',
            'save_shipping_address' => true,
            'shipping_address_label' => 'Zuhause',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'awaiting_transfer')
            ->assertJsonPath('data.provider', 'bank_transfer')
            ->assertJsonPath('data.payment_action.type', 'bank_transfer')
            ->assertJsonPath('data.bank_transfer.account_holder', 'Airmius GmbH')
            ->assertJsonPath('data.bank_transfer.iban', 'DE89370400440532013000')
            ->assertJsonPath('data.shipping_address.city', 'Berlin')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.support.can_cancel', true);

        $this->assertStringStartsWith('AIR-COM-', (string) $response->json('data.payment_reference'));
        $order = CommerceOrder::query()->findOrFail((int) $response->json('data.id'));
        $this->assertSame($originalAmount - 400, (int) $order->amount_cents);
        $this->assertSame((int) $order->amount_cents, (int) $order->items()->sum('total_cents'));
        $this->assertSame(1, (int) $coupon->fresh()->redeemed_count);
        $buyerNotification = AppNotificationModel::query()
            ->where('user_id', $buyer->id)
            ->where('type', 'commerce.order.awaiting_transfer')
            ->firstOrFail();

        $this->assertSame(__('commerce.notifications.awaiting_transfer_title'), data_get($buyerNotification->data, 'title'));
        $this->assertStringContainsString((string) $order->payment_reference, (string) data_get($buyerNotification->data, 'body'));
        $this->assertSame($order->id, data_get($buyerNotification->data, 'order_id'));
        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.action_url', 'airmius://marketplace/orders/'.$order->id);

        Notification::assertSentTo(
            $buyer,
            CommerceOrderAwaitingTransfer::class,
            function (CommerceOrderAwaitingTransfer $notification) use ($buyer, $order): bool {
                $mail = $notification->toMail($buyer);

                return $mail->subject === 'Zahlungsdaten für deine Airmius Bestellung #'.$order->id
                    && collect($mail->introLines)->contains('IBAN: DE89370400440532013000')
                    && collect($mail->introLines)->contains('Verwendungszweck: '.$order->payment_reference);
            },
        );
        $this->assertDatabaseHas('commerce_shipping_addresses', [
            'user_id' => $buyer->id,
            'label' => 'Zuhause',
            'city' => 'Berlin',
        ]);

        $this->getJson('/api/v1/commerce/cart')
            ->assertOk()
            ->assertJsonPath('data.cart.items_count', 0);
    }

    public function test_order_detail_returns_product_image_and_saved_prices_after_catalog_price_changes(): void
    {
        $buyer = User::factory()->create();
        $product = $this->publishedProduct(User::factory()->create(), ['image_url' => '/images/shirt.jpg']);
        $order = $this->orderFor($buyer, $product, [
            'item_gross_cents' => 7800, 'shipping_cents' => 490, 'amount_cents' => 8290, 'tax_cents' => 1324,
        ]);
        $order->items()->update(['quantity' => 2, 'shipping_cents' => 490, 'total_cents' => 8290, 'tax_cents' => 1324, 'net_cents' => 6966]);
        $product->update(['price_cents' => 4500]);
        Sanctum::actingAs($buyer);
        $this->getJson("/api/v1/commerce/orders/{$order->id}")->assertOk()
            ->assertJsonPath('data.items.0.image_url', '/images/shirt.jpg')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.unit_gross_cents', 3900)
            ->assertJsonPath('data.item_gross_cents', 7800)
            ->assertJsonPath('data.shipping_cents', 490)
            ->assertJsonPath('data.amount_cents', 8290)
            ->assertJsonPath('data.tax_cents', 1324);
    }

    public function test_buyer_can_report_issue_request_return_and_cancel_eligible_orders(): void
    {
        $buyer = User::factory()->create();
        $product = $this->publishedProduct(User::factory()->create());
        $delivered = $this->orderFor($buyer, $product, [
            'status' => 'completed',
            'shipping_status' => 'delivered',
            'completed_at' => now()->subDays(2),
            'delivered_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($buyer);

        $this->getJson("/api/v1/commerce/orders/{$delivered->id}")
            ->assertOk()
            ->assertJsonPath('data.support.can_report_issue', true)
            ->assertJsonPath('data.support.can_request_return', true)
            ->assertJsonPath('data.documents.invoice.available', false);

        $this->postJson("/api/v1/commerce/orders/{$delivered->id}/issue", [
            'issue_note' => 'Die Verpackung war stark beschädigt.',
        ])
            ->assertOk()
            ->assertJsonPath('data.issue_status', 'reported')
            ->assertJsonPath('data.support.can_report_issue', false);

        $itemId = $delivered->items()->value('id');
        $this->postJson("/api/v1/commerce/orders/{$delivered->id}/returns", [
            'commerce_order_item_id' => $itemId,
            'quantity' => 1,
            'reason' => 'Der Artikel passt nicht.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.return_requests.0.status', 'requested')
            ->assertJsonPath('data.support.has_open_return_request', true)
            ->assertJsonPath('data.support.can_request_return', false);

        $pending = $this->orderFor($buyer, $product, [
            'status' => 'awaiting_transfer',
            'shipping_status' => 'open',
        ]);

        $this->postJson("/api/v1/commerce/orders/{$pending->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.issue_status', 'cancelled')
            ->assertJsonPath('data.documents.credit_note.available', true);
    }

    public function test_order_actions_and_documents_are_not_available_to_other_buyers(): void
    {
        $owner = User::factory()->create();
        $order = $this->orderFor($owner, $this->publishedProduct(User::factory()->create()), [
            'status' => 'completed',
            'shipping_status' => 'delivered',
            'invoice_number' => 'AIR-RG-2026-000077',
        ]);

        Sanctum::actingAs($owner);
        $invoiceUrl = $this
            ->getJson("/api/v1/commerce/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.documents.invoice.available', true)
            ->json('data.documents.invoice.url');

        $this->assertIsString($invoiceUrl);
        $this->get($invoiceUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $tamperedUrl = preg_replace(
            '/signature=[^&]+/',
            'signature=invalid',
            $invoiceUrl,
        );
        $this->get($tamperedUrl)->assertForbidden();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/commerce/orders/{$order->id}")->assertNotFound();
        $this->postJson("/api/v1/commerce/orders/{$order->id}/issue", [
            'issue_note' => 'Nicht meine Bestellung.',
        ])->assertForbidden();
        $this->getJson("/api/v1/commerce/orders/{$order->id}/invoice")
            ->assertForbidden();
    }

    public function test_buyer_can_switch_an_unpaid_stripe_order_to_bank_transfer(): void
    {
        Notification::fake();
        config(['services.stripe.secret' => 'stripe-test']);
        Setting::setValue('billing_iban', 'DE89370400440532013000');
        $buyer = User::factory()->create();
        $order = $this->orderFor($buyer, $this->publishedProduct(User::factory()->create()), [
            'provider' => 'stripe',
            'provider_checkout_id' => 'cs_test_previous',
            'checkout_url' => 'https://checkout.stripe.com/previous',
            'payload' => ['pricing' => ['amount_cents' => 3900]],
        ]);
        $path = "/api/v1/commerce/orders/{$order->id}";
        Sanctum::actingAs(User::factory()->create());
        $this->postJson($path.'/payment-method', ['provider' => 'bank_transfer'])->assertForbidden();

        Sanctum::actingAs($buyer);
        $this->getJson($path)->assertOk()->assertJsonPath('data.payment_action.can_change_method', true);
        Http::preventStrayRequests();
        $stripe = 'https://api.stripe.com/v1/checkout/sessions/cs_test_previous';
        Http::fake([
            $stripe => Http::sequence()
                ->push(['status' => 'complete', 'payment_status' => 'paid'])
                ->push(['status' => 'open', 'payment_status' => 'unpaid']),
            $stripe.'/expire' => Http::response(['status' => 'expired', 'payment_status' => 'unpaid']),
        ]);
        $this->postJson($path.'/payment-method', ['provider' => 'bank_transfer'])->assertUnprocessable();
        $this->assertSame('stripe', $order->fresh()->provider);

        $this->postJson($path.'/payment-method', ['provider' => 'bank_transfer'])
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.status', 'awaiting_transfer')
            ->assertJsonPath('data.amount_cents', 3900)
            ->assertJsonPath('data.bank_transfer.iban', 'DE89370400440532013000')
            ->assertJsonPath('data.payment_action.can_change_method', false);
        $this->assertNull($order->fresh()->provider_checkout_id);
        $this->assertSame(3900, data_get($order->fresh()->payload, 'pricing.amount_cents'));
        $this->assertDatabaseCount('commerce_orders', 1);
    }

    private function publishedProduct(User $seller, array $overrides = []): MarketplaceProduct
    {
        return MarketplaceProduct::query()->create(array_merge([
            'user_id' => $seller->id,
            'title' => 'Airmius Trainingsshirt',
            'description' => 'Atmungsaktives Vereinsshirt.',
            'category' => 'equipment',
            'offer_type' => 'physical_product',
            'product_type' => 'single',
            'is_shippable' => true,
            'manages_stock' => false,
            'price_cents' => 3900,
            'currency' => 'EUR',
            'tax_class' => 'standard',
            'return_policy_type' => 'standard',
            'return_window_days' => 14,
            'status' => 'published',
            'moderation_status' => 'approved',
            'commission_percent' => 10,
            'payout_status' => 'pending_sales',
        ], $overrides));
    }

    private function orderFor(
        User $buyer,
        MarketplaceProduct $product,
        array $overrides = [],
    ): CommerceOrder {
        $order = CommerceOrder::query()->create(array_merge([
            'user_id' => $buyer->id,
            'type' => 'marketplace_cart',
            'provider' => 'bank_transfer',
            'item_gross_cents' => $product->price_cents,
            'shipping_cents' => 0,
            'net_cents' => 3277,
            'tax_cents' => 623,
            'amount_cents' => $product->price_cents,
            'currency' => 'EUR',
            'status' => 'pending',
            'shipping_status' => 'open',
        ], $overrides));

        $order->items()->create([
            'orderable_type' => MarketplaceProduct::class,
            'orderable_id' => $product->id,
            'title' => $product->title,
            'quantity' => 1,
            'unit_gross_cents' => $product->price_cents,
            'shipping_cents' => 0,
            'net_cents' => 3277,
            'tax_cents' => 623,
            'total_cents' => $product->price_cents,
            'currency' => 'EUR',
            'tax_rate_percent' => 19,
            'is_shippable' => true,
        ]);

        return $order->fresh('items');
    }
}
