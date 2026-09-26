<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedView extends Model
{
    protected $fillable = [
        'user_id',
        'workspace',
        'name',
        'configuration',
        'is_favorite',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'is_favorite' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
