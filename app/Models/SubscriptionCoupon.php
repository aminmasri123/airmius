<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionCoupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'value_cents',
        'percent_off',
        'max_redemptions',
        'redeemed_count',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isRedeemable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return ! $this->max_redemptions || $this->redeemed_count < $this->max_redemptions;
    }

    public function discountFor(int $amountCents): int
    {
        $discount = $this->type === 'fixed'
            ? (int) $this->value_cents
            : (int) floor($amountCents * ((int) $this->percent_off / 100));

        return max(0, min($discount, $amountCents));
    }
}
