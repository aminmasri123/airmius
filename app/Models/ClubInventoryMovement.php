<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubInventoryMovement extends Model
{
    protected $fillable = [
        'club_id',
        'club_inventory_item_id',
        'recorded_by',
        'type',
        'quantity_delta',
        'quantity_before',
        'quantity_after',
        'purchase_price_cents',
        'deposit_cents',
        'batch_number',
        'supplier',
        'occurred_on',
        'reason',
        'correction_of_id',
        'correction_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
            'purchase_price_cents' => 'integer',
            'deposit_cents' => 'integer',
            'occurred_on' => 'date',
            'correction_snapshot' => 'array',
        ];
    }

    public function item()
    {
        return $this->belongsTo(ClubInventoryItem::class, 'club_inventory_item_id');
    }
}
