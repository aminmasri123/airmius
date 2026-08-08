<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\MarketplaceProduct;
use App\Models\Notification as AppNotification;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceLocalizationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_commerce_catalogs_have_key_and_placeholder_parity(): void
    {
        $reference = Arr::dot(require lang_path('de/commerce.php'));

        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            $catalog = Arr::dot(require lang_path($locale.'/commerce.php'));
            $this->assertSame(array_keys($reference), array_keys($catalog), $locale.' key parity');

            foreach ($reference as $key => $source) {
                $this->assertSame(
                    $this->placeholders((string) $source),
                    $this->placeholders((string) $catalog[$key]),
                    $locale.' placeholder parity for '.$key,
                );
            }
        }
    }

    public function test_cart_responses_and_order_notifications_follow_the_recipient_locale(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create(['language' => 'fr', 'country' => 'FR']);
        $product = $this->publishedProduct($seller);
        Sanctum::actingAs($buyer);

        $cartResponse = $this->withHeader('X-App-Locale', 'fr')
            ->postJson('/api/v1/commerce/cart/items/'.$product->id, ['quantity' => 1])
            ->assertCreated()
            ->assertHeader('Content-Language', 'fr')
            ->assertJsonPath('message', __('commerce.flash.cart_added', locale: 'fr'));

        $itemId = (int) $cartResponse->json('data.items.0.id');
        $this->withHeader('X-App-Locale', 'fr')
            ->deleteJson('/api/v1/commerce/cart/items/'.$itemId)
            ->assertOk()
            ->assertJsonPath('message', __('commerce.flash.cart_removed', locale: 'fr'));

        $this->withHeader('X-App-Locale', 'fr')
            ->postJson('/api/v1/commerce/cart/checkout', [
                'provider' => 'bank_transfer',
                'accepted_terms' => true,
                'shipping_country' => 'FR',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('commerce.validation.cart_empty', locale: 'fr'));

        $buyer->forceFill(['language' => 'ar'])->save();
        $order = $this->cancellableOrder($buyer, $product);

        $this->withHeader('X-App-Locale', 'ar')
            ->postJson('/api/v1/commerce/orders/'.$order->id.'/cancel')
            ->assertOk()
            ->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('data.status', 'cancelled');

        $notification = AppNotification::query()
            ->where('user_id', $buyer->id)
            ->where('type', 'commerce.order.cancelled')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('ar', data_get($notification->data, 'locale'));
        $this->assertSame(__('commerce.notifications.cancelled_title', locale: 'ar'), data_get($notification->data, 'title'));
        $this->assertSame('commerce.notifications.cancelled_body', data_get($notification->data, 'i18n.body_key'));
    }

    public function test_public_marketplace_options_and_payment_methods_follow_the_request_locale(): void
    {
        $seller = User::factory()->create();
        $product = $this->publishedProduct($seller);
        Setting::setValue('billing_iban', 'DE89370400440532013000');
        config([
            'services.stripe.secret' => null,
            'services.paypal.client_id' => null,
            'services.paypal.client_secret' => null,
        ]);

        $this->withHeader('X-App-Locale', 'ar')
            ->get('/marketplace')
            ->assertOk()
            ->assertHeader('Content-Language', 'ar')
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories.0.label', __('commerce.marketplace.categories.all', locale: 'ar'))
                ->where('segments.7.label', __('commerce.marketplace.segments.plans', locale: 'ar'))
                ->where('sortOptions.2.label', __('commerce.marketplace.sort.price_asc', locale: 'ar'))
                ->where('availabilityOptions.1.label', __('commerce.marketplace.availability.available', locale: 'ar'))
                ->where('trustBenefits.3.label', __('commerce.marketplace.trust.order_status.label', locale: 'ar'))
            );

        $this->withHeader('X-App-Locale', 'fr')
            ->get('/marketplace/products/'.$product->id)
            ->assertOk()
            ->assertHeader('Content-Language', 'fr')
            ->assertInertia(fn (Assert $page) => $page
                ->where('paymentProviders.0.value', 'bank_transfer')
                ->where('paymentProviders.0.label', __('commerce.marketplace.payments.bank_transfer.label', locale: 'fr'))
                ->where('paymentProviders.0.description', __('commerce.marketplace.payments.bank_transfer.description', locale: 'fr'))
            );
    }

    private function publishedProduct(User $seller): MarketplaceProduct
    {
        return MarketplaceProduct::query()->create([
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
        ]);
    }

    private function cancellableOrder(User $buyer, MarketplaceProduct $product): CommerceOrder
    {
        $order = CommerceOrder::query()->create([
            'user_id' => $buyer->id,
            'type' => 'marketplace_cart',
            'provider' => 'bank_transfer',
            'item_gross_cents' => $product->price_cents,
            'net_cents' => 3277,
            'tax_cents' => 623,
            'amount_cents' => $product->price_cents,
            'currency' => 'EUR',
            'status' => 'awaiting_transfer',
            'shipping_status' => 'open',
        ]);

        $order->items()->create([
            'orderable_type' => MarketplaceProduct::class,
            'orderable_id' => $product->id,
            'title' => $product->title,
            'quantity' => 1,
            'unit_gross_cents' => $product->price_cents,
            'net_cents' => 3277,
            'tax_cents' => 623,
            'total_cents' => $product->price_cents,
            'currency' => 'EUR',
            'is_shippable' => true,
        ]);

        return $order->fresh('items');
    }

    /** @return array<int, string> */
    private function placeholders(string $value): array
    {
        preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $value, $matches);
        $placeholders = $matches[0];
        sort($placeholders);

        return $placeholders;
    }
}
