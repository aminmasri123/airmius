<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FamilyCalendarEntry extends Model
{
    protected $fillable = ['user_id', 'child_user_id', 'club_id', 'title', 'location', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
