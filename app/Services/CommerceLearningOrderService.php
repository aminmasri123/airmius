<?php

namespace App\Services;

use App\Models\CommerceOrder;
use App\Models\LearningCoupon;
use App\Models\LearningEnrollment;
use App\Models\MarketplaceProduct;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CommerceLearningOrderService
{
    public function applyCoupon(MarketplaceProduct $product, array $quote, ?string $code): array
    {
        if (! $product->learning_course_id || blank($code)) {
            return $quote;
        }

        $coupon = LearningCoupon::query()
            ->where('learning_course_id', $product->learning_course_id)
            ->where('code', strtoupper(trim($code)))
            ->first();

        if (! $coupon || ! $coupon->isRedeemable()) {
            throw ValidationException::withMessages(['coupon_code' => 'Dieser Gutschein ist ungültig oder abgelaufen.']);
        }

        $oldItemGross = max(0, (int) ($quote['item_gross_cents'] ?? $quote['gross_cents'] ?? 0));
        $discount = $coupon->discountFor($oldItemGross);
        $newItemGross = max(0, $oldItemGross - $discount);
        $ratio = $oldItemGross > 0 ? $newItemGross / $oldItemGross : 1;

        $quote['item_gross_cents'] = $newItemGross;
        $quote['item_net_cents'] = (int) round((int) ($quote['item_net_cents'] ?? $quote['net_cents'] ?? 0) * $ratio);
        $quote['item_tax_cents'] = max(0, $newItemGross - $quote['item_net_cents']);
        $quote['gross_cents'] = $newItemGross + (int) ($quote['shipping_gross_cents'] ?? 0);
        $quote['net_cents'] = $quote['item_net_cents'] + (int) ($quote['shipping_net_cents'] ?? 0);
        $quote['tax_cents'] = $quote['item_tax_cents'] + (int) ($quote['shipping_tax_cents'] ?? 0);
        $quote['learning_coupon'] = [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'discount_cents' => $discount,
        ];

        return $quote;
    }

    public function grantAccessForOrder(CommerceOrder $order): void
    {
        $userId = $order->user_id ?: User::query()
            ->where('email', strtolower((string) $order->guest_email))
            ->value('id');

        if (! $userId) {
            return;
        }

        $order->loadMissing('items.orderable');

        foreach ($order->items as $item) {
            if (! $item->orderable instanceof MarketplaceProduct || ! $item->orderable->learning_course_id) {
                continue;
            }

            $enrollment = LearningEnrollment::query()->firstOrNew([
                'learning_course_id' => $item->orderable->learning_course_id,
                'user_id' => $userId,
            ]);

            $enrollment->forceFill([
                'status' => 'active',
                'started_at' => $enrollment->started_at ?: now(),
            ])->save();
        }

        $couponId = data_get($order->payload, 'pricing.learning_coupon.id');
        if ($couponId) {
            LearningCoupon::query()->whereKey($couponId)->increment('redeemed_count');
        }
    }

    public function revokeAccessForOrder(CommerceOrder $order, string $status = 'refunded'): void
    {
        $userId = $order->user_id ?: User::query()
            ->where('email', strtolower((string) $order->guest_email))
            ->value('id');

        if (! $userId) {
            return;
        }

        $courseIds = $this->productsForOrder($order)
            ->pluck('learning_course_id')
            ->filter()
            ->unique()
            ->values();

        if ($courseIds->isEmpty()) {
            return;
        }

        LearningEnrollment::query()
            ->where('user_id', $userId)
            ->whereIn('learning_course_id', $courseIds)
            ->update([
                'status' => $status,
                'completed_at' => null,
            ]);
    }

    public function productsForOrder(CommerceOrder $order)
    {
        $order->loadMissing('items.orderable');

        return collect([$order->orderable])
            ->concat($order->items->pluck('orderable'))
            ->filter(fn ($item) => $item instanceof MarketplaceProduct && $item->learning_course_id)
            ->values();
    }
}
