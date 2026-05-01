<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GamificationRule extends Model
{
    protected $fillable = [
        'key',
        'actor_type',
        'category',
        'label',
        'description',
        'xp_amount',
        'daily_limit',
        'trust_delta',
        'is_penalty',
        'is_active',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'daily_limit' => 'integer',
            'xp_amount' => 'integer',
            'trust_delta' => 'integer',
            'is_penalty' => 'boolean',
            'is_active' => 'boolean',
            'meta' => 'array',
        ];
    }
}
