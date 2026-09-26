<?php

namespace App\Models;

use App\Models\Concerns\CleansClubMetadata;
use App\Services\ClubYearPeriodResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Event extends Model
{
    use CleansClubMetadata;
    use HasFactory;

    public const TYPES = ['training', 'match', 'meeting', 'public'];

    public const VISIBILITIES = ['private', 'organization', 'public'];

    public const PARTICIPANT_STATUSES = ['yes', 'late', 'maybe', 'no', 'waitlist'];

    public const RSVP_STATUSES = ['yes', 'maybe', 'no', 'waitlist'];

    public const ATTENDANCE_STATUSES = ['present', 'late', 'absent', 'excused'];

    public const REGISTRATION_AUDIENCES = ['members_and_guests', 'members_only'];

    public const STATUSES = ['scheduled', 'cancelled'];

    public const PARTICIPATION_LIFECYCLE_STATUSES = [
        'reserved',
        'confirmed',
        'cancelled',
        'replacement_assigned',
        'refunded',
        'event_cancelled',
    ];

    public function clubMetadataSubjectType(): string
    {
        return 'event';
    }

    protected $fillable = [
        'club_id',
        'user_id',
        'team_id',
        'conversation_id',
        'sport_route_id',
        'competition_id',
        'competition_class_id',
        'competition_venue_id',
        'title',
        'type',
        'visibility',
        'status',
        'start_time',
        'end_time',
        'event_timezone',
        'location',
        'location_name',
        'location_street',
        'location_house_number',
        'location_postal_code',
        'location_city',
        'location_country',
        'location_latitude',
        'location_longitude',
        'max_participants',
        'min_participants',
        'registration_audience',
        'participation_requirements',
        'participation_consent_required',
        'participation_consent_version',
        'member_price_cents',
        'guest_price_cents',
        'waitlist_offer_ttl_minutes',
        'participant_response_required',
        'participant_response_deadline_at',
        'uses_penalty_catalog',
        'notes',
        'camp_groups',
        'camp_supervision',
        'camp_accommodation',
        'camp_catering',
        'camp_emergency_contacts',
        'camp_guardian_consent_required',
        'camp_travel_consent_required',
        'camp_privacy_notice_version',
        'recurring',
        'recurrence_series_id',
        'recurrence_rule_version_id',
        'recurrence_original_start_time',
        'recurrence_local_date',
        'recurrence_exception_kind',
        'recurrence_snapshot',
        'recurrence_days',
        'recurrence_ends_at',
        'reminder_at',
        'reminder_sent_at',
        'completed_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'recurrence_days' => 'array',
        'recurrence_ends_at' => 'datetime',
        'reminder_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'location_latitude' => 'float',
        'location_longitude' => 'float',
        'max_participants' => 'integer',
        'min_participants' => 'integer',
        'participation_requirements' => 'array',
        'participation_consent_required' => 'boolean',
        'member_price_cents' => 'integer',
        'guest_price_cents' => 'integer',
        'waitlist_offer_ttl_minutes' => 'integer',
        'participant_response_required' => 'boolean',
        'participant_response_deadline_at' => 'datetime',
        'uses_penalty_catalog' => 'boolean',
        'camp_groups' => 'array',
        'camp_supervision' => 'array',
        'camp_accommodation' => 'array',
        'camp_catering' => 'array',
        'camp_emergency_contacts' => 'array',
        'camp_guardian_consent_required' => 'boolean',
        'camp_travel_consent_required' => 'boolean',
        'recurrence_original_start_time' => 'datetime',
        'recurrence_snapshot' => 'array',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $event) {
            $clubId = $event->club_id;
            if (! $clubId && $event->team_id) {
                $clubId = Team::query()->whereKey($event->team_id)->value('club_id');
                $event->club_id ??= $clubId;
            }
            if ($clubId && $event->start_time) {
                $event->sport_year_period_id ??= app(ClubYearPeriodResolver::class)->idFor(
                    (int) $clubId,
                    'sport',
                    $event->start_time,
                );
            }
        });

        static::saving(function (self $event): void {
            if (! $event->competition_id) {
                return;
            }

            $competition = Competition::query()->find($event->competition_id);
            if (! $competition) {
                return;
            }

            $event->club_id ??= $competition->club_id;

            if ((int) $event->club_id !== (int) $competition->club_id) {
                throw ValidationException::withMessages([
                    'competition_id' => __('validation.exists', ['attribute' => 'competition_id']),
                ]);
            }

            foreach ([
                'competition_class_id' => CompetitionClass::class,
                'competition_venue_id' => CompetitionVenue::class,
            ] as $column => $modelClass) {
                if (! $event->{$column}) {
                    continue;
                }

                $relatedClubId = $modelClass::query()->whereKey($event->{$column})->value('club_id');
                if ($relatedClubId && (int) $relatedClubId !== (int) $competition->club_id) {
                    throw ValidationException::withMessages([
                        $column => __('validation.exists', ['attribute' => $column]),
                    ]);
                }
            }
        });
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function waitlistOfferExpiresAt()
    {
        $ttl = (int) ($this->waitlist_offer_ttl_minutes ?: 0);

        return $ttl > 0 ? now()->addMinutes($ttl) : null;
    }

    public function sportYearPeriod()
    {
        return $this->belongsTo(ClubYearPeriod::class, 'sport_year_period_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sportRoute()
    {
        return $this->belongsTo(SportRoute::class);
    }

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function competitionClass()
    {
        return $this->belongsTo(CompetitionClass::class);
    }

    public function competitionVenue()
    {
        return $this->belongsTo(CompetitionVenue::class);
    }

    public function competitionResults()
    {
        return $this->hasMany(CompetitionResult::class);
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function recurrenceSeries()
    {
        return $this->belongsTo(EventRecurrenceSeries::class, 'recurrence_series_id');
    }

    public function recurrenceRuleVersion()
    {
        return $this->belongsTo(EventRecurrenceRuleVersion::class, 'recurrence_rule_version_id');
    }

    public function rides()
    {
        return $this->hasMany(Ride::class);
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'event_participants')
            ->withPivot([
                'status',
                'lifecycle_status',
                'payment_id',
                'refund_payment_id',
                'replacement_for_participant_id',
                'rsvp_status',
                'attendance_status',
                'response_reason',
                'absence_reason',
                'camp_group_key',
                'camp_privacy_notice_accepted_at',
                'camp_travel_consent_accepted_at',
                'camp_guardian_consent_verified_at',
                'camp_emergency_contact_snapshot',
                'camp_dietary_notes',
                'response_mode',
                'responded_at',
                'waitlist_position',
                'waitlist_promoted_at',
                'waitlist_offer_expires_at',
                'cancelled_at',
                'refunded_at',
                'checked_in_at',
                'check_in_method',
            ])
            ->withTimestamps();
    }

    public function participantRecords()
    {
        return $this->hasMany(EventParticipant::class);
    }

    public function comments()
    {
        return $this->hasMany(EventComment::class);
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function penaltyFees()
    {
        return $this->hasMany(TeamFee::class);
    }

    public function resolvedClub(): ?Club
    {
        return $this->club ?: $this->team?->club;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $visibilityQuery) use ($user) {
            $visibilityQuery
                ->where('visibility', 'public')
                ->orWhere('user_id', $user->id)
                ->orWhere(function (Builder $organizationQuery) use ($user) {
                    $organizationQuery
                        ->where('visibility', 'organization')
                        ->whereHas('club.users', fn (Builder $clubUsers) => $clubUsers->where('users.id', $user->id));
                })
                ->orWhere(function (Builder $teamQuery) use ($user) {
                    $teamQuery
                        ->where('visibility', 'private')
                        ->whereHas('team.users', fn (Builder $teamUsers) => $teamUsers->where('users.id', $user->id));
                })
                ->orWhereHas('participants', fn (Builder $participants) => $participants->where('users.id', $user->id));
        });
    }
}
