<?php

namespace App\Models;

use App\Models\Club;
use App\Models\Event;
use App\Models\TeamFee;
use App\Models\TeamPenaltyRule;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $fillable = ['name','club_id','sport_type','logo','cover_image'];

    public const ROLES = TeamRoles::TEAM_ASSIGNABLE_ROLES;


    public function scopeVisibleTo($query, $user)
    {
        if (
            $user->hasAnyRole(\App\Support\Roles::FULL_ACCESS)
            || $user->can('teams.view')
        ) {
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
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function trainingPlans()
    {
        return $this->hasMany(TrainingPlan::class);
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function folders()
    {
        return $this->hasMany(Folder::class);
    }

    public function invitations()
    {
        return $this->hasMany(TeamInvitation::class);
    }

    public function joinRequests()
    {
        return $this->hasMany(TeamJoinRequest::class);
    }

    public function penaltyRules()
    {
        return $this->hasMany(TeamPenaltyRule::class);
    }

    public function fees()
    {
        return $this->hasMany(TeamFee::class);
    }
}
