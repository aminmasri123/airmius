<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubContributionRule extends Model
{
    protected $fillable = [
        'club_id',
        'club_membership_type_id',
        'name',
        'valid_from',
        'valid_until',
        'billing_interval',
        'amount',
        'age_min',
        'age_max',
        'factor_key',
        'factor_operator',
        'factor_value',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function membershipType()
    {
        return $this->belongsTo(ClubMembershipType::class, 'club_membership_type_id');
    }

    public function scopeEffectiveOn($query, $date)
    {
        return $query
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date);
            });
    }
}
