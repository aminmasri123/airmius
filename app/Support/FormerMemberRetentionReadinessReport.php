<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class FormerMemberRetentionReadinessReport
{
    public const CONTRACT = 'former-member-retention-readiness.v1';

    public const DATA_CATEGORIES = [
        'membership_history',
        'identity_contact',
        'billing_accounting',
        'payment_mandate',
        'free_text_notes',
    ];

    public function make(bool $withData = false): array
    {
        $checks = [
            $this->repositoryCheck(),
            $this->approvalCheck('approval.domain', 'Domain approval for club-specific retention and deletion rules is still required.'),
            $this->approvalCheck('approval.legal', 'Legal review must approve retention periods before any productive deletion path exists.'),
        ];
        $inventory = null;

        if ($withData) {
            [$dataChecks, $inventory] = $this->dataChecks();
            array_push($checks, ...$dataChecks);
        } else {
            $checks[] = $this->check('database.runtime', 'pending', 'Runtime data was not inspected; report stays repository-only.');
        }

        $automatedChecksPassed = collect($checks)
            ->reject(fn (array $check) => str_starts_with($check['id'], 'approval.')
                || ($check['id'] === 'database.runtime' && $check['status'] === 'pending'))
            ->every(fn (array $check) => $check['status'] !== 'fail');

        return [
            'contract' => self::CONTRACT,
            'generated_at' => now()->toIso8601String(),
            'mode' => $withData ? 'runtime-read-only' : 'repository-only',
            'read_only' => true,
            'productive_erasure_supported' => false,
            'requires_domain_approval' => true,
            'requires_legal_approval' => true,
            'automated_checks_passed' => $automatedChecksPassed,
            'decision' => 'no-go',
            'data_categories' => self::DATA_CATEGORIES,
            'retention_rules' => $this->rules(),
            'inventory' => $inventory,
            'checks' => $checks,
        ];
    }

    private function repositoryCheck(): array
    {
        $paths = [
            'app/Console/Commands/ProcessMembershipTerminations.php',
            'app/Services/ClubMembershipLifecycleService.php',
            'app/Services/UserDataErasureService.php',
            'app/Services/UserPrivacyRetentionService.php',
        ];
        $missing = array_values(array_filter($paths, fn (string $path) => ! is_file(base_path($path))));

        return $this->check(
            'repository.contract',
            $missing === [] ? 'pass' : 'fail',
            $missing === []
                ? 'Termination, data-erasure and privacy-retention services are present for a read-only former-member rule contract.'
                : count($missing).' required lifecycle or privacy artifacts are missing.',
        );
    }

    private function dataChecks(): array
    {
        $required = [
            'club_user' => [
                'club_id',
                'user_id',
                'membership_status',
                'member_number',
                'contribution_amount',
                'sepa_iban',
                'sepa_mandate_reference',
                'joined_on',
                'membership_ends_on',
                'membership_ended_at',
                'membership_notes',
            ],
            'club_external_members' => [
                'id',
                'club_id',
                'membership_status',
                'name',
                'email',
                'phone',
                'street',
                'city',
                'member_number',
                'contribution_amount',
                'sepa_iban',
                'sepa_mandate_reference',
                'joined_on',
                'membership_ends_on',
                'membership_ended_at',
                'membership_notes',
            ],
            'users' => ['id', 'name', 'email'],
            'invoices' => ['id', 'club_id', 'user_id', 'status'],
        ];

        $missing = [];
        try {
            foreach ($required as $table => $columns) {
                if (! Schema::hasTable($table)) {
                    $missing[] = $table;

                    continue;
                }
                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) {
                        $missing[] = $table.'.'.$column;
                    }
                }
            }
        } catch (Throwable) {
            return [[$this->check('database.runtime', 'fail', 'The configured database is unavailable; no former-member data was inspected.')], null];
        }

        if ($missing !== []) {
            return [[$this->check('database.schema', 'fail', count($missing).' required tables or columns are missing.')], null];
        }

        try {
            $linkedCounts = $this->linkedCategoryCounts();
            $externalCounts = $this->externalCategoryCounts();
            $linkedFormer = DB::table('club_user')->where('membership_status', 'former')->count();
            $externalFormer = DB::table('club_external_members')->where('membership_status', 'former')->count();
        } catch (Throwable) {
            return [[
                $this->check('database.schema', 'pass', 'All former-member and privacy tables required for the read-only contract are present.'),
                $this->check('database.runtime', 'fail', 'The read-only former-member inventory could not complete.'),
            ], null];
        }

        $inventory = [
            'linked_former_members' => $linkedFormer,
            'external_former_members' => $externalFormer,
            'category_counts' => collect(self::DATA_CATEGORIES)
                ->mapWithKeys(fn (string $category) => [
                    $category => [
                        'linked' => (int) ($linkedCounts[$category] ?? 0),
                        'external' => (int) ($externalCounts[$category] ?? 0),
                    ],
                ])
                ->all(),
        ];

        return [[
            $this->check('database.schema', 'pass', 'All former-member and privacy tables required for the read-only contract are present.'),
            $this->check('database.runtime', 'pass', 'Former-member retention inventory completed without changing rows.'),
            $this->check('database.data_minimization', 'pass', 'Inventory contains category counts only and no member names, emails, notes, IBANs or record identifiers.'),
            $this->check('database.approval_gate', 'pending', 'All categories remain blocked behind domain and legal approval before productive deletion can be implemented.'),
        ], $inventory];
    }

    private function linkedCategoryCounts(): array
    {
        $base = DB::table('club_user')
            ->join('users', 'users.id', '=', 'club_user.user_id')
            ->where('club_user.membership_status', 'former');

        return [
            'membership_history' => (clone $base)
                ->where(fn ($query) => $query
                    ->whereNotNull('club_user.joined_on')
                    ->orWhereNotNull('club_user.membership_ends_on')
                    ->orWhereNotNull('club_user.membership_ended_at'))
                ->count(),
            'identity_contact' => (clone $base)
                ->where(fn ($query) => $query
                    ->whereNotNull('users.name')
                    ->orWhereNotNull('users.email'))
                ->count(),
            'billing_accounting' => (clone $base)
                ->where(fn ($query) => $query
                    ->whereNotNull('club_user.member_number')
                    ->orWhereNotNull('club_user.contribution_amount')
                    ->orWhereExists(fn ($invoice) => $invoice
                        ->selectRaw('1')
                        ->from('invoices')
                        ->whereColumn('invoices.club_id', 'club_user.club_id')
                        ->whereColumn('invoices.user_id', 'club_user.user_id')))
                ->count(),
            'payment_mandate' => (clone $base)
                ->where(fn ($query) => $query
                    ->whereNotNull('club_user.sepa_iban')
                    ->orWhereNotNull('club_user.sepa_mandate_reference'))
                ->count(),
            'free_text_notes' => (clone $base)
                ->whereNotNull('club_user.membership_notes')
                ->count(),
        ];
    }

    private function externalCategoryCounts(): array
    {
        $base = DB::table('club_external_members')->where('membership_status', 'former');

        return [
            'membership_history' => (clone $base)
                ->where(fn ($query) => $query
                    ->whereNotNull('joined_on')
                    ->orWhereNotNull('membership_ends_on')
                    ->orWhereNotNull('membership_ended_at'))
                ->count(),
            'identity_contact' => (clone $base)
                ->where(fn ($query) => $query
                    ->whereNotNull('name')
                    ->orWhereNotNull('email')
                    ->orWhereNotNull('phone')
                    ->orWhereNotNull('street')
                    ->orWhereNotNull('city'))
                ->count(),
            'billing_accounting' => (clone $base)
                ->where(fn ($query) => $query
                    ->whereNotNull('member_number')
                    ->orWhereNotNull('contribution_amount'))
                ->count(),
            'payment_mandate' => (clone $base)
                ->where(fn ($query) => $query
                    ->whereNotNull('sepa_iban')
                    ->orWhereNotNull('sepa_mandate_reference'))
                ->count(),
            'free_text_notes' => (clone $base)
                ->whereNotNull('membership_notes')
                ->count(),
        ];
    }

    private function rules(): array
    {
        return [
            'membership_history' => 'Retain a minimal membership timeline until approved club, legal and statutory rules define a shorter handling path.',
            'identity_contact' => 'Review for minimization after membership end; no automatic erasure before domain and legal approval.',
            'billing_accounting' => 'Retain records that may be required for contribution, invoice, tax or audit duties.',
            'payment_mandate' => 'Treat payment mandate data as sensitive and blocked for productive deletion until finance and legal policy is approved.',
            'free_text_notes' => 'Require manual review because notes can contain sensitive free text.',
        ];
    }

    private function approvalCheck(string $id, string $detail): array
    {
        return $this->check($id, 'pending', $detail);
    }

    private function check(string $id, string $status, string $detail): array
    {
        return compact('id', 'status', 'detail');
    }
}
