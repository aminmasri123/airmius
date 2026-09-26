<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubNumberRange extends Model
{
    use HasFactory;

    public const SCOPES = [
        'member', 'invoice', 'receipt', 'donation', 'inventory_item',
        'shop_invoice', 'shop_credit_note', 'shop_sku',
    ];

    public const RESET_POLICIES = ['never', 'yearly'];

    protected $fillable = [
        'club_id', 'scope', 'name', 'prefix', 'suffix', 'padding', 'start_number',
        'next_number', 'reset_policy', 'last_reset_year', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'padding' => 'integer',
            'start_number' => 'integer',
            'next_number' => 'integer',
            'last_reset_year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function allocations()
    {
        return $this->hasMany(ClubNumberAllocation::class);
    }

    public function defaultAssignment()
    {
        return $this->hasOne(ClubNumberRangeDefault::class);
    }
}
