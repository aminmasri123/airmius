<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommerceShippingRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'country_code',
        'postal_code_prefix',
        'amount_cents',
        'currency',
        'free_from_cents',
        'is_active',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'free_from_cents' => 'integer',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }
}
