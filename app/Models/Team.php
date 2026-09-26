<?php

namespace App\Models;

use App\Models\Concerns\CleansClubMetadata;
use App\Support\Roles;
use App\Support\TeamRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use CleansClubMetadata;
    use HasFactory;

    protected $fillable = [
        'name', 'club_id', 'sport_year_period_id', 'sport_type', 'logo', 'cover_image',
        'club_department_id', 'club_location_id', 'club_training_group_id',
        'birth_year_from', 'birth_year_to', 'performance_level', 'capacity',
        'waitlist_enabled', 'valid_from', 'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'waitlist_enabled' => 'boolean',
            'valid_from' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
        ];
    }

    public const ROLES = TeamRoles::TEAM_ASSIGNABLE_ROLES;

    public function clubMetadataSubjectType(): string
    {
        return 'team';
    }

    public function scopeVisibleTo($query, $user)
    {
        if (
            $user->hasAnyRole(Roles::FULL_ACCESS)
            || $user->can('teams.view')
        ) {
            return $query;
        }

        return $query->where(function ($query) use ($user) {
            $query
                ->whereHas('users', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->orWhereHas('club.users', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                });
        });
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function sportYearPeriod()
    {
        return $this->belongsTo(ClubYearPeriod::class, 'sport_year_period_id');
    }

    public function department()
    {
        return $this->belongsTo(ClubDepartment::class, 'club_department_id');
    }

    public function location()
    {
        return $this->belongsTo(ClubLocation::class, 'club_location_id');
    }

    public function trainingGroup()
    {
        return $this->belongsTo(ClubTrainingGroup::class, 'club_training_group_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function competitions()
    {
        return $this->hasMany(Competition::class);
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

    public function inventoryItems()
    {
        return $this->hasMany(ClubInventoryItem::class);
    }

    public function invitations()
    {
        return $this->hasMany(TeamInvitation::class);
    }

    public function joinRequests()
    {
        return $this->hasMany(TeamJoinRequest::class);
    }

    public function targetTransferRequests()
    {
        return $this->hasMany(TeamTransferRequest::class, 'target_team_id');
    }

    public function sourceTransferRequests()
    {
        return $this->hasMany(TeamTransferRequest::class, 'source_team_id');
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
