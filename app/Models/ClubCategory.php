<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubCategory extends Model
{
    use HasFactory;

    public const SCOPES = ['member', 'team', 'event', 'inventory_item', 'document', 'finance'];

    protected $fillable = ['club_id', 'scope', 'name', 'color', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function assignments()
    {
        return $this->hasMany(ClubCategoryAssignment::class);
    }
}
