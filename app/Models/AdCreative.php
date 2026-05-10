<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdCreative extends Model
{
    use HasFactory;

    protected $fillable = [
        'ad_campaign_id',
        'name',
        'headline',
        'description',
        'primary_text',
        'target_url',
        'cta_label',
        'creative_format',
        'creative_image_path',
        'creative_image_url',
        'weight',
        'is_active',
        'spent_cents',
        'impressions',
        'clicks',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AdEvent::class);
    }
}
