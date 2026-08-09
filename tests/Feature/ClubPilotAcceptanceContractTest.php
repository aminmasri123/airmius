<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use App\Support\ClubPilotAcceptanceRegistry;
use App\Support\ClubPilotReadinessReport;
use App\Support\CriticalJourneyRegistry;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubPilotAcceptanceContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_covers_cohort_journeys_guest_entry_metrics_raci_and_rollback(): void
    {
        $registry = ClubPilotAcceptanceRegistry::definitions();

        $this->assertSame('club-pilot.v1', $registry['contract']);
        $this->assertSame(3, $registry['cohort']['minimum_clubs']);
        $this->assertSame(5, $registry['cohort']['maximum_clubs']);
        $this->assertCount(6, $registry['metrics']);
        $this->assertCount(5, $registry['checkpoints']);
        $this->assertGreaterThanOrEqual(3, count($registry['guest_acceptance']));
        $this->assertSame('Customer Success', $registry['responsibility']['responsible']);
        $this->assertSame('Product Lead', $registry['responsibility']['accountable']);
        $this->assertTrue($registry['rollback']['must_be_rehearsed']);
        $this->assertTrue($registry['rollback']['preserve_user_data']);
        $this->assertFalse($registry['evidence']['stores_personal_data']);

        foreach ($registry['journeys'] as $journey) {
            $this->assertArrayHasKey($journey, CriticalJourneyRegistry::definitions());
        }
        foreach ($registry['guest_acceptance'] as $control) {
            $this->assertFileExists(base_path($control['source']));
            $this->assertFileExists(base_path($control['test']));
        }
    }

    public function test_repository_report_passes_without_claiming_real_pilot_evidence(): void
    {
        $report = app(ClubPilotReadinessReport::class)->make();

        $this->assertTrue($report['automated_checks_passed']);
        $this->assertFalse($report['pilot_evidence_complete']);
        $this->assertSame('no_go', $report['decision']);
        $this->assertSame(0, $report['summary']['fail']);
        $this->assertSame('skipped', $this->checkStatus($report, 'runtime.cohort'));
        $this->assertSame('pending', $this->checkStatus($report, 'external.club_pilot'));
        $this->assertFalse($report['privacy']['stores_personal_data']);
        $this->assertFalse($report['privacy']['outputs_club_identifiers']);
    }

    public function test_data_mode_checks_three_clubs_and_outputs_aggregates_only(): void
    {
        $clubs = collect(range(1, 3))->map(function (int $index): Club {
            $owner = User::factory()->create(['name' => 'Private Pilot Owner '.$index]);

            return Club::factory()->create([
                'owner_id' => $owner->id,
                'name' => 'Private Pilot Club '.$index,
                'verification_status' => 'verified',
            ]);
        });
        config([
            'airmius_pilot.enabled' => true,
            'airmius_pilot.club_ids' => $clubs->pluck('id')->all(),
            'airmius_pilot.minimum_onboarding_percent' => 0,
            'airmius_pilot.evidence_path' => resource_path('release/missing-pilot-evidence.json'),
        ]);

        $report = app(ClubPilotReadinessReport::class)->make(true);
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertSame('pass', $this->checkStatus($report, 'runtime.cohort'));
        $this->assertStringContainsString('3 clubs configured and found', $encoded);
        $this->assertStringNotContainsString('Private Pilot Club', $encoded);
        $this->assertStringNotContainsString('Private Pilot Owner', $encoded);
        $this->assertStringNotContainsString('"club_ids":', $encoded);
        $this->assertStringNotContainsString('"club_id":', $encoded);
    }

    public function test_data_mode_fails_closed_for_an_invalid_cohort_without_printing_ids(): void
    {
        config([
            'airmius_pilot.enabled' => true,
            'airmius_pilot.club_ids' => [991, 992, 993, 994, 995, 996],
        ]);

        $report = app(ClubPilotReadinessReport::class)->make(true);
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertSame('fail', $this->checkStatus($report, 'runtime.cohort'));
        $this->assertFalse($report['automated_checks_passed']);
        foreach ([991, 992, 993, 994, 995, 996] as $identifier) {
            $this->assertStringNotContainsString((string) $identifier, $encoded);
        }
    }

    public function test_local_evidence_rejects_direct_identifier_fields_and_fake_passes(): void
    {
        $template = json_decode(
            file_get_contents(resource_path('release/club_pilot_evidence.template.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $template['status'] = 'passed';
        $template['cohort'] = [
            'size' => 3,
            'aliases' => ['pilot-01', 'pilot-02', 'pilot-03'],
            'selection_criteria_confirmed' => true,
            'club_name' => 'Must never be stored',
        ];
        $path = tempnam(sys_get_temp_dir(), 'airmius-pilot-evidence-');
        file_put_contents($path, json_encode($template, JSON_THROW_ON_ERROR));

        try {
            config(['airmius_pilot.evidence_path' => $path]);
            $report = app(ClubPilotReadinessReport::class)->make();

            $this->assertSame('fail', $this->checkStatus($report, 'local.evidence'));
            $this->assertStringContainsString('personal_data_key', json_encode($report, JSON_THROW_ON_ERROR));
            $this->assertStringNotContainsString('Must never be stored', json_encode($report, JSON_THROW_ON_ERROR));
        } finally {
            @unlink($path);
        }
    }

    public function test_command_supports_ci_json_data_mode_and_strict_external_gate(): void
    {
        $this->artisan('airmius:audit-club-pilot', ['--json' => true])
            ->expectsOutputToContain('"automated_checks_passed": true')
            ->assertExitCode(Command::SUCCESS);

        $this->artisan('airmius:audit-club-pilot', ['--strict' => true])
            ->assertExitCode(Command::FAILURE);
    }

    /** @param array<string, mixed> $report */
    private function checkStatus(array $report, string $id): ?string
    {
        return collect($report['checks'])->firstWhere('id', $id)['status'] ?? null;
    }
}
