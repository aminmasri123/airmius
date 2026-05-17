<?php

namespace App\Models;

use App\Support\UploadStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Story extends Model
{
    use HasFactory;

    public const VISIBILITIES = ['team', 'organization', 'public'];

    protected $fillable = [
        'moderation_status',
        'user_id',
        'club_id',
        'team_id',
        'publisher_type',
        'publisher_id',
        'visibility',
        'media_path',
        'media_thumbnail_path',
        'media_type',
        'media_size',
        'caption',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'viewed_by_me' => 'boolean',
    ];

    protected $appends = ['media_url', 'media_thumbnail_url', 'media_kind'];

    public function getMediaUrlAttribute(): ?string
    {
        return UploadStorage::url($this->media_path);
    }

    public function getMediaThumbnailUrlAttribute(): ?string
    {
        return UploadStorage::url($this->media_thumbnail_path);
    }

    public function getMediaKindAttribute(): string
    {
        return str_starts_with((string) $this->media_type, 'video/') ? 'video' : 'image';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function views()
    {
        return $this->hasMany(StoryView::class);
    }

    public function reactions()
    {
        return $this->hasMany(StoryReaction::class);
    }

    public function moderationFlags()
    {
        return $this->morphMany(ModerationFlag::class, 'flaggable');
    }

    public function reports()
    {
        return $this->morphMany(ContentReport::class, 'reportable');
    }
}
