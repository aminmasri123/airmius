<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubYearPeriod;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubYearPeriodReadinessReport;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ClubYearPeriodReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_only_runtime_audit_reports_historical_inventory_without_changing_rows(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $period = $this->period($club, 'sport', 'Saison 2026');
        $team = Team::factory()->create(['club_id' => $club->id, 'sport_year_period_id' => null]);
        $before = $team->fresh()->getAttributes();

        $report = app(ClubYearPeriodReadinessReport::class)->make(true);

        $this->assertTrue($report['automated_checks_passed']);
        $this->assertSame('no-go', $report['decision']);
        $this->assertSame('runtime-read-only', $report['mode']);
        $this->assertSame(1, $report['inventory']['periods']['sport']);
        $this->assertSame(1, $report['inventory']['unassigned']['team_sport']);
        $this->assertSame(0, array_sum($report['inventory']['invalid_references']));
        $this->assertSame($before, $team->fresh()->getAttributes());
        $this->assertDatabaseHas('club_year_periods', ['id' => $period->id]);
    }

    public function test_runtime_audit_rejects_cross_club_or_wrong_type_references(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $business = $this->period($club, 'business', 'Geschäftsjahr 2026');
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->forceFill(['sport_year_period_id' => $business->id])->saveQuietly();

        $report = app(ClubYearPeriodReadinessReport::class)->make(true);
        $referenceCheck = collect($report['checks'])->firstWhere('id', 'database.references');

        $this->assertFalse($report['automated_checks_passed']);
        $this->assertSame('fail', $referenceCheck['status']);
        $this->assertSame(1, $report['inventory']['invalid_references']['team_sport']);
        $this->assertSame(Command::FAILURE, Artisan::call('airmius:audit-club-year-periods', [
            '--with-data' => true,
            '--json' => true,
        ]));
    }

    public function test_strict_audit_requires_complete_external_evidence_and_can_reach_go(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $this->period($club, 'business', 'Geschäftsjahr 2026');
        $this->period($club, 'contribution', 'Beitragsjahr 2026');
        $sport = $this->period($club, 'sport', 'Saison 2026');
        Team::factory()->create(['club_id' => $club->id, 'sport_year_period_id' => $sport->id]);

        $this->assertSame(Command::FAILURE, Artisan::call('airmius:audit-club-year-periods', [
            '--with-data' => true,
            '--strict' => true,
            '--json' => true,
        ]));

        $evidence = json_decode((string) file_get_contents(resource_path('release/club_year_period_evidence.template.json')), true, flags: JSON_THROW_ON_ERROR);
        $evidence['status'] = 'passed';
        $evidence['migration'] = [
            'backup_verified' => true,
            'dry_run_passed' => true,
            'rollback_rehearsed' => true,
        ];
        $evidence['surfaces'] = array_fill_keys(ClubYearPeriodReadinessReport::SURFACES, 'passed');
        $evidence['journeys'] = array_fill_keys(ClubYearPeriodReadinessReport::JOURNEYS, 'passed');
        $evidence['approvals'] = ['product' => true, 'engineering' => true];
        $evidence['evidence_references'] = ['QA-CLUB-YEARS-2026-09'];
        $path = tempnam(sys_get_temp_dir(), 'airmius-year-period-evidence-');
        file_put_contents($path, json_encode($evidence, JSON_THROW_ON_ERROR));

        try {
            $this->assertSame(Command::SUCCESS, Artisan::call('airmius:audit-club-year-periods', [
                '--with-data' => true,
                '--evidence' => $path,
                '--strict' => true,
                '--json' => true,
            ]));
            $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('go', $output['decision']);
            $this->assertSame('pass', collect($output['checks'])->firstWhere('id', 'local.evidence')['status']);
        } finally {
            @unlink($path);
        }
    }

    public function test_audit_rejects_overlaps_and_evidence_with_unapproved_free_text(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $this->period($club, 'sport', 'Saison A');
        ClubYearPeriod::query()->create([
            'club_id' => $club->id,
            'type' => 'sport',
            'name' => 'Saison B',
            'starts_on' => '2026-06-01',
            'ends_on' => '2027-05-31',
        ]);

        $evidence = json_decode((string) file_get_contents(resource_path('release/club_year_period_evidence.template.json')), true, flags: JSON_THROW_ON_ERROR);
        $evidence['status'] = 'passed';
        $evidence['migration'] = array_fill_keys(['backup_verified', 'dry_run_passed', 'rollback_rehearsed'], true);
        $evidence['surfaces'] = array_fill_keys(ClubYearPeriodReadinessReport::SURFACES, 'passed');
        $evidence['journeys'] = array_fill_keys(ClubYearPeriodReadinessReport::JOURNEYS, 'passed');
        $evidence['approvals'] = ['product' => true, 'engineering' => true];
        $evidence['evidence_references'] = ['QA-CLUB-YEARS-2026-09'];
        $evidence['free_text_notes'] = 'must not enter the coordination evidence';
        $path = tempnam(sys_get_temp_dir(), 'airmius-year-period-evidence-');
        file_put_contents($path, json_encode($evidence, JSON_THROW_ON_ERROR));

        try {
            $report = app(ClubYearPeriodReadinessReport::class)->make(true, $path);
            $this->assertFalse($report['automated_checks_passed']);
            $this->assertSame('no-go', $report['decision']);
            $this->assertSame(1, $report['inventory']['overlapping_period_pairs']);
            $this->assertSame('fail', collect($report['checks'])->firstWhere('id', 'database.period_overlap')['status']);
            $this->assertSame('pending', collect($report['checks'])->firstWhere('id', 'local.evidence')['status']);
        } finally {
            @unlink($path);
        }
    }

    private function period(Club $club, string $type, string $name): ClubYearPeriod
    {
        return ClubYearPeriod::query()->create([
            'club_id' => $club->id,
            'type' => $type,
            'name' => $name,
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
    }
}
