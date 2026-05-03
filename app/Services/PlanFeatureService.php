<?php

namespace App\Services;

use App\Models\Club;
use App\Models\File;
use Illuminate\Http\UploadedFile;

class PlanFeatureService
{
    private const PLAN_LEVELS = [
        'free' => 0,
        'starter' => 10,
        'club' => 20,
        'pro' => 30,
        'elite' => 40,
    ];

    private const FEATURE_MINIMUM_PLANS = [
        'external_members' => 'starter',
        'member_import' => 'starter',
        'member_invitations' => 'starter',
        'invoices' => 'starter',
        'payment_tracking' => 'starter',
        'payment_reminders' => 'club',
        'sponsors' => 'club',
        'advanced_roles' => 'club',
        'recurring_invoices' => 'pro',
        'sepa_export' => 'pro',
        'bank_reconciliation' => 'pro',
        'datev_export' => 'pro',
        'api' => 'elite',
    ];

    public function allows(Club $club, string $feature): bool
    {
        $minimumPlan = self::FEATURE_MINIMUM_PLANS[$feature] ?? null;

        if (! $minimumPlan) {
            return true;
        }

        return $this->planLevel($club) >= (self::PLAN_LEVELS[$minimumPlan] ?? 0);
    }

    public function ensureAllows(Club $club, string $feature, ?string $message = null): void
    {
        abort_unless(
            $this->allows($club, $feature),
            422,
            $message ?? $this->featureMessage($feature)
        );
    }

    public function canCreateTeam(Club $club): bool
    {
        $limit = $club->subscriptionPlan()?->team_limit;

        return $limit === null || $club->teams()->count() < $limit;
    }

    public function ensureCanCreateTeam(Club $club): void
    {
        abort_unless(
            $this->canCreateTeam($club),
            422,
            'Das Teamlimit des aktuellen Vereinsplans ist erreicht.'
        );
    }

    public function canStoreFile(Club $club, ?UploadedFile $file = null): bool
    {
        $storageGb = $club->subscriptionPlan()?->storage_gb;

        if (! $storageGb) {
            return true;
        }

        $usedBytes = (int) File::query()
            ->where('club_id', $club->id)
            ->sum('size');
        $nextBytes = $file?->getSize() ?? 0;
        $limitBytes = $storageGb * 1024 * 1024 * 1024;

        return ($usedBytes + $nextBytes) <= $limitBytes;
    }

    public function ensureCanStoreFile(Club $club, ?UploadedFile $file = null): void
    {
        abort_unless(
            $this->canStoreFile($club, $file),
            422,
            'Der Speicher des aktuellen Vereinsplans ist ausgeschöpft.'
        );
    }

    public function capabilities(Club $club): array
    {
        return [
            'external_members' => $this->allows($club, 'external_members'),
            'member_import' => $this->allows($club, 'member_import'),
            'member_invitations' => $this->allows($club, 'member_invitations'),
            'invoices' => $this->allows($club, 'invoices'),
            'payment_tracking' => $this->allows($club, 'payment_tracking'),
            'payment_reminders' => $this->allows($club, 'payment_reminders'),
            'sponsors' => $this->allows($club, 'sponsors'),
            'advanced_roles' => $this->allows($club, 'advanced_roles'),
            'recurring_invoices' => $this->allows($club, 'recurring_invoices'),
            'sepa_export' => $this->allows($club, 'sepa_export'),
            'bank_reconciliation' => $this->allows($club, 'bank_reconciliation'),
            'datev_export' => $this->allows($club, 'datev_export'),
            'api' => $this->allows($club, 'api'),
            'can_create_team' => $this->canCreateTeam($club),
            'can_store_files' => $this->canStoreFile($club),
        ];
    }

    private function planLevel(Club $club): int
    {
        $slug = $club->subscriptionPlan()?->slug ?? 'free';

        return self::PLAN_LEVELS[$slug] ?? 0;
    }

    private function featureMessage(string $feature): string
    {
        return match ($feature) {
            'external_members', 'member_import', 'member_invitations' => 'Diese Mitgliederfunktion ist ab Starter verfügbar.',
            'invoices', 'payment_tracking' => 'Rechnungen und Zahlungsverfolgung sind ab Starter verfügbar.',
            'payment_reminders' => 'Mahnungen sind ab dem Club-Plan verfügbar.',
            'sponsors' => 'Sponsorenverwaltung ist ab dem Club-Plan verfügbar.',
            'recurring_invoices' => 'Wiederkehrende Rechnungen sind ab Pro vorgesehen.',
            'sepa_export' => 'SEPA-Export ist ab Pro vorgesehen.',
            'bank_reconciliation' => 'Bankabgleich ist ab Pro vorgesehen.',
            'datev_export' => 'DATEV/SKR42-Export ist ab Pro vorgesehen.',
            'api' => 'API-Zugang ist im Elite-Plan vorgesehen.',
            default => 'Diese Funktion ist im aktuellen Plan nicht enthalten.',
        };
    }
}
