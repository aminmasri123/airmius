<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'sponsor_id',
        'user_id',
        'club_id',
        'name',
        'description',
        'target_url',
        'budget_cents',
        'spent_cents',
        'impressions',
        'clicks',
        'status',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function stats(): HasMany
    {
        return $this->hasMany(AdCampaignStat::class);
    }
}
