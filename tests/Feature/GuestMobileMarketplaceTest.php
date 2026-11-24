<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\MarketplaceProduct;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GuestMobileMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_browse_quote_and_order_without_creating_an_account(): void
    {
        Notification::fake();
        Setting::setValue('billing_iban', 'DE89370400440532013000');
        $seller = User::factory()->create();
        $product = MarketplaceProduct::create([
            'user_id' => $seller->id, 'title' => 'Trainingsshirt',
            'category' => 'equipment', 'offer_type' => 'physical_product',
            'product_type' => 'single', 'is_shippable' => true,
            'manages_stock' => false, 'price_cents' => 3900, 'currency' => 'EUR',
            'tax_class' => 'standard', 'status' => 'published', 'moderation_status' => 'approved',
        ]);
        $hidden = $product->replicate();
        $hidden->status = 'draft';
        $hidden->save();
        $this->getJson('/api/v1/public/commerce/products')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonMissing(['email' => $seller->email]);
        $this->getJson("/api/v1/public/commerce/products/{$hidden->id}")->assertNotFound();
        $this->postJson('/api/v1/public/commerce/cart', [
            'items' => [['product_id' => $hidden->id, 'quantity' => 1]],
        ])->assertUnprocessable();
        $items = [['product_id' => $product->id, 'quantity' => 2]];
        $quote = $this->postJson('/api/v1/public/commerce/cart', ['items' => $items])
            ->assertOk()->assertJsonPath('data.cart.items.0.quantity', 2);
        $payload = [
            'items' => $items, 'guest_name' => 'Gast', 'guest_email' => 'gast@example.test',
            'provider' => 'bank_transfer', 'accepted_terms' => true,
            'shipping_country' => 'DE', 'shipping_city' => 'Berlin', 'shipping_postal_code' => '10115',
            'shipping_street' => 'Teststraße', 'shipping_house_number' => '1',
        ];
        $result = $this->postJson('/api/v1/public/commerce/cart/checkout', $payload,
            ['Idempotency-Key' => 'guest-order-test-1234567890'])->assertCreated();
        $this->postJson('/api/v1/public/commerce/cart/checkout', $payload,
            ['Idempotency-Key' => 'guest-order-test-1234567890'])->assertCreated();
        $this->assertDatabaseCount('commerce_orders', 1);
        $order = CommerceOrder::firstOrFail();
        $this->assertNull($order->user_id);
        $this->assertSame('gast@example.test', $order->guest_email);
        $this->assertSame($quote->json('data.cart.summary.amount_cents'), $order->amount_cents);
        $token = $result->json('data.guest_access_token');
        $this->getJson("/api/v1/public/commerce/orders/{$order->id}/{$token}")->assertOk();
        $this->getJson("/api/v1/public/commerce/orders/{$order->id}/wrong")->assertNotFound();
        $this->assertSame(1, User::count());
    }
}
