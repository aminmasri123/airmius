<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningCoupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_course_id',
        'code',
        'discount_type',
        'discount_value',
        'max_redemptions',
        'redeemed_count',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'integer',
            'max_redemptions' => 'integer',
            'redeemed_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function course()
    {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    public function isRedeemable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at && now()->gt($this->expires_at)) {
            return false;
        }

        if ($this->max_redemptions !== null && $this->redeemed_count >= $this->max_redemptions) {
            return false;
        }

        return true;
    }

    public function discountFor(int $amountCents): int
    {
        $amountCents = max(0, $amountCents);

        if ($this->discount_type === 'fixed') {
            return min($amountCents, (int) $this->discount_value);
        }

        return min($amountCents, (int) round($amountCents * min(100, (int) $this->discount_value) / 100));
    }
}
