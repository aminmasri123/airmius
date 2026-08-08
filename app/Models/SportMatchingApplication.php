<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SportMatchingApplication extends Model
{
    use HasFactory;

    protected $fillable = ['sport_matching_id', 'user_id', 'team_id', 'message', 'status'];

    public function matching() { return $this->belongsTo(SportMatching::class, 'sport_matching_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function team() { return $this->belongsTo(Team::class); }
    public function attendance() { return $this->hasOne(SportMatchingAttendance::class, 'sport_matching_application_id'); }
}
