<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClubInventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id', 'name', 'sku', 'category', 'location', 'description',
        'quantity_total', 'quantity_available', 'condition', 'status',
        'qr_token', 'requires_approval',
    ];

    protected $casts = [
        'quantity_total' => 'integer',
        'quantity_available' => 'integer',
        'requires_approval' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            $item->qr_token ??= (string) Str::uuid();
        });
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function loans()
    {
        return $this->hasMany(ClubInventoryLoan::class);
    }

    public function maintenanceRecords()
    {
        return $this->hasMany(ClubInventoryMaintenanceRecord::class);
    }
}
