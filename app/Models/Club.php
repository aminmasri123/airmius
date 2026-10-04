<?php

namespace App\Models;

use App\Support\ClubRoles;
use App\Support\ClubWorkspaceAccess;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Club extends Model
{
    use HasFactory;

    private static ?bool $clubUserHasMemberCardDesign = null;

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
        'registry_authority',
        'registry_number',
        'federation_affiliations',
        'tax_authority',
        'tax_number',
        'vat_id',
        'tax_status',
        'tax_exemption_valid_until',
        'verification_status',
        'requested_official_club_number',
        'verification_notes',
        'verification_requested_at',
        'verified_at',
        'rejected_at',
        'verified_by',
        'sepa_creditor_id',
        'sepa_account_holder',
        'sepa_iban',
        'sepa_bic',
        'datev_consultant_number',
        'datev_client_number',
        'datev_revenue_account',
        'datev_bank_account',
        'datev_fee_account',
        'membership_requests_enabled',
        'member_pause_requests_enabled',
        'membership_application_fields',
        'membership_payment_methods',
        'membership_application_documents',
        'membership_application_document_types',
        'is_listed',
        'teams_are_listed',
        'members_can_post_to_club',
        'members_can_post_to_teams',
        'logo',
        'cover_image',
        'brand_primary_color',
        'brand_secondary_color',
        'brand_accent_color',
        'letterhead_settings',
        'document_templates',
        'country',
        'street',
        'house_number',
        'postal_code',
        'city',
        'state',
        'contact_email',
        'contact_phone',
        'website_url',
        'contact_details_public',
        'contact_persons',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'deletion_requested_at' => 'datetime',
            'deletion_scheduled_at' => 'datetime',
            'deletion_reminded_at' => 'datetime',
            'deletion_blocked_at' => 'datetime',
            'is_official' => 'boolean',
            'federation_affiliations' => 'array',
            'tax_exemption_valid_until' => 'date:Y-m-d',
            'contact_details_public' => 'boolean',
            'contact_persons' => 'array',
            'letterhead_settings' => 'array',
            'document_templates' => 'array',
            'membership_requests_enabled' => 'boolean',
            'member_pause_requests_enabled' => 'boolean',
            'membership_application_fields' => 'array',
            'membership_payment_methods' => 'array',
            'membership_application_documents' => 'array',
            'membership_application_document_types' => 'array',
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
        if ($user->hasAnyRole(Roles::FULL_ACCESS)) {
            return $query;
        }

        if (ClubWorkspaceAccess::isScoped($user)) {
            return $query->linkedToUser($user);
        }

        return $query->where(function ($visible) use ($user) {
            $visible
                ->where(function ($public) {
                    $public->where('verification_status', 'verified')
                        ->where('is_listed', true);
                })
                ->orWhere(fn ($linked) => $linked->linkedToUser($user));
        });
    }

    public function scopeLinkedToUser($query, $user)
    {
        return $query->where(function ($query) use ($user) {
            $query->where('owner_id', $user->id)
                ->orWhereHas('users', fn ($userQuery) => $userQuery->where('users.id', $user->id))
                ->orWhereHas('teams.users', fn ($userQuery) => $userQuery->where('users.id', $user->id));
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
        $pivotColumns = [
            'role',
            'roles',
            'permission_overrides',
            'membership_status',
            'club_membership_type_id',
            'club_department_id',
            'family_group_key',
            'contribution_payer_user_id',
            'member_number',
            'contribution_amount',
            'contribution_interval',
            'payment_method',
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
            'membership_ended_at',
            'membership_notes',
        ];

        if (self::clubUserHasMemberCardDesign()) {
            $pivotColumns[] = 'member_card_design';
        }

        return $this->belongsToMany(User::class)
            ->using(ClubUser::class)
            ->withPivot($pivotColumns)
            ->withTimestamps();
    }

    private static function clubUserHasMemberCardDesign(): bool
    {
        return self::$clubUserHasMemberCardDesign ??= Schema::hasColumn('club_user', 'member_card_design');
    }

    public function competitions()
    {
        return $this->hasMany(Competition::class);
    }

    public function roleDefinitions()
    {
        return $this->hasMany(ClubRoleDefinition::class);
    }

    public function volunteerProfiles()
    {
        return $this->hasMany(ClubVolunteerProfile::class);
    }

    public function masterDataChangeRequests()
    {
        return $this->hasMany(ClubMasterDataChangeRequest::class);
    }

    public function externalMembers()
    {
        return $this->hasMany(ClubExternalMember::class);
    }

    public function memberTimelineEntries()
    {
        return $this->hasMany(ClubMemberTimelineEntry::class);
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    public function departments()
    {
        return $this->hasMany(ClubDepartment::class);
    }

    public function locations()
    {
        return $this->hasMany(ClubLocation::class);
    }

    public function trainingGroups()
    {
        return $this->hasMany(ClubTrainingGroup::class);
    }

    public function governanceBodies()
    {
        return $this->hasMany(ClubGovernanceBody::class);
    }

    public function governanceAssignments()
    {
        return $this->hasMany(ClubGovernanceAssignment::class);
    }

    public function governanceMeetings()
    {
        return $this->hasMany(ClubGovernanceMeeting::class);
    }

    public function yearPeriods()
    {
        return $this->hasMany(ClubYearPeriod::class);
    }

    public function policyDocuments()
    {
        return $this->hasMany(ClubPolicyDocument::class);
    }

    public function customFieldDefinitions()
    {
        return $this->hasMany(ClubCustomFieldDefinition::class);
    }

    public function categories()
    {
        return $this->hasMany(ClubCategory::class);
    }

    public function numberRanges()
    {
        return $this->hasMany(ClubNumberRange::class);
    }

    public function numberAllocations()
    {
        return $this->hasMany(ClubNumberAllocation::class);
    }

    public function numberRangeDefaults()
    {
        return $this->hasMany(ClubNumberRangeDefault::class);
    }

    public function customFieldValues()
    {
        return $this->hasMany(ClubCustomFieldValue::class);
    }

    public function categoryAssignments()
    {
        return $this->hasMany(ClubCategoryAssignment::class);
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

    public function membershipProspects()
    {
        return $this->hasMany(ClubMembershipProspect::class);
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

    public function financeEntries()
    {
        return $this->hasMany(ClubFinanceEntry::class);
    }

    public function inventoryItems()
    {
        return $this->hasMany(ClubInventoryItem::class);
    }

    public function inventoryLoans()
    {
        return $this->hasMany(ClubInventoryLoan::class);
    }

    public function inventoryMaintenanceRecords()
    {
        return $this->hasMany(ClubInventoryMaintenanceRecord::class);
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
        $subscription = $this->currentSubscription;

        return $subscription?->grantsAccess()
            ? $subscription->plan
            : SubscriptionPlan::free();
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
