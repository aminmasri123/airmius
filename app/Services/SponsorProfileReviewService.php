<?php

namespace App\Services;

use App\Models\Sponsor;
use App\Models\SponsorActivityLog;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SponsorProfileReviewService
{
    public const FIELDS = [
        'name', 'legal_name', 'country_code', 'region', 'city', 'registration_number', 'vat_id',
        'contact_name', 'email', 'website', 'logo', 'logo_light', 'logo_dark',
    ];

    public function __construct(
        private RevenueTrustService $revenueTrust,
        private SponsorOrganizationService $organizations,
        private SponsorProfileReviewNotifier $notifier,
    ) {}

    public function snapshot(Sponsor $sponsor): array
    {
        return collect(self::FIELDS)->mapWithKeys(
            fn (string $field) => [$field => $sponsor->getAttribute($field)]
        )->all();
    }

    public function changes(array $before, Sponsor $after): array
    {
        $current = $this->snapshot($after);

        return collect(self::FIELDS)->filter(
            fn (string $field) => (string) ($before[$field] ?? '') !== (string) ($current[$field] ?? '')
        )->mapWithKeys(fn (string $field) => [$field => [
            'before' => $before[$field] ?? null,
            'after' => $current[$field] ?? null,
        ]])->all();
    }

    public function pendingChanges(Sponsor $sponsor): array
    {
        if ($sponsor->verification_status !== 'pending_review' || ! $sponsor->verification_requested_at) {
            return [];
        }

        return SponsorActivityLog::query()
            ->where('sponsor_id', $sponsor->id)
            ->whereIn('action', ['profile.updated', 'profile.logo_updated'])
            ->where('created_at', '>=', $sponsor->verification_requested_at)
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->flatMap(function (SponsorActivityLog $log) {
                $changes = $log->metadata['changes'] ?? [];

                return collect($changes)->map(fn ($value, $field) => [
                    'field' => $field,
                    'before' => $value['before'] ?? null,
                    'after' => $value['after'] ?? null,
                    'changed_at' => $log->created_at?->toIso8601String(),
                ]);
            })->values()->all();
    }

    public function decide(Sponsor $sponsor, User $reviewer, string $decision, ?string $note): Sponsor
    {
        if ($sponsor->verification_status !== 'pending_review') {
            throw ValidationException::withMessages(['decision' => 'Dieses Profil wartet nicht auf eine Prüfung.']);
        }
        if ((int) $sponsor->owner_user_id === (int) $reviewer->id) {
            throw ValidationException::withMessages(['decision' => 'Das eigene Sponsorprofil kann nicht selbst freigegeben werden.']);
        }
        if ($decision === 'rejected' && blank($note)) {
            throw ValidationException::withMessages(['note' => 'Bitte begründe die Ablehnung.']);
        }
        if ($decision === 'verified') {
            $this->revenueTrust->ensureSponsorApprovable($sponsor);
        }

        $sponsor->forceFill([
            'verification_status' => $decision,
            'verification_note' => $decision === 'rejected' ? $note : null,
            'verified_by' => $decision === 'verified' ? $reviewer->id : null,
            'verified_at' => $decision === 'verified' ? now() : null,
        ])->save();
        $this->organizations->audit($sponsor, $reviewer, 'profile.reviewed', [
            'decision' => $decision,
            'note' => $note,
        ]);
        $this->notifier->reviewDecided($sponsor);

        return $sponsor->fresh();
    }
}
