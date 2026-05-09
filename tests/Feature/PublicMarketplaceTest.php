<?php

namespace Tests\Feature;

use App\Models\MarketplaceProduct;
use App\Models\CommerceOrder;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_marketplace_can_be_rendered(): void
    {
        $this->createPublishedProduct();

        $response = $this->get('/marketplace');

        $response->assertStatus(200);
        $response->assertSee('Guest/Marketplace', false);
    }

    public function test_guest_can_open_product_detail(): void
    {
        $product = $this->createPublishedProduct();

        $response = $this->get('/marketplace/products/'.$product->id);

        $response->assertStatus(200);
        $response->assertSee('Guest/MarketplaceProductShow', false);
    }

    public function test_guest_can_start_bank_transfer_checkout(): void
    {
        Setting::setValue('billing_iban', 'DE89370400440532013000');
        Setting::setValue('billing_bank_account_holder', 'Airmius');
        Setting::setValue('billing_payment_terms_days', 14);

        $product = $this->createPublishedProduct();

        $response = $this->post('/marketplace/products/'.$product->id.'/checkout', [
            'guest_name' => 'Gast Kaeufer',
            'guest_email' => 'gast@example.com',
            'provider' => 'bank_transfer',
            'accepted_terms' => true,
        ]);

        $order = CommerceOrder::query()->first();

        $this->assertNotNull($order);
        $this->assertSame('gast@example.com', $order->guest_email);
        $this->assertNotNull($order->access_token);
        $this->assertSame('awaiting_transfer', $order->status);
        $response->assertRedirect(route('commerce-checkout.guest.bank-transfer.show', [$order, $order->access_token]));
    }

    private function createPublishedProduct(): MarketplaceProduct
    {
        return MarketplaceProduct::create([
            'title' => 'Lauftechnik Kurs',
            'description' => 'Ein Kurs für bessere Lauftechnik.',
            'category' => 'course',
            'price_cents' => 4900,
            'currency' => 'EUR',
            'status' => 'published',
            'moderation_status' => 'approved',
            'commission_percent' => 10,
            'payout_status' => 'pending_sales',
        ]);
    }
}
