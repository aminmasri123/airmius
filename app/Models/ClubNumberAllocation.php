<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubNumberAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id', 'club_number_range_id', 'period_key', 'sequence_number',
        'formatted_number', 'allocation_key', 'assigned_subject_type', 'assigned_subject_id', 'allocated_by',
    ];

    protected function casts(): array
    {
        return [
            'period_key' => 'integer',
            'sequence_number' => 'integer',
            'assigned_subject_id' => 'integer',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function numberRange()
    {
        return $this->belongsTo(ClubNumberRange::class, 'club_number_range_id');
    }

    public function allocatedBy()
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }
}
