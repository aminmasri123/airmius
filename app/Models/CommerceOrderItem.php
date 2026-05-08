<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommerceOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'commerce_order_id',
        'orderable_type',
        'orderable_id',
        'title',
        'sku',
        'quantity',
        'unit_gross_cents',
        'shipping_cents',
        'net_cents',
        'tax_cents',
        'total_cents',
        'currency',
        'tax_rate_percent',
        'tax_class',
        'is_shippable',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_gross_cents' => 'integer',
            'shipping_cents' => 'integer',
            'net_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'tax_rate_percent' => 'float',
            'is_shippable' => 'boolean',
        ];
    }

    public function order()
    {
        return $this->belongsTo(CommerceOrder::class, 'commerce_order_id');
    }

    public function orderable()
    {
        return $this->morphTo();
    }
}
