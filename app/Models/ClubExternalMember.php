<?php

namespace App\Models;

use App\Models\Concerns\CleansClubMetadata;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class ClubExternalMember extends Model
{
    use CleansClubMetadata;
    use HasFactory;

    public const INVITATION_TTL_DAYS = 14;

    public function clubMetadataSubjectType(): string
    {
        return 'external_member';
    }

    protected $fillable = [
        'club_id',
        'created_by',
        'linked_user_id',
        'name',
        'email',
        'phone',
        'country',
        'street',
        'house_number',
        'postal_code',
        'city',
        'role',
        'membership_status',
        'club_membership_type_id',
        'family_group_key',
        'contribution_payer_user_id',
        'member_number',
        'athlete_license_number',
        'athlete_license_valid_until',
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
        'invitation_status',
        'invitation_token',
        'invited_at',
        'invitation_expires_at',
        'linked_at',
    ];

    protected function casts(): array
    {
        return [
            'contribution_amount' => 'decimal:2',
            'athlete_license_valid_until' => 'date',
            'contribution_next_invoice_on' => 'date',
            'contribution_last_invoice_at' => 'datetime',
            'sepa_mandate_signed_on' => 'date',
            'sepa_mandate_active' => 'boolean',
            'joined_on' => 'date',
            'membership_ends_on' => 'date',
            'membership_end_notified_at' => 'datetime',
            'membership_ended_at' => 'datetime',
            'invited_at' => 'datetime',
            'invitation_expires_at' => 'datetime',
            'linked_at' => 'datetime',
        ];
    }

    public function membershipType()
    {
        return $this->belongsTo(ClubMembershipType::class, 'club_membership_type_id');
    }

    public function issueInvitation(?CarbonInterface $expiresAt = null): self
    {
        $expiresAt = $expiresAt
            ? $expiresAt->copy()->endOfDay()
            : now()->addDays(self::INVITATION_TTL_DAYS)->endOfDay();

        $this->forceFill([
            'invitation_status' => 'pending',
            'invitation_token' => Str::random(64),
            'invited_at' => now(),
            'invitation_expires_at' => $expiresAt,
        ])->save();

        return $this;
    }

    public function invitationUrl(): ?string
    {
        if (blank($this->invitation_token) || ! Route::has('auth.club-member-invitations.accept')) {
            return null;
        }

        return route('auth.club-member-invitations.accept', $this->invitation_token);
    }

    public function invitationExpired(): bool
    {
        return (bool) $this->invitation_expires_at?->isPast();
    }

    public function markInvitationExpired(): self
    {
        $this->forceFill([
            'invitation_status' => 'expired',
        ])->save();

        return $this;
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function linkedUser()
    {
        return $this->belongsTo(User::class, 'linked_user_id');
    }

    public function contributionPayer()
    {
        return $this->belongsTo(User::class, 'contribution_payer_user_id');
    }
}
