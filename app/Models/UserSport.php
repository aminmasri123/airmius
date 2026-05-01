<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSport extends Model
{
    protected $fillable = [
        'user_id',
        'sport_id',
        'status',
        'experience_level',
        'visibility',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }
}
