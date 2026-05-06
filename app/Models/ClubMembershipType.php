<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClubMembershipType extends Model
{
    protected $fillable = [
        'club_id',
        'name',
        'slug',
        'description',
        'is_public',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $type) {
            $type->slug = $type->slug ?: Str::slug($type->name);
        });
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function contributionRules()
    {
        return $this->hasMany(ClubContributionRule::class);
    }
}
