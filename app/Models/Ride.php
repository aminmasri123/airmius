<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ride extends Model
{
    use HasFactory;

    protected $fillable = ['club_id','driver_id','from','to','departure_time','seats'];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'ride_users');
    }
}
