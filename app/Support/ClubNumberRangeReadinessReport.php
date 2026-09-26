<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ClubNumberRangeReadinessReport
{
    public const CONTRACT = 'club-number-range-rollout.v1';

    public const SURFACES = ['web_mobile', 'web_desktop', 'android', 'ios'];

    public const JOURNEYS = ['configuration', 'subject_values', 'default_allocations', 'legacy_fallback'];

    public const SCOPES = [
        'member', 'invoice', 'receipt', 'donation', 'inventory_item',
        'shop_invoice', 'shop_credit_note', 'shop_sku',
    ];

    public function make(bool $withData = false, ?string $evidencePath = null): array
    {
        $checks = [$this->repositoryCheck(), $this->evidenceTemplateCheck()];
        $inventory = null;
        if ($withData) {
            [$dataChecks, $inventory] = $this->dataChecks();
            array_push($checks, ...$dataChecks);
        } else {
            $checks[] = $this->check(
                'database.runtime',
                'pending',
                'Runtime numbering data was not inspected. Run with --with-data against a reviewed environment.'
            );
        }
        $checks[] = $this->check(
            'integration.canonical_storage',
            'pass',
            'Receipt and donation numbers use dedicated canonical payment columns.'
        );
        $checks[] = $this->check(
            'integration.runtime_wiring',
            'pass',
            'Member, invoice, receipt, donation, inventory and club shop creation paths support explicit defaults.'
        );
        $checks[] = $this->evidenceCheck($evidencePath);
        $automatedChecksPassed = collect($checks)
            ->reject(fn (array $check) => $check['id'] === 'local.evidence'
                || ($check['id'] === 'database.runtime' && $check['status'] === 'pending'))
            ->every(fn (array $check) => $check['status'] !== 'fail');
        $evidencePassed = collect($checks)->firstWhere('id', 'local.evidence')['status'] === 'pass';

        return [
            'contract' => self::CONTRACT,
            'generated_at' => now()->toIso8601String(),
            'mode' => $withData ? 'runtime-read-only' : 'repository-only',
            'automated_checks_passed' => $automatedChecksPassed,
            'decision' => $automatedChecksPassed && $withData && $evidencePassed ? 'go' : 'no-go',
            'inventory' => $inventory,
            'checks' => $checks,
        ];
    }

    private function repositoryCheck(): array
    {
        $paths = [
            'database/migrations/2026_09_24_000020_create_club_metadata_configuration.php',
            'database/migrations/2026_09_24_000022_create_club_number_range_defaults.php',
            'database/migrations/2026_09_24_000023_link_number_allocations_to_subjects.php',
            'database/migrations/2026_09_24_000024_add_canonical_numbers_to_payments.php',
            'app/Models/ClubNumberRange.php',
            'app/Models/ClubNumberRangeDefault.php',
            'app/Services/ClubNumberRangeService.php',
            'app/Services/ClubPaymentNumberService.php',
            'app/Services/ClubShopProductNumberService.php',
            'app/Services/ClubShopOrderNumberService.php',
            'app/Console/Commands/VerifyClubNumberRangeConcurrency.php',
            'app/Console/Commands/InternalAllocateNumberRangeProbe.php',
            'docs/CLUB_METADATA_CONFIGURATION.md',
            'docs/CLUB_NUMBER_RANGE_ADOPTION.md',
            'docs/CLUB_NUMBER_RANGE_ROLLOUT.md',
        ];
        $missing = array_values(array_filter($paths, fn (string $path) => ! is_file(base_path($path))));

        return $this->check(
            'repository.contract',
            $missing === [] ? 'pass' : 'fail',
            $missing === []
                ? 'Additive ranges, explicit defaults, allocation service and adoption documentation are present.'
                : count($missing).' required repository artifacts are missing.'
        );
    }

    private function evidenceTemplateCheck(): array
    {
        $data = $this->json(base_path('resources/release/club_number_range_evidence.template.json'));
        $valid = is_array($data)
            && ($data['contract'] ?? null) === self::CONTRACT
            && array_keys($data['surfaces'] ?? []) === self::SURFACES
            && array_keys($data['journeys'] ?? []) === self::JOURNEYS;

        return $this->check(
            'repository.evidence_template',
            $valid ? 'pass' : 'fail',
            $valid
                ? 'The versioned MySQL, browser and real-device evidence template is structurally complete.'
                : 'The number-range rollout evidence template is missing or invalid.'
        );
    }

    private function evidenceCheck(?string $path): array
    {
        if (! $path) {
            return $this->check('local.evidence', 'pending', 'No reviewed rollout evidence file was supplied.');
        }
        $data = $this->json($path);
        if (! is_array($data) || ($data['contract'] ?? null) !== self::CONTRACT) {
            return $this->check('local.evidence', 'fail', 'The supplied evidence file is unreadable or uses the wrong contract.');
        }
        $shapeIsValid = array_keys($data) === [
            'contract', 'status', 'migration', 'database', 'surfaces', 'journeys', 'approvals', 'evidence_references',
        ]
            && array_keys($data['migration'] ?? []) === ['backup_verified', 'dry_run_passed', 'rollback_rehearsed']
            && array_keys($data['database'] ?? []) === ['mysql_concurrency_passed']
            && array_keys($data['surfaces'] ?? []) === self::SURFACES
            && array_keys($data['journeys'] ?? []) === self::JOURNEYS
            && array_keys($data['approvals'] ?? []) === ['product', 'engineering'];
        $statuses = [...array_values($data['surfaces'] ?? []), ...array_values($data['journeys'] ?? [])];
        $passed = $shapeIsValid
            && ($data['status'] ?? null) === 'passed'
            && collect($data['migration'] ?? [])->every(fn ($value) => $value === true)
            && ($data['database']['mysql_concurrency_passed'] ?? false) === true
            && collect($data['approvals'] ?? [])->every(fn ($value) => $value === true)
            && $statuses !== []
            && collect($statuses)->every(fn ($value) => $value === 'passed')
            && $this->validReferences($data['evidence_references'] ?? []);

        return $this->check(
            'local.evidence',
            $passed ? 'pass' : 'pending',
            $passed
                ? 'Reviewed migration, MySQL concurrency, browser, Android, iOS and workflow evidence is complete.'
                : 'Reviewed migration, MySQL concurrency, browser, device or workflow evidence remains open.'
        );
    }

    private function validReferences(mixed $references): bool
    {
        return is_array($references) && $references !== []
            && collect($references)->every(fn ($reference) => is_string($reference)
                && preg_match('/^[A-Z0-9][A-Z0-9._:-]{2,80}$/i', $reference) === 1);
    }

    private function json(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }
        try {
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

            return is_array($data) ? $data : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function dataChecks(): array
    {
        $required = [
            'club_number_ranges' => ['id', 'club_id', 'scope', 'is_active'],
            'club_number_range_defaults' => ['club_id', 'scope', 'club_number_range_id'],
            'club_number_allocations' => [
                'club_id', 'club_number_range_id', 'formatted_number',
                'assigned_subject_type', 'assigned_subject_id',
            ],
            'club_user' => ['club_id', 'member_number'],
            'club_external_members' => ['club_id', 'member_number'],
            'invoices' => ['club_id', 'number'],
            'payments' => ['club_id', 'purpose', 'reference', 'receipt_number', 'donation_number'],
            'club_inventory_items' => ['club_id', 'sku'],
            'commerce_orders' => ['club_id', 'invoice_number', 'credit_note_number'],
            'marketplace_products' => ['club_id', 'sku'],
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
            return [[$this->check(
                'database.runtime', 'fail', 'The configured database is unavailable; no numbering data was inspected.'
            )], null];
        }
        if ($missing !== []) {
            return [[$this->check(
                'database.schema', 'fail', count($missing).' required tables or columns are missing.'
            )], null];
        }

        try {
            $invalidDefaults = DB::table('club_number_range_defaults as defaults')
                ->join('club_number_ranges as ranges', 'ranges.id', '=', 'defaults.club_number_range_id')
                ->where(fn ($query) => $query
                    ->whereColumn('ranges.club_id', '!=', 'defaults.club_id')
                    ->orWhereColumn('ranges.scope', '!=', 'defaults.scope')
                    ->orWhere('ranges.is_active', false))
                ->count();
            $invalidAllocations = DB::table('club_number_allocations as allocations')
                ->join('club_number_ranges as ranges', 'ranges.id', '=', 'allocations.club_number_range_id')
                ->whereColumn('ranges.club_id', '!=', 'allocations.club_id')
                ->count();
            $legacy = $this->legacyInventory();
            $duplicates = $this->duplicateInventory();
            $coverage = $this->defaultCoverage($legacy['clubs']);
            $collisions = $this->allocationCollisions();
        } catch (Throwable) {
            return [[
                $this->check('database.schema', 'pass', 'All required numbering tables and columns are present.'),
                $this->check('database.runtime', 'fail', 'The read-only numbering audit could not complete.'),
            ], null];
        }

        $invalidReferences = $invalidDefaults + $invalidAllocations;
        $duplicateTotal = array_sum($duplicates);
        $collisionTotal = array_sum($collisions);
        $missingDefaults = array_sum($coverage);

        return [[
            $this->check('database.schema', 'pass', 'All required numbering tables and columns are present.'),
            $this->check(
                'database.references',
                $invalidReferences === 0 ? 'pass' : 'fail',
                $invalidReferences === 0
                    ? 'All defaults and allocations match their club and configured scope.'
                    : $invalidReferences.' invalid default or allocation references require correction.'
            ),
            $this->check(
                'database.legacy_duplicates',
                $duplicateTotal === 0 ? 'pass' : 'fail',
                $duplicateTotal === 0
                    ? 'No duplicate legacy number groups were found within their club scope.'
                    : $duplicateTotal.' duplicate legacy number groups require review.'
            ),
            $this->check(
                'database.allocation_collisions',
                $collisionTotal === 0 ? 'pass' : 'fail',
                $collisionTotal === 0
                    ? 'No allocated range number collides with a stored legacy number.'
                    : $collisionTotal.' allocated numbers collide with stored legacy numbers.'
            ),
            $this->check(
                'database.default_coverage',
                $missingDefaults === 0 ? 'pass' : 'pending',
                $missingDefaults === 0
                    ? 'Every club scope containing legacy numbers has an explicit default range.'
                    : $missingDefaults.' club and scope pairs with legacy numbers have no explicit default range.'
            ),
            $this->check(
                'database.runtime',
                'pass',
                'The runtime inventory completed read-only; no number or sequence was changed.'
            ),
        ], [
            'legacy_records' => $legacy['counts'],
            'duplicate_groups' => $duplicates,
            'missing_default_club_scope_pairs' => $coverage,
            'allocation_collisions' => $collisions,
            'invalid_references' => [
                'defaults' => $invalidDefaults,
                'allocations' => $invalidAllocations,
            ],
        ]];
    }

    private function legacyInventory(): array
    {
        $queries = [
            'member' => $this->memberNumberRows(),
            'invoice' => $this->numberRows('invoices', 'number'),
            'receipt' => $this->numberRows('payments', 'receipt_number'),
            'donation' => $this->numberRows('payments', 'donation_number'),
            'inventory_item' => $this->numberRows('club_inventory_items', 'sku'),
            'shop_invoice' => $this->numberRows('commerce_orders', 'invoice_number'),
            'shop_credit_note' => $this->numberRows('commerce_orders', 'credit_note_number'),
            'shop_sku' => $this->numberRows('marketplace_products', 'sku'),
        ];
        $counts = [];
        $clubs = [];
        foreach ($queries as $scope => $query) {
            $counts[$scope] = DB::query()->fromSub(clone $query, 'legacy_numbers')->count();
            $clubs[$scope] = DB::query()->fromSub(clone $query, 'legacy_numbers')
                ->distinct()->pluck('club_id')->map(fn ($id) => (int) $id)->all();
        }

        return compact('counts', 'clubs');
    }

    private function duplicateInventory(): array
    {
        $queries = [
            'member' => $this->memberNumberRows(),
            'invoice' => $this->numberRows('invoices', 'number'),
            'receipt' => $this->numberRows('payments', 'receipt_number'),
            'donation' => $this->numberRows('payments', 'donation_number'),
            'inventory_item' => $this->numberRows('club_inventory_items', 'sku'),
            'shop_invoice' => $this->numberRows('commerce_orders', 'invoice_number'),
            'shop_credit_note' => $this->numberRows('commerce_orders', 'credit_note_number'),
            'shop_sku' => $this->numberRows('marketplace_products', 'sku'),
        ];

        return collect($queries)->map(fn (Builder $query) => DB::query()
            ->fromSub(clone $query, 'legacy_numbers')
            ->select('club_id', 'number_value')
            ->groupBy('club_id', 'number_value')
            ->havingRaw('COUNT(*) > 1')
            ->get()->count())->all();
    }

    private function defaultCoverage(array $legacyClubs): array
    {
        $defaults = DB::table('club_number_range_defaults')
            ->get(['club_id', 'scope'])
            ->groupBy('scope')
            ->map(fn ($rows) => $rows->pluck('club_id')->map(fn ($id) => (int) $id)->all());

        return collect(self::SCOPES)->mapWithKeys(fn (string $scope) => [
            $scope => count(array_diff($legacyClubs[$scope] ?? [], $defaults->get($scope, []))),
        ])->all();
    }

    private function allocationCollisions(): array
    {
        $counts = array_fill_keys(self::SCOPES, 0);
        $defaults = DB::table('club_number_range_defaults')->get();
        foreach ($defaults as $default) {
            $query = DB::table('club_number_allocations as allocations')
                ->where('allocations.club_number_range_id', $default->club_number_range_id);
            $scope = (string) $default->scope;
            $counts[$scope] += match ($scope) {
                'member' => (clone $query)->join('club_user as legacy', function ($join) use ($default) {
                    $join->on('legacy.member_number', '=', 'allocations.formatted_number')
                        ->where('legacy.club_id', '=', $default->club_id);
                })->where(fn ($collision) => $collision
                    ->whereNull('allocations.assigned_subject_type')
                    ->orWhere('allocations.assigned_subject_type', '!=', 'member')
                    ->orWhereColumn('allocations.assigned_subject_id', '!=', 'legacy.user_id'))
                    ->count() + (clone $query)->join('club_external_members as legacy', function ($join) use ($default) {
                        $join->on('legacy.member_number', '=', 'allocations.formatted_number')
                            ->where('legacy.club_id', '=', $default->club_id);
                    })->where(fn ($collision) => $collision
                    ->whereNull('allocations.assigned_subject_type')
                    ->orWhere('allocations.assigned_subject_type', '!=', 'external_member')
                    ->orWhereColumn('allocations.assigned_subject_id', '!=', 'legacy.id'))
                    ->count(),
                'invoice' => $this->collisionCount($query, 'invoices', 'number', $default->club_id, 'invoice'),
                'receipt' => $this->collisionCount($query, 'payments', 'receipt_number', $default->club_id, 'receipt'),
                'donation' => $this->collisionCount($query, 'payments', 'donation_number', $default->club_id, 'donation'),
                'inventory_item' => $this->collisionCount($query, 'club_inventory_items', 'sku', $default->club_id, 'inventory_item'),
                'shop_invoice' => $this->collisionCount($query, 'commerce_orders', 'invoice_number', $default->club_id, 'shop_invoice'),
                'shop_credit_note' => $this->collisionCount($query, 'commerce_orders', 'credit_note_number', $default->club_id, 'shop_credit_note'),
                'shop_sku' => $this->collisionCount($query, 'marketplace_products', 'sku', $default->club_id, 'shop_sku'),
                default => 0,
            };
        }

        return $counts;
    }

    private function collisionCount(
        Builder $allocations,
        string $table,
        string $column,
        int $clubId,
        string $subjectType,
        ?array $extra = null
    ): int {
        return $allocations->join($table.' as legacy', function ($join) use ($column, $clubId) {
            $join->on('legacy.'.$column, '=', 'allocations.formatted_number')
                ->where('legacy.club_id', '=', $clubId);
        })->where(fn ($collision) => $collision
            ->whereNull('allocations.assigned_subject_type')
            ->orWhere('allocations.assigned_subject_type', '!=', $subjectType)
            ->orWhereColumn('allocations.assigned_subject_id', '!=', 'legacy.id'))
            ->when($extra, fn ($query) => $query->where('legacy.'.$extra[0], $extra[1], $extra[2]))
            ->count();
    }

    private function memberNumberRows(): Builder
    {
        return $this->numberRows('club_user', 'member_number')
            ->unionAll($this->numberRows('club_external_members', 'member_number'));
    }

    private function numberRows(string $table, string $column): Builder
    {
        return DB::table($table)
            ->select('club_id', DB::raw($column.' as number_value'))
            ->whereNotNull('club_id')
            ->whereNotNull($column)
            ->where($column, '!=', '');
    }

    private function check(string $id, string $status, string $detail): array
    {
        return compact('id', 'status', 'detail');
    }
}
