<?php

namespace App\Models;

use App\Models\Club;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $fillable = ['name','club_id','sport_type'];


    public function scopeVisibleTo($query, $user)
    {
        if ($user->isSystem()) {
            return $query;
        }

        return $query->whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('role');
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
