<?php

namespace App\Models;

use App\Models\Concerns\CleansClubMetadata;
use App\Notifications\MyCustomResetPassword;
use App\Notifications\VerifyEmailNotification;
use App\Support\MinorSafety;
use App\Support\SupportedLocale;
use App\Support\UploadStorage;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\Features;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasLocalePreference, MustVerifyEmailContract
{
    use CleansClubMetadata;
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use HasRoles;
    use Notifiable;
    use TwoFactorAuthenticatable;

    private const CLUB_ONLY_ROLES = ['club_owner', 'club_admin', 'club_manager', 'academy_manager'];

    private const PLATFORM_FINANCE_PERMISSIONS = ['billing.manage', 'finance.view', 'finance.edit', 'subscriptions.manage'];

    private static ?bool $clubUserHasMemberCardDesign = null;

    protected function hasPermissionViaRole(PermissionContract $permission): bool
    {
        // Legacy club roles carry platform finance permissions in existing databases.
        $roles = in_array($permission->name, self::PLATFORM_FINANCE_PERMISSIONS, true)
            ? $permission->roles->reject(fn ($role) => in_array($role->name, self::CLUB_ONLY_ROLES, true))
            : $permission->roles;

        return $this->hasRole($roles);
    }

    public function getPermissionsViaRoles(): Collection
    {
        return $this->loadMissing('roles', 'roles.permissions')->roles
            ->flatMap(fn ($role) => in_array($role->name, self::CLUB_ONLY_ROLES, true)
                ? $role->permissions->reject(fn ($permission) => in_array($permission->name, self::PLATFORM_FINANCE_PERMISSIONS, true))
                : $role->permissions)
            ->sort()->values();
    }

    public function clubMetadataSubjectType(): string
    {
        return 'member';
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'theme',
        'country',
        'athlete_license_number',
        'athlete_license_valid_until',
        'last_seen_at',
        'last_login_at',
        'privacy_status',
        'inactivity_first_warning_sent_at',
        'inactivity_second_warning_sent_at',
        'deletion_scheduled_at',
        'anonymized_at',
        'street',
        'house_number',
        'postal_code',
        'city',
        'state',
        'event_radius_km',
        'event_default_sport_ids',
        'event_default_filters',
        'dashboard_widget_keys',
        'dashboard_quick_action_keys',
        'enabled_navigation_modules',
        'status',
        'account_status',
        'suspended_until',
        'suspension_reason',
        'birth_date',
        'gender',
        'guardian_email',
        'guardian_user_id',
        'guardian_consent_requested_at',
        'guardian_consent_at',
        'guardian_consent_rejected_at',
        'guardian_consent_revoked_at',
        'guardian_consent_revoked_by_email',
        'guardian_consent_token',
        'guardian_consent_version',
        'default_post_visibility',
        'profile_visibility',
        'direct_message_privacy',
        'friend_request_privacy',
        'notification_channels',
        'notification_quiet_time',
        'ads_personalization_consent',
        'ads_measurement_consent',
        'product_analytics_consent',
        'product_analytics_consented_at',
        'product_analytics_consent_version',
        'bio',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
        'profile_photo_thumb',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'athlete_license_valid_until' => 'date',
            'last_seen_at' => 'datetime',
            'last_login_at' => 'datetime',
            'inactivity_first_warning_sent_at' => 'datetime',
            'inactivity_second_warning_sent_at' => 'datetime',
            'deletion_scheduled_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'suspended_until' => 'datetime',
            'gamification_last_active_on' => 'date',
            'guardian_consent_requested_at' => 'datetime',
            'guardian_consent_at' => 'datetime',
            'guardian_consent_rejected_at' => 'datetime',
            'guardian_consent_revoked_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'event_radius_km' => 'integer',
            'event_default_sport_ids' => 'array',
            'event_default_filters' => 'array',
            'dashboard_widget_keys' => 'array',
            'dashboard_quick_action_keys' => 'array',
            'enabled_navigation_modules' => 'array',
            'notification_channels' => 'array',
            'ads_personalization_consent' => 'boolean',
            'ads_measurement_consent' => 'boolean',
            'product_analytics_consent' => 'boolean',
            'product_analytics_consented_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function guardian()
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }

    public function supervisedChildren()
    {
        return $this->hasMany(User::class, 'guardian_user_id');
    }

    public function sendEmailVerificationNotification(): void
    {
        try {
            $this->notify(new VerifyEmailNotification);
        } catch (\Throwable $exception) {
            Log::warning('Email verification notification could not be sent.', [
                'user_id' => $this->id,
                'email' => $this->email,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    public function preferredLocale(): string
    {
        return SupportedLocale::normalize($this->language) ?? SupportedLocale::DEFAULT;
    }

    public function clubs()
    {
        $pivotColumns = [
            'role',
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
            'membership_end_notified_at',
            'membership_ended_at',
            'membership_notes',
        ];

        if (self::clubUserHasMemberCardDesign()) {
            $pivotColumns[] = 'member_card_design';
        }

        return $this->belongsToMany(Club::class)
            ->withPivot($pivotColumns);
    }

    private static function clubUserHasMemberCardDesign(): bool
    {
        return self::$clubUserHasMemberCardDesign ??= Schema::hasColumn('club_user', 'member_card_design');
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class)->withPivot('role');
    }

    public function teamInvitations()
    {
        return $this->hasMany(TeamInvitation::class, 'recipient_id');
    }

    public function sportProfiles()
    {
        return $this->hasMany(UserSport::class);
    }

    public function sportSkills()
    {
        return $this->hasMany(UserSportSkill::class);
    }

    public function clubVolunteerProfiles()
    {
        return $this->hasMany(ClubVolunteerProfile::class);
    }

    public function skillEndorsementsGiven()
    {
        return $this->hasMany(SkillEndorsement::class, 'endorser_id');
    }

    public function recommendationsReceived()
    {
        return $this->hasMany(ProfileRecommendation::class, 'profile_user_id');
    }

    public function recommendationsGiven()
    {
        return $this->hasMany(ProfileRecommendation::class, 'author_id');
    }

    public function xpEvents()
    {
        return $this->hasMany(GamificationXpEvent::class);
    }

    public function badgeAwards()
    {
        return $this->hasMany(UserBadge::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function savedViews()
    {
        return $this->hasMany(SavedView::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function outfitStyleProfile()
    {
        return $this->hasOne(OutfitStyleProfile::class);
    }

    public function outfitSubscriptions()
    {
        return $this->hasMany(OutfitSubscription::class);
    }

    public function commerceShippingAddresses()
    {
        return $this->hasMany(CommerceShippingAddress::class);
    }

    public function marketplaceSellerApplications()
    {
        return $this->hasMany(MarketplaceSellerApplication::class);
    }

    public function marketplaceProviderProfile()
    {
        return $this->hasOne(MarketplaceProviderProfile::class);
    }

    public function payoutProfile()
    {
        return $this->hasOne(PayoutProfile::class);
    }

    public function approvedSellerApplications()
    {
        return $this->marketplaceSellerApplications()->where('status', 'approved');
    }

    public function subscriptionInvoices()
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function currentSubscription()
    {
        return $this->hasOne(UserSubscription::class)->latestOfMany();
    }

    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function connectedSportAccounts()
    {
        return $this->hasMany(ConnectedSportAccount::class);
    }

    public function connectedSportActivities()
    {
        return $this->hasMany(ConnectedSportActivity::class);
    }

    public function sportRoutes()
    {
        return $this->hasMany(SportRoute::class);
    }

    public function sportRouteTracks()
    {
        return $this->hasMany(SportRouteTrack::class);
    }

    public function sportPlaces()
    {
        return $this->hasMany(SportPlace::class);
    }

    public function createdTrainingPlans()
    {
        return $this->hasMany(TrainingPlan::class, 'created_by');
    }

    public function trainingLogs()
    {
        return $this->hasMany(TrainingLog::class);
    }

    public function createdTrainingLogs()
    {
        return $this->hasMany(TrainingLog::class, 'created_by');
    }

    public function trainingPlanAssignments()
    {
        return $this->hasMany(TrainingPlanAssignment::class);
    }

    public function folders()
    {
        return $this->hasMany(Folder::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_users')
            ->withPivot('joined_at', 'muted_until', 'cleared_at', 'cleared_message_id');
    }

    public function receivedConversationInvitations()
    {
        return $this->hasMany(ConversationInvitation::class, 'recipient_id');
    }

    public function rides()
    {
        return $this->hasMany(Ride::class, 'driver_id');
    }

    public function appNotifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function roleApplications()
    {
        return $this->hasMany(UserRoleApplication::class);
    }

    public function sentFriendInvitations()
    {
        return $this->hasMany(FriendInvitation::class, 'sender_id');
    }

    public function receivedFriendInvitations()
    {
        return $this->hasMany(FriendInvitation::class, 'recipient_id');
    }

    public function friendships()
    {
        return $this->hasMany(Friendship::class);
    }

    public function receivedFriendships()
    {
        return $this->hasMany(Friendship::class, 'friend_id');
    }

    public function following()
    {
        return $this->hasMany(Follow::class, 'follower_id');
    }

    public function followers()
    {
        return $this->hasMany(Follow::class, 'followed_id');
    }

    public function blockedUsers()
    {
        return $this->hasMany(UserBlock::class);
    }

    public function blockedByUsers()
    {
        return $this->hasMany(UserBlock::class, 'blocked_user_id');
    }

    public function hasBlocked(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->blockedUsers()
            ->where('blocked_user_id', $user->id)
            ->exists();
    }

    public function isBlockedBy(?User $user): bool
    {
        return $user?->hasBlocked($this) ?? false;
    }

    public function isFriendsWith(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->friendships()
            ->where('friend_id', $user->id)
            ->exists();
    }

    public function allowsDirectMessagesFrom(?User $user): bool
    {
        if (! $user || $this->hasBlocked($user) || $this->isBlockedBy($user)) {
            return false;
        }

        if (! MinorSafety::canDirectMessage($user, $this)) {
            return false;
        }

        return ($this->direct_message_privacy ?? 'everyone') === 'everyone'
            || $this->isFriendsWith($user);
    }

    public function allowsFriendRequestsFrom(?User $user): bool
    {
        if (! $user || $this->hasBlocked($user) || $this->isBlockedBy($user)) {
            return false;
        }

        return ($this->friend_request_privacy ?? 'everyone') === 'everyone'
            || $this->isFriendsWith($user);
    }

    public function isFollowedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->followers()
            ->where('follower_id', $user->id)
            ->exists();
    }

    public function isProfileVisibleTo(?User $user): bool
    {
        if (! MinorSafety::canViewMinorProfile($this, $user)) {
            return false;
        }

        if ($user?->id === $this->id || $user?->can('user.manage')) {
            return true;
        }

        if ($this->hasBlocked($user) || $this->isBlockedBy($user)) {
            return false;
        }

        return match ($this->profile_visibility ?? 'public') {
            'public' => true,
            'friends' => $this->isFriendsWith($user),
            default => false,
        };
    }

    public function deleteProfilePhoto()
    {
        if (! Features::managesProfilePhotos() || is_null($this->profile_photo_path)) {
            return;
        }

        $this->deleteProfilePhotoFiles($this->profile_photo_path);

        $this->forceFill([
            'profile_photo_path' => null,
        ])->save();
    }

    public function deleteProfilePhotoFiles(?string $path = null): void
    {
        if (! $path) {
            return;
        }

        $paths = [$path];

        if (str_ends_with(strtolower($path), '.jpg')) {
            $paths[] = substr($path, 0, -4).'_thumb.jpg';
        }

        Storage::disk($this->profilePhotoDisk())->delete(array_unique($paths));
    }

    public function getProfilePhotoUrlAttribute()
    {
        if ($this->profile_photo_path) {
            return $this->profilePhotoUrlFor($this->profile_photo_path);
        }

        return null;
    }

    public function getProfilePhotoThumbAttribute()
    {
        if (! $this->profile_photo_path) {
            return null;
        }

        if (str_ends_with(strtolower($this->profile_photo_path), '.jpg')) {
            $thumbPath = substr($this->profile_photo_path, 0, -4).'_thumb.jpg';

            try {
                if (Storage::disk($this->profilePhotoDisk())->exists($thumbPath)) {
                    return $this->profilePhotoUrlFor($thumbPath);
                }
            } catch (\Throwable) {
                // A storage outage must not make user and chat payloads fail.
            }
        }

        return $this->profile_photo_url;
    }

    private function profilePhotoUrlFor(string $path): string
    {
        return UploadStorage::url($path).'?v='.$this->profilePhotoVersion();
    }

    private function profilePhotoDisk(): string
    {
        return config('jetstream.profile_photo_disk', 'public');
    }

    private function profilePhotoVersion(): string
    {
        return (string) ($this->updated_at?->timestamp ?? time());
    }

    public function sendPasswordResetNotification($token)
    {
        // Erstelle zuerst die Instanz der Notification
        $notification = new MyCustomResetPassword($token);

        // Setze die Sprache auf der Notification-Instanz
        $notification->locale('en');

        // Sende die fertig konfigurierte Notification
        $this->notify($notification);
    }
}
