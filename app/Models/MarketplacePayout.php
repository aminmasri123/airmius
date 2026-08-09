<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplacePayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'currency',
        'gross_cents',
        'commission_cents',
        'amount_cents',
        'adjustment_cents',
        'recovery_cents',
        'method',
        'status',
        'reconciliation_status',
        'reference',
        'notes',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_cents' => 'integer',
            'commission_cents' => 'integer',
            'amount_cents' => 'integer',
            'adjustment_cents' => 'integer',
            'recovery_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(CommerceOrder::class, 'payout_id');
    }
}
