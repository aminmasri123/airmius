<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ClubEmploymentEngagement extends Model
{
    public const TYPE_TRAINER = 'trainer';

    public const TYPE_INSTRUCTOR = 'instructor';

    public const TYPE_EMPLOYEE = 'employee';

    public const TYPE_CONTRACTOR = 'contractor';

    public const TYPES = [
        self::TYPE_TRAINER,
        self::TYPE_INSTRUCTOR,
        self::TYPE_EMPLOYEE,
        self::TYPE_CONTRACTOR,
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PLANNED = 'planned';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ENDED = 'ended';

    protected $guarded = ['id'];

    protected $casts = [
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
        'qualification_requirements' => 'array',
        'contract_terms' => 'encrypted:array',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function person()
    {
        return $this->belongsTo(ClubPersonProfile::class, 'club_person_profile_id');
    }

    public function roleDefinition()
    {
        return $this->belongsTo(ClubRoleDefinition::class, 'club_role_definition_id');
    }

    public function scopeForClub(Builder $query, Club|int $club): Builder
    {
        return $query->where('club_id', $club instanceof Club ? $club->id : $club);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isWorkforceType(): bool
    {
        return in_array($this->engagement_type, self::TYPES, true);
    }
}
