<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamBulkOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_bulk_order_id',
        'user_id',
        'quantity',
        'personalization',
        'unit_price_cents',
        'funded_share_cents',
        'payable_unit_price_cents',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'personalization' => 'array',
            'unit_price_cents' => 'integer',
            'funded_share_cents' => 'integer',
            'payable_unit_price_cents' => 'integer',
        ];
    }

    public function bulkOrder()
    {
        return $this->belongsTo(TeamBulkOrder::class, 'team_bulk_order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
