<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalProviderUsageEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'area',
        'provider',
        'service',
        'operation',
        'billable_quantity',
        'input_tokens',
        'output_tokens',
        'status',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'billable_quantity' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
