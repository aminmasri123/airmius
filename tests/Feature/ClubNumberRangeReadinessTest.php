<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubNumberAllocation;
use App\Models\ClubNumberRange;
use App\Models\ClubNumberRangeDefault;
use App\Models\User;
use App\Support\ClubNumberRangeReadinessReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubNumberRangeReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_and_clean_runtime_audit_are_read_only_and_remain_no_go_until_wiring(): void
    {
        $club = Club::factory()->create(['owner_id' => User::factory()]);
        $range = $this->range($club, 'member', 'M-');
        ClubNumberRangeDefault::query()->create([
            'club_id' => $club->id, 'scope' => 'member',
            'club_number_range_id' => $range->id, 'assigned_by' => $club->owner_id,
        ]);

        $before = [
            'ranges' => ClubNumberRange::query()->count(),
            'defaults' => ClubNumberRangeDefault::query()->count(),
            'allocations' => ClubNumberAllocation::query()->count(),
        ];
        $report = app(ClubNumberRangeReadinessReport::class)->make(true);

        $this->assertTrue($report['automated_checks_passed']);
        $this->assertSame('no-go', $report['decision']);
        $this->assertSame('pass', collect($report['checks'])->firstWhere('id', 'database.runtime')['status']);
        $this->assertSame('pass', collect($report['checks'])->firstWhere('id', 'integration.canonical_storage')['status']);
        $this->assertSame($before, [
            'ranges' => ClubNumberRange::query()->count(),
            'defaults' => ClubNumberRangeDefault::query()->count(),
            'allocations' => ClubNumberAllocation::query()->count(),
        ]);

        $this->artisan('airmius:audit-club-number-ranges', ['--with-data' => true, '--strict' => true])
            ->assertFailed();
    }

    public function test_runtime_audit_reports_duplicate_groups_and_missing_defaults_without_values_or_ids(): void
    {
        $club = Club::factory()->create(['owner_id' => User::factory()]);
        foreach (['Sehr-Geheim-4711', 'Sehr-Geheim-4711'] as $index => $number) {
            ClubExternalMember::query()->create([
                'club_id' => $club->id,
                'created_by' => $club->owner_id,
                'name' => 'Mitglied '.($index + 1),
                'email' => 'member'.$index.'@example.test',
                'role' => 'member',
                'membership_status' => 'active',
                'member_number' => $number,
            ]);
        }

        $report = app(ClubNumberRangeReadinessReport::class)->make(true);
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertFalse($report['automated_checks_passed']);
        $this->assertSame(1, $report['inventory']['duplicate_groups']['member']);
        $this->assertSame(1, $report['inventory']['missing_default_club_scope_pairs']['member']);
        $this->assertStringNotContainsString('Sehr-Geheim-4711', $encoded);
        $this->assertStringNotContainsString('club_id', json_encode($report['inventory'], JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('number_value', json_encode($report['inventory'], JSON_THROW_ON_ERROR));
    }

    public function test_audit_detects_collision_between_default_range_allocation_and_legacy_number(): void
    {
        $club = Club::factory()->create(['owner_id' => User::factory()]);
        $range = $this->range($club, 'member', 'M-');
        ClubNumberRangeDefault::query()->create([
            'club_id' => $club->id, 'scope' => 'member',
            'club_number_range_id' => $range->id, 'assigned_by' => $club->owner_id,
        ]);
        ClubNumberAllocation::query()->create([
            'club_id' => $club->id, 'club_number_range_id' => $range->id,
            'period_key' => 0, 'sequence_number' => 1, 'formatted_number' => 'M-0001',
            'allocation_key' => '4ee5fd9a-903f-4fc0-b311-88d9833f5de1', 'allocated_by' => $club->owner_id,
        ]);
        ClubExternalMember::query()->create([
            'club_id' => $club->id, 'created_by' => $club->owner_id,
            'name' => 'Alt', 'email' => 'legacy@example.test', 'role' => 'member',
            'membership_status' => 'active', 'member_number' => 'M-0001',
        ]);

        $report = app(ClubNumberRangeReadinessReport::class)->make(true);

        $this->assertFalse($report['automated_checks_passed']);
        $this->assertSame(1, $report['inventory']['allocation_collisions']['member']);
        $this->assertSame('fail', collect($report['checks'])->firstWhere('id', 'database.allocation_collisions')['status']);
    }

    public function test_strict_audit_requires_complete_versioned_rollout_evidence(): void
    {
        $club = Club::factory()->create(['owner_id' => User::factory()]);
        $range = $this->range($club, 'member', 'M-');
        ClubNumberRangeDefault::query()->create([
            'club_id' => $club->id, 'scope' => 'member',
            'club_number_range_id' => $range->id, 'assigned_by' => $club->owner_id,
        ]);
        $path = tempnam(sys_get_temp_dir(), 'number-range-evidence-');
        file_put_contents($path, json_encode([
            'contract' => ClubNumberRangeReadinessReport::CONTRACT,
            'status' => 'passed',
            'migration' => [
                'backup_verified' => true,
                'dry_run_passed' => true,
                'rollback_rehearsed' => true,
            ],
            'database' => ['mysql_concurrency_passed' => true],
            'surfaces' => array_fill_keys(ClubNumberRangeReadinessReport::SURFACES, 'passed'),
            'journeys' => array_fill_keys(ClubNumberRangeReadinessReport::JOURNEYS, 'passed'),
            'approvals' => ['product' => true, 'engineering' => true],
            'evidence_references' => ['QA-2026-09-24', 'MYSQL-CONCURRENCY-01'],
        ], JSON_THROW_ON_ERROR));

        try {
            $report = app(ClubNumberRangeReadinessReport::class)->make(true, $path);
            $this->assertSame('go', $report['decision']);
            $this->assertSame('pass', collect($report['checks'])->firstWhere('id', 'local.evidence')['status']);
            $this->artisan('airmius:audit-club-number-ranges', [
                '--with-data' => true,
                '--evidence' => $path,
                '--strict' => true,
            ])->assertSuccessful();

            file_put_contents($path, str_replace('QA-2026-09-24', '/private/device.png', (string) file_get_contents($path)));
            $invalid = app(ClubNumberRangeReadinessReport::class)->make(true, $path);
            $this->assertSame('no-go', $invalid['decision']);
            $this->assertSame('pending', collect($invalid['checks'])->firstWhere('id', 'local.evidence')['status']);
        } finally {
            @unlink($path);
        }
    }

    public function test_mysql_concurrency_probe_refuses_unconfirmed_or_non_mysql_environments_without_writes(): void
    {
        $club = Club::factory()->create(['owner_id' => User::factory()]);
        $before = [
            'ranges' => ClubNumberRange::query()->count(),
            'allocations' => ClubNumberAllocation::query()->count(),
        ];

        $this->artisan('airmius:verify-club-number-range-concurrency', [
            '--club' => $club->id,
            '--json' => true,
        ])->assertFailed()->expectsOutputToContain('club-number-range-mysql-concurrency.v1');

        $this->artisan('airmius:verify-club-number-range-concurrency', [
            '--club' => $club->id,
            '--confirm-isolated' => true,
            '--json' => true,
        ])->assertFailed()->expectsOutputToContain('"mysql_connection": "fail"');

        $this->assertSame($before, [
            'ranges' => ClubNumberRange::query()->count(),
            'allocations' => ClubNumberAllocation::query()->count(),
        ]);
    }

    private function range(Club $club, string $scope, string $prefix): ClubNumberRange
    {
        return ClubNumberRange::query()->create([
            'club_id' => $club->id, 'scope' => $scope, 'name' => ucfirst($scope),
            'prefix' => $prefix, 'suffix' => '', 'padding' => 4,
            'start_number' => 1, 'next_number' => 1,
            'reset_policy' => 'never', 'is_active' => true,
        ]);
    }
}
