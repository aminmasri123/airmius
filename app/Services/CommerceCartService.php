<?php

namespace App\Services;

use App\Models\CommerceCart;
use App\Models\CommerceCartItem;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceProductInventory;
use App\Models\User;

class CommerceCartService
{
    public function __construct(private MarketplacePricingService $pricing) {}

    public function cartFor(User $user): CommerceCart
    {
        return CommerceCart::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['currency' => 'EUR'],
        );
    }

    public function resourceFor(User $user, array $shippingAddress, array $customer = []): array
    {
        $cart = $this->cartFor($user)->load('items.product');
        $summary = $cart->items->isNotEmpty()
            ? $this->quote($cart, $shippingAddress, $customer)
            : [
                'item_gross_cents' => 0,
                'shipping_cents' => 0,
                'net_cents' => 0,
                'tax_cents' => 0,
                'amount_cents' => 0,
                'currency' => 'EUR',
                'items' => [],
            ];
        $summaryForClient = $summary;
        unset($summaryForClient['items']);

        return [
            'id' => $cart->id,
            'items_count' => $cart->items->count(),
            'items' => collect($summary['items'] ?? [])->map(function (array $summaryItem) {
                /** @var CommerceCartItem $item */
                $item = $summaryItem['cart_item'];
                /** @var MarketplaceProduct $product */
                $product = $summaryItem['product'];
                $quote = $summaryItem['quote'];

                return [
                    'id' => $item->id,
                    'quantity' => (int) $summaryItem['quantity'],
                    'line_total_cents' => (int) ($quote['item_gross_cents'] ?? ((int) $product->price_cents * (int) $summaryItem['quantity'])),
                    'product' => [
                        'id' => $product->id,
                        'title' => $product->title,
                        'description' => $product->description,
                        'category' => $product->category,
                        'sku' => $product->sku,
                        'image_url' => $product->image_url,
                        'price_cents' => $product->price_cents,
                        'currency' => $product->currency,
                        'stock_quantity' => $product->stock_quantity,
                        'show_url' => route('auth.commerce.products.show', $product),
                    ],
                ];
            })->values(),
            'summary' => $summaryForClient,
        ];
    }

    public function quote(CommerceCart $cart, array $shippingAddress, array $customer): array
    {
        $items = [];
        $currency = 'EUR';
        $itemGross = 0;
        $shipping = 0;
        $net = 0;
        $tax = 0;
        $commission = 0;
        $country = strtoupper((string) ($shippingAddress['country'] ?? 'DE'));
        $taxRate = 0.0;

        foreach ($cart->items as $cartItem) {
            $product = $cartItem->product;
            $quantity = max(1, (int) $cartItem->quantity);
            if (! $product || $product->status !== 'published' || ! $this->hasSellableStock($product, $country, $quantity)) {
                continue;
            }

            $fulfillmentInventory = $this->fulfillmentInventoryFor($product, $country, $quantity);
            $quote = $this->pricing->quote($product, $country, 'cart', [
                ...$shippingAddress,
                'origin_country' => $fulfillmentInventory?->warehouse?->country_code,
            ], $customer);
            $quote['item_gross_cents'] *= $quantity;
            $quote['item_net_cents'] *= $quantity;
            $quote['item_tax_cents'] *= $quantity;
            $quote['gross_cents'] = ($quote['item_gross_cents'] ?? 0) + ($quote['shipping_gross_cents'] ?? 0);
            $quote['net_cents'] = ($quote['item_net_cents'] ?? 0) + ($quote['shipping_net_cents'] ?? 0);
            $quote['tax_cents'] = ($quote['item_tax_cents'] ?? 0) + ($quote['shipping_tax_cents'] ?? 0);
            $quote['fulfillment_inventory'] = $fulfillmentInventory ? [
                'id' => $fulfillmentInventory->id,
                'commerce_warehouse_id' => $fulfillmentInventory->commerce_warehouse_id,
                'country_code' => $fulfillmentInventory->country_code,
            ] : null;
            $currency = $quote['currency'] ?? $currency;
            $taxRate = max($taxRate, (float) ($quote['tax_rate'] ?? 0));

            $itemGross += (int) $quote['item_gross_cents'];
            $shipping += (int) ($quote['shipping_gross_cents'] ?? 0);
            $net += (int) ($quote['net_cents'] ?? 0);
            $tax += (int) ($quote['tax_cents'] ?? 0);
            $commission += $this->pricing->commissionCents($product, (int) $quote['item_gross_cents']);
            $items[] = ['cart_item' => $cartItem, 'product' => $product, 'quantity' => $quantity, 'quote' => $quote];
        }

        return [
            'items' => $items,
            'currency' => $currency,
            'tax_country' => $country,
            'tax_rate_percent' => $taxRate,
            'item_gross_cents' => $itemGross,
            'shipping_cents' => $shipping,
            'shipping_gross_cents' => $shipping,
            'net_cents' => $net,
            'tax_cents' => $tax,
            'amount_cents' => $itemGross + $shipping,
            'gross_cents' => $itemGross + $shipping,
            'commission_cents' => $commission,
        ];
    }

    public function quoteWithQuantity(array $quote, int $quantity): array
    {
        $quantity = max(1, $quantity);

        $quote['item_gross_cents'] = (int) ($quote['item_gross_cents'] ?? $quote['gross_cents'] ?? 0) * $quantity;
        $quote['item_net_cents'] = (int) ($quote['item_net_cents'] ?? $quote['net_cents'] ?? 0) * $quantity;
        $quote['item_tax_cents'] = (int) ($quote['item_tax_cents'] ?? $quote['tax_cents'] ?? 0) * $quantity;
        $quote['gross_cents'] = $quote['item_gross_cents'] + (int) ($quote['shipping_gross_cents'] ?? 0);
        $quote['net_cents'] = $quote['item_net_cents'] + (int) ($quote['shipping_net_cents'] ?? 0);
        $quote['tax_cents'] = $quote['item_tax_cents'] + (int) ($quote['shipping_tax_cents'] ?? 0);

        return $quote;
    }

    public function hasSellableStock(MarketplaceProduct $product, ?string $country = null, int $quantity = 1): bool
    {
        if (in_array($product->offer_type, ['online_course', 'training_plan', 'service'], true) || $product->product_type === 'digital') {
            return true;
        }

        if (! $product->isAvailableForCountry($country)) {
            return false;
        }

        if (! (bool) $product->manages_stock) {
            return true;
        }

        return $product->sellableStockForCountry($country) >= max(1, $quantity);
    }

    public function fulfillmentInventoryFor(MarketplaceProduct $product, ?string $country, int $quantity): ?MarketplaceProductInventory
    {
        if (! (bool) $product->manages_stock || $product->isDigitalDelivery()) {
            return null;
        }

        return $product->inventories()
            ->with('warehouse:id,name,country_code,city,postal_code')
            ->availableForCountry(strtoupper((string) ($country ?: 'DE')))
            ->orderBy('lead_time_days')
            ->orderBy('id')
            ->get()
            ->first(fn (MarketplaceProductInventory $inventory) => $inventory->availableQuantity() >= max(1, $quantity));
    }
}
