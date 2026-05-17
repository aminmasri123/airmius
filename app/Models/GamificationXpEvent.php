<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GamificationXpEvent extends Model
{
    protected $fillable = [
        'user_id',
        'actor_type',
        'owner_type',
        'owner_id',
        'source_type',
        'source_id',
        'idempotency_key',
        'amount',
        'base_amount',
        'trust_multiplier',
        'limited_by_daily_cap',
        'reason',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'limited_by_daily_cap' => 'boolean',
            'meta' => 'array',
            'trust_multiplier' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function owner()
    {
        return $this->morphTo();
    }
}
