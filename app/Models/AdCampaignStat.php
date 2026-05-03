<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdCampaignStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'ad_campaign_id',
        'date',
        'impressions',
        'clicks',
        'spent_cents',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }
}
