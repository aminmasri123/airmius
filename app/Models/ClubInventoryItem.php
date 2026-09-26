<?php

namespace App\Models;

use App\Models\Concerns\CleansClubMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClubInventoryItem extends Model
{
    use CleansClubMetadata;
    use HasFactory;

    public function clubMetadataSubjectType(): string
    {
        return 'inventory_item';
    }

    protected $fillable = [
        'club_id', 'club_department_id', 'team_id', 'parent_id', 'resource_type', 'opening_hours', 'booking_rules', 'rental_price_rules', 'name', 'sku', 'category', 'location', 'description',
        'article_number', 'batch_number', 'purchase_price_cents', 'deposit_cents', 'supplier', 'purchased_on',
        'quantity_total', 'quantity_available', 'condition', 'status',
        'minimum_stock', 'reserved_quantity', 'reorder_lead_time_days',
        'qr_token', 'qr_issued_at', 'qr_revoked_at', 'requires_approval',
    ];

    protected $casts = [
        'quantity_total' => 'integer',
        'quantity_available' => 'integer',
        'minimum_stock' => 'integer',
        'reserved_quantity' => 'integer',
        'reorder_lead_time_days' => 'integer',
        'purchase_price_cents' => 'integer',
        'deposit_cents' => 'integer',
        'purchased_on' => 'date',
        'opening_hours' => 'array',
        'booking_rules' => 'array',
        'rental_price_rules' => 'array',
        'qr_issued_at' => 'datetime',
        'qr_revoked_at' => 'datetime',
        'requires_approval' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            $item->qr_token ??= (string) Str::uuid();
            $item->qr_issued_at ??= now();
        });
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function department()
    {
        return $this->belongsTo(ClubDepartment::class, 'club_department_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function loans()
    {
        return $this->hasMany(ClubInventoryLoan::class);
    }

    public function maintenanceRecords()
    {
        return $this->hasMany(ClubInventoryMaintenanceRecord::class);
    }

    public function movements()
    {
        return $this->hasMany(ClubInventoryMovement::class);
    }
}
