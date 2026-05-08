<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommerceTaxRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'country_code',
        'region',
        'tax_class',
        'tax_label',
        'rate_percent',
        'currency',
        'is_default',
        'is_active',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent' => 'float',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }
}
