<?php

namespace Tests\Feature;

use App\Support\DatabaseQueryPlanReport;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class DatabaseQueryPlanAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_contract_compiles_every_bounded_query_without_exposing_database_material(): void
    {
        $secretReference = 'DBA-private-release-reference';
        $report = app(DatabaseQueryPlanReport::class)->make(null, false, $secretReference);
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertSame('query-plan-readiness.v1', $report['contract']);
        $this->assertSame('repository', $report['mode']);
        $this->assertSame('no_go', $report['decision']);
        $this->assertTrue($report['automated_checks_passed'], $encoded);
        $this->assertFalse($report['evidence_complete']);
        $this->assertSame('pass', $this->checkStatus($report, 'database.index_contracts'));
        $this->assertSame('pending', $this->checkStatus($report, 'database.production_like_dialect'));
        $this->assertCount(25, collect($report['checks'])->filter(fn (array $check): bool => str_starts_with($check['id'], 'query.')));
        $this->assertStringNotContainsString($secretReference, $encoded);
        $this->assertStringNotContainsString('select ', strtolower($encoded));
        $this->assertStringNotContainsString('2025-01-01', $encoded);
        foreach ($report['privacy'] as $value) {
            $this->assertFalse($value);
        }
    }

    public function test_command_is_non_blocking_for_repository_mode_but_strict_mode_requires_staging_evidence(): void
    {
        $this->assertSame(Command::SUCCESS, Artisan::call('airmius:audit-query-plans', [
            '--online-ddl-reference' => 'DBA-1234',
            '--json' => true,
        ]));
        $this->assertSame(Command::FAILURE, Artisan::call('airmius:audit-query-plans', [
            '--online-ddl-reference' => 'DBA-1234',
            '--json' => true,
            '--strict' => true,
        ]));

        $output = Artisan::output();
        $this->assertStringNotContainsString('DBA-1234', $output);
        $this->assertStringNotContainsString('select ', strtolower($output));
    }

    public function test_invalid_connection_or_ddl_reference_fails_without_echoing_input(): void
    {
        $secretConnection = 'mysql?password=private-secret';
        $invalidConnection = app(DatabaseQueryPlanReport::class)->make($secretConnection);
        $this->assertFalse($invalidConnection['automated_checks_passed']);
        $this->assertStringNotContainsString('private-secret', json_encode($invalidConnection, JSON_THROW_ON_ERROR));

        $secretReference = 'https://private.example.test/ddl?token=private-secret';
        $invalidReference = app(DatabaseQueryPlanReport::class)->make(null, false, $secretReference);
        $this->assertFalse($invalidReference['automated_checks_passed']);
        $this->assertSame('fail', $this->checkStatus($invalidReference, 'evidence.online_ddl'));
        $this->assertStringNotContainsString('private-secret', json_encode($invalidReference, JSON_THROW_ON_ERROR));
    }

    public function test_missing_exact_index_contract_fails_closed(): void
    {
        Schema::table('learning_courses', fn ($table) => $table->dropIndex('learning_public_filter_idx'));

        $report = app(DatabaseQueryPlanReport::class)->make(null, false, 'DBA-1234');

        $this->assertFalse($report['automated_checks_passed']);
        $this->assertSame('fail', $this->checkStatus($report, 'database.index_contracts'));
    }

    /** @param array<string, mixed> $report */
    private function checkStatus(array $report, string $id): string
    {
        $check = collect($report['checks'])->firstWhere('id', $id);
        $this->assertIsArray($check, "Missing query-plan check: {$id}");

        return $check['status'];
    }
}
