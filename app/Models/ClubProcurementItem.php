<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubProcurementItem extends Model
{
    protected $fillable = [
        'club_procurement_request_id',
        'club_inventory_item_id',
        'name',
        'sku',
        'unit',
        'quantity_requested',
        'quantity_ordered',
        'quantity_received',
        'unit_price_cents',
    ];

    protected function casts(): array
    {
        return [
            'quantity_requested' => 'integer',
            'quantity_ordered' => 'integer',
            'quantity_received' => 'integer',
            'unit_price_cents' => 'integer',
        ];
    }

    public function procurementRequest()
    {
        return $this->belongsTo(ClubProcurementRequest::class, 'club_procurement_request_id');
    }

    public function inventoryItem()
    {
        return $this->belongsTo(ClubInventoryItem::class, 'club_inventory_item_id');
    }
}
