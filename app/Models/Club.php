<?php

namespace App\Models;

use App\Support\Roles;
use App\Support\ClubRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Club extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (Club $club) {
            if (! $club->owner_id) {
                return;
            }

            $club->users()->syncWithoutDetaching([
                $club->owner_id => ['role' => 'owner', 'roles' => ['owner']],
            ]);

            if (! $club->currentSubscription && ($freePlan = SubscriptionPlan::free())) {
                $club->currentSubscription()->create([
                    'subscription_plan_id' => $freePlan->id,
                    'status' => 'active',
                    'trial_ends_at' => now()->addDays(30),
                ]);
            }
        });
    }

    protected $fillable = [
        'name',
        'sport_type',
        'is_official',
        'official_club_number',
        'verification_status',
        'requested_official_club_number',
        'verification_notes',
        'verification_requested_at',
        'verified_at',
        'rejected_at',
        'verified_by',
        'sepa_creditor_id',
        'sepa_iban',
        'sepa_bic',
        'datev_consultant_number',
        'datev_client_number',
        'datev_revenue_account',
        'datev_bank_account',
        'membership_requests_enabled',
        'member_pause_requests_enabled',
        'is_listed',
        'teams_are_listed',
        'members_can_post_to_club',
        'members_can_post_to_teams',
        'logo',
        'cover_image',
        'country',
        'street',
        'house_number',
        'postal_code',
        'city',
        'state',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'is_official' => 'boolean',
            'membership_requests_enabled' => 'boolean',
            'member_pause_requests_enabled' => 'boolean',
            'is_listed' => 'boolean',
            'teams_are_listed' => 'boolean',
            'members_can_post_to_club' => 'boolean',
            'members_can_post_to_teams' => 'boolean',
            'verification_requested_at' => 'datetime',
            'verified_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    public function scopeVisibleTo($query, $user)
    {
        if (
            $user->hasAnyRole(Roles::FULL_ACCESS)
            || $user->can('clubs.view')
            || $user->can('teams.view')
        ) {
            return $query;
        }

        return $query->whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->using(ClubUser::class)
            ->withPivot([
                'role',
                'roles',
                'membership_status',
                'club_membership_type_id',
                'member_number',
                'contribution_amount',
                'contribution_interval',
                'contribution_next_invoice_on',
                'contribution_last_invoice_at',
                'sepa_iban',
                'sepa_bic',
                'sepa_mandate_reference',
                'sepa_mandate_signed_on',
                'sepa_mandate_active',
                'joined_on',
                'membership_ends_on',
                'pause_requested_at',
                'paused_from',
                'paused_until',
                'membership_end_notified_at',
                'membership_notes',
            ])
            ->withTimestamps();
    }

    public function externalMembers()
    {
        return $this->hasMany(ClubExternalMember::class);
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    public function membershipTypes()
    {
        return $this->hasMany(ClubMembershipType::class);
    }

    public function contributionRules()
    {
        return $this->hasMany(ClubContributionRule::class);
    }

    public function membershipRequests()
    {
        return $this->hasMany(ClubMembershipRequest::class);
    }

    public function sponsors()
    {
        return $this->hasMany(Sponsor::class);
    }

    public function jobs()
    {
        return $this->hasMany(OrganizationJob::class);
    }

    public function admins()
    {
        return ClubRoles::whereAny($this->users(), ['owner', 'admin']);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function bankTransactions()
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function currentSubscription()
    {
        return $this->hasOne(ClubSubscription::class)->with('plan');
    }

    public function subscriptionInvoices()
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function subscriptionPlan(): ?SubscriptionPlan
    {
        return $this->currentSubscription?->plan ?? SubscriptionPlan::free();
    }

    public function memberUsageCount(): int
    {
        return $this->users()->count() + $this->externalMembers()->count();
    }

    public function canAddMembers(int $amount = 1): bool
    {
        $limit = $this->subscriptionPlan()?->member_limit;

        return $limit === null || ($this->memberUsageCount() + $amount) <= $limit;
    }
}
