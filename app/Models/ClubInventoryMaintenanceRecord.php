<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubInventoryMaintenanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id', 'club_inventory_item_id', 'reported_by', 'resolved_by',
        'title', 'description', 'status', 'opened_at', 'completed_at', 'cost',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'completed_at' => 'datetime',
        'cost' => 'decimal:2',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function item()
    {
        return $this->belongsTo(ClubInventoryItem::class, 'club_inventory_item_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
