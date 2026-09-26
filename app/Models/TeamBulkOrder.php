<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class TeamBulkOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'team_id',
        'marketplace_product_id',
        'created_by',
        'title',
        'order_window_starts_at',
        'order_deadline_at',
        'status',
        'supplier_name',
        'supplier_reference',
        'unit_price_cents',
        'funded_share_cents',
        'currency',
        'price_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'order_window_starts_at' => 'datetime',
            'order_deadline_at' => 'datetime',
            'unit_price_cents' => 'integer',
            'funded_share_cents' => 'integer',
            'price_snapshot' => 'array',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function product()
    {
        return $this->belongsTo(MarketplaceProduct::class, 'marketplace_product_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(TeamBulkOrderItem::class);
    }

    public function isAcceptingOrders(?Carbon $now = null): bool
    {
        $now ??= now();

        return $this->status === 'open'
            && $this->order_window_starts_at->lte($now)
            && $this->order_deadline_at->gte($now);
    }
}
