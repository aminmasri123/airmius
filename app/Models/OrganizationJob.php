<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationJob extends Model
{
    use HasFactory;

    public const EXPERIENCE_LEVELS = ['beginner', 'intermediate', 'advanced', 'expert', 'elite'];

    public const COMMITMENT_VOLUNTARY = 'voluntary';

    public const COMMITMENT_MANDATORY = 'mandatory';

    public const COMMITMENT_TYPES = [
        self::COMMITMENT_VOLUNTARY,
        self::COMMITMENT_MANDATORY,
    ];

    protected $fillable = [
        'club_id',
        'sport_id',
        'created_by',
        'title',
        'type',
        'minimum_experience_level',
        'required_qualifications',
        'location',
        'starts_at',
        'ends_at',
        'shift_slots_required',
        'commitment_type',
        'workload',
        'employment_type',
        'description',
        'contact_email',
        'application_url',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'required_qualifications' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'shift_slots_required' => 'integer',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function interests(): HasMany
    {
        return $this->hasMany(OrganizationJobInterest::class);
    }

    public function matchingVolunteerProfiles()
    {
        $requirements = collect($this->required_qualifications ?? [])
            ->map(fn ($requirement) => mb_strtolower(trim((string) $requirement)))
            ->filter()
            ->values();

        if (! $this->club) {
            return collect();
        }

        return $this->club->volunteerProfiles()
            ->where(function ($query) {
                $query->where('visibility', '!=', ClubVolunteerProfile::VISIBILITY_PRIVATE)
                    ->orWhere('user_id', $this->created_by);
            })
            ->get()
            ->filter(function (ClubVolunteerProfile $profile) use ($requirements) {
                if ($requirements->isEmpty()) {
                    return true;
                }

                $profileTerms = collect($profile->skills ?? [])
                    ->merge($profile->interests ?? [])
                    ->map(fn ($term) => mb_strtolower(trim((string) $term)))
                    ->filter();

                return $requirements->every(fn ($requirement) => $profileTerms->contains($requirement));
            })
            ->values();
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
