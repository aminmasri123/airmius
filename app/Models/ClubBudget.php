<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubBudget extends Model
{
    use HasFactory;

    public const SCOPE_TYPES = ['club', 'department', 'team', 'project'];

    public const APPROVAL_STATUSES = ['draft', 'submitted', 'approved', 'rejected', 'archived'];

    protected $fillable = [
        'club_id',
        'parent_id',
        'club_year_period_id',
        'responsible_user_id',
        'scope_type',
        'club_department_id',
        'team_id',
        'project_name',
        'club_project_id',
        'name',
        'version',
        'approval_status',
        'planned_income_cents',
        'planned_expense_cents',
        'notes',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'planned_income_cents' => 'integer',
            'planned_expense_cents' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function yearPeriod()
    {
        return $this->belongsTo(ClubYearPeriod::class, 'club_year_period_id');
    }

    public function department()
    {
        return $this->belongsTo(ClubDepartment::class, 'club_department_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
