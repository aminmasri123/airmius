<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamPenaltyRule extends Model
{
    use HasFactory;

    public const TRIGGERS = [
        'late',
        'absence',
        'forgotten_equipment',
        'custom',
    ];

    public const CALCULATION_TYPES = [
        'fixed',
        'per_minute',
        'threshold_fixed',
        'item',
    ];

    protected $fillable = [
        'team_id',
        'title',
        'trigger',
        'calculation_type',
        'amount',
        'currency',
        'threshold_minutes',
        'max_amount',
        'unit_label',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'float',
        'max_amount' => 'float',
        'threshold_minutes' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function fees()
    {
        return $this->hasMany(TeamFee::class, 'penalty_rule_id');
    }

    public function calculateAmount(?int $minutes = null, ?float $manualAmount = null): float
    {
        if ($manualAmount !== null) {
            return max(0, round($manualAmount, 2));
        }

        $baseAmount = (float) ($this->amount ?? 0);

        $amount = match ($this->calculation_type) {
            'per_minute' => $baseAmount * max(0, (int) $minutes),
            'threshold_fixed' => (int) $minutes >= (int) ($this->threshold_minutes ?? 0) ? $baseAmount : 0,
            'item' => 0,
            default => $baseAmount,
        };

        if ($this->max_amount !== null && $this->max_amount >= 0) {
            $amount = min($amount, (float) $this->max_amount);
        }

        return max(0, round($amount, 2));
    }
}
