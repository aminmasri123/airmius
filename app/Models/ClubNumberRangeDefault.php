<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubNumberRangeDefault extends Model
{
    use HasFactory;

    protected $fillable = ['club_id', 'scope', 'club_number_range_id', 'assigned_by'];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function numberRange()
    {
        return $this->belongsTo(ClubNumberRange::class, 'club_number_range_id');
    }
}
