<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'club_department_id',
        'code',
        'name',
        'starts_on',
        'ends_on',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'metadata' => 'array'];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function department()
    {
        return $this->belongsTo(ClubDepartment::class, 'club_department_id');
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
