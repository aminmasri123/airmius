<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubTask extends Model
{
    protected $fillable = ['club_id', 'created_by', 'title', 'completed_at'];

    protected $casts = ['completed_at' => 'datetime'];
}
