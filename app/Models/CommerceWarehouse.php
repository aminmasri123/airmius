<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommerceWarehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'club_id',
        'name',
        'country_code',
        'city',
        'postal_code',
        'address_line',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function inventories()
    {
        return $this->hasMany(MarketplaceProductInventory::class);
    }
}
