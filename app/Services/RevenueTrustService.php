<?php

namespace App\Services;

use App\Models\MarketplaceSellerApplication;
use App\Models\PayoutProfile;
use App\Models\Sponsor;
use App\Models\User;
use App\Support\MarketplaceSellerReadiness;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevenueTrustService
{
    public const CONTRACT_VERSION = '2026-08-08';

    public function reviewSellerApplication(
        MarketplaceSellerApplication $application,
        User $reviewer,
        string $status,
        ?string $note = null,
    ): MarketplaceSellerApplication {
        $readiness = MarketplaceSellerReadiness::forApplication($application);

        if ($status === 'approved' && $application->verification_version === self::CONTRACT_VERSION && ! $readiness['can_approve']) {
            throw ValidationException::withMessages([
                'status' => __('commerce.validation.seller_readiness_incomplete', [
                    'count' => count($readiness['blocks']),
                ]),
            ]);
        }

        return DB::transaction(function () use ($application, $reviewer, $status, $note, $readiness) {
            $application->forceFill([
                'status' => $status,
                'review_note' => $note,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'verification_snapshot' => $status === 'approved' ? $readiness : null,
            ])->save();

            return $application->fresh(['user', 'reviewer']);
        });
    }

    public function reviewPayoutProfile(PayoutProfile $profile, User $reviewer, string $status, ?string $note = null): PayoutProfile
    {
        $readiness = $this->payoutReadiness($profile);

        if ($status === 'approved' && $profile->terms_version === self::CONTRACT_VERSION && ! $readiness['can_approve']) {
            throw ValidationException::withMessages([
                'status' => __('commerce.validation.payout_readiness_incomplete', [
                    'count' => count($readiness['blocks']),
                ]),
            ]);
        }

        $profile->forceFill([
            'status' => $status,
            'notes' => $note,
            'verified_by' => $status === 'approved' ? $reviewer->id : null,
            'verified_at' => $status === 'approved' ? now() : null,
            'rejection_reason' => $status === 'blocked' ? $note : null,
        ])->save();

        return $profile->fresh('user');
    }

    public function payoutReadiness(?PayoutProfile $profile): array
    {
        $checks = [
            $this->check('payout_method', filled($profile?->iban) || filled($profile?->paypal_email)),
            $this->check('payout_account_holder', filled($profile?->account_holder)),
            $this->check('payout_country', filled($profile?->country_code)),
            $this->check('payout_tax_status', filled($profile?->tax_status)),
            $this->check('payout_beneficial_owner', (bool) $profile?->beneficial_owner_confirmed),
            $this->check('payout_terms', $profile?->terms_version === self::CONTRACT_VERSION && $profile?->terms_accepted_at !== null),
        ];
        $blocks = collect($checks)->where('done', false)->pluck('key')->values()->all();

        return [
            'version' => self::CONTRACT_VERSION,
            'can_approve' => $blocks === [],
            'blocks' => $blocks,
            'checklist' => $checks,
        ];
    }

    public function sponsorReadiness(Sponsor $sponsor): array
    {
        $rules = is_array($sponsor->accepted_rules) ? $sponsor->accepted_rules : [];
        $checks = [
            $this->check('sponsor_name', filled($sponsor->name)),
            $this->check('sponsor_legal_name', filled($sponsor->legal_name)),
            $this->check('sponsor_country', filled($sponsor->country_code)),
            $this->check('sponsor_contact', filled($sponsor->email)),
            $this->check('sponsor_website', filled($sponsor->website)),
            $this->check('sponsor_legal_accuracy', ($rules['legal_accuracy'] ?? false) === true),
            $this->check('sponsor_data_privacy', ($rules['data_privacy'] ?? false) === true),
        ];
        $blocks = collect($checks)->where('done', false)->pluck('key')->values()->all();

        return [
            'version' => self::CONTRACT_VERSION,
            'can_approve' => $blocks === [],
            'blocks' => $blocks,
            'checklist' => $checks,
        ];
    }

    public function ensureSponsorApprovable(Sponsor $sponsor): void
    {
        if ($sponsor->verification_version !== self::CONTRACT_VERSION) {
            return;
        }

        $readiness = $this->sponsorReadiness($sponsor);
        if (! $readiness['can_approve']) {
            throw ValidationException::withMessages([
                'verification_status' => __('sponsor.validation.verification_incomplete', [
                    'count' => count($readiness['blocks']),
                ]),
            ]);
        }
    }

    private function check(string $key, bool $done): array
    {
        return ['key' => $key, 'done' => $done];
    }
}
