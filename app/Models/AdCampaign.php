<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AdCampaign extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (AdCampaign $campaign) {
            // The source is immutable, including edits through the admin form.
            $original = $campaign->getOriginal('audience') ?: [];
            if (! empty($original['promoted_post_id'])) {
                $campaign->audience = [...($campaign->audience ?: []), 'promoted_post_id' => $original['promoted_post_id']];
                $campaign->placement = 'feed';
                $campaign->target_url = route('auth.posts.index', ['post' => $original['promoted_post_id']]);
            }
        });
    }

    protected $fillable = [
        'sponsor_id',
        'user_id',
        'club_id',
        'is_internal',
        'force_priority',
        'name',
        'headline',
        'description',
        'primary_text',
        'target_url',
        'cta_label',
        'objective',
        'placement',
        'creative_format',
        'creative_image_path',
        'creative_image_url',
        'audience',
        'budget_cents',
        'daily_budget_cents',
        'billing_event',
        'spent_cents',
        'impressions',
        'clicks',
        'status',
        'review_note',
        'reviewed_at',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'audience' => 'array',
            'is_internal' => 'boolean',
            'force_priority' => 'boolean',
        ];
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function stats(): HasMany
    {
        return $this->hasMany(AdCampaignStat::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AdEvent::class);
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(AdCreative::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(AdGroup::class);
    }

    public function commerceOrders(): MorphMany
    {
        return $this->morphMany(CommerceOrder::class, 'orderable');
    }
}
