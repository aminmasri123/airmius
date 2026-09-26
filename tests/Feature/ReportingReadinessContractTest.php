<?php

namespace Tests\Feature;

use App\Support\ReportingReadinessContract;
use Tests\TestCase;

class ReportingReadinessContractTest extends TestCase
{
    public function test_meta_exposes_reporting_dimension_and_regression_contract(): void
    {
        $contract = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->json('data.catalogs.reporting_readiness');

        $this->assertSame(ReportingReadinessContract::VERSION, $contract['version']);
        $this->assertSame('local_contract_ready', $contract['decision']);

        $dimensions = collect($contract['coverage_dimensions'])->keyBy('key');
        foreach (['age', 'department', 'membership', 'training', 'course', 'finance', 'trainer_hours', 'volunteering', 'resources', 'material'] as $key) {
            $this->assertSame('covered_by_contract', $dimensions[$key]['status']);
            $this->assertNotEmpty($dimensions[$key]['fields']);
        }

        $this->assertContains('minimum_group_size', $dimensions['age']['fields']);
        $this->assertContains('privacy_scope', $dimensions['training']['fields']);
        $this->assertContains('correction_movements', $dimensions['material']['fields']);
        $this->assertTrue($contract['regression']['query_budget_required']);
        $this->assertTrue($contract['regression']['index_coverage_required']);
        $this->assertSame('club_timezone_with_utc_storage', $contract['regression']['timezone_policy']);
        $this->assertSame('club_year_period_or_explicit_date_range', $contract['regression']['period_policy']);
        $this->assertTrue($contract['regression']['privacy_policy']['minimum_group_size_required']);
        $this->assertContains('async_export', $contract['regression']['large_dataset_strategy']);
        $this->assertContains('payments', $contract['regression']['reconciliation_sources']);
    }
}
