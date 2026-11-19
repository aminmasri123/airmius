<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'team_id',
        'club_training_group_id',
        'template_source_id',
        'title',
        'description',
        'cadence',
        'period_type',
        'period_index',
        'season_label',
        'is_template',
        'starts_on',
        'ends_on',
        'status',
        'share_permission',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'period_index' => 'integer',
            'is_template' => 'boolean',
            'settings' => 'array',
        ];
    }

    /** Draft assignments reserve recipients; they do not release the plan. */
    public function scopeReleasedTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $visibility) => $visibility
            ->where('training_plans.created_by', $user->id)
            ->orWhereIn('training_plans.status', ['published', 'active']));
    }

    public function isReleasedTo(User $user): bool
    {
        return (int) $this->created_by === (int) $user->id || in_array($this->status, ['published', 'active'], true);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function trainingGroup()
    {
        return $this->belongsTo(ClubTrainingGroup::class, 'club_training_group_id');
    }

    public function templateSource()
    {
        return $this->belongsTo(self::class, 'template_source_id');
    }

    public function items()
    {
        return $this->hasMany(TrainingPlanItem::class)->orderBy('scheduled_at')->orderBy('sort_order');
    }

    public function assignments()
    {
        return $this->hasMany(TrainingPlanAssignment::class);
    }

    public function handovers()
    {
        return $this->hasMany(TrainingPlanHandover::class)->latest('starts_at')->latest();
    }

    public function historyEntries()
    {
        return $this->hasMany(TrainingPlanHistoryEntry::class)->latest();
    }
}
