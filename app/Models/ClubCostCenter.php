<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubCostCenter extends Model
{
    use HasFactory;

    protected $fillable = ['club_id', 'parent_id', 'code', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function financeEntries()
    {
        return $this->hasMany(ClubFinanceEntry::class);
    }

    public function financeAssignments()
    {
        return $this->hasMany(ClubFinanceAssignment::class);
    }
}
