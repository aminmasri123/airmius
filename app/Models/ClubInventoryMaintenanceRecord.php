<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubInventoryMaintenanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id', 'club_inventory_item_id', 'reported_by', 'resolved_by', 'responsible_user_id', 'parent_id',
        'type', 'title', 'description', 'status', 'severity', 'booking_impact', 'opened_at', 'damage_reported_at',
        'starts_at', 'ends_at', 'completed_at', 'cost', 'estimated_cost_cents', 'recurrence_frequency',
        'recurrence_interval', 'recurrence_until', 'blocks_resource', 'resource_lock_snapshot', 'protected_photo_manifest',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'damage_reported_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'completed_at' => 'datetime',
        'cost' => 'decimal:2',
        'estimated_cost_cents' => 'integer',
        'recurrence_interval' => 'integer',
        'recurrence_until' => 'date',
        'blocks_resource' => 'boolean',
        'resource_lock_snapshot' => 'array',
        'protected_photo_manifest' => 'array',
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

    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
