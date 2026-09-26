<?php

namespace Tests\Feature;

use App\Support\BulkChangeSafetyContract;
use Tests\TestCase;

class BulkChangeSafetyContractTest extends TestCase
{
    public function test_meta_exposes_bulk_change_safety_contract(): void
    {
        $contract = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->json('data.catalogs.bulk_change_safety');

        $this->assertSame(BulkChangeSafetyContract::VERSION, $contract['version']);
        $this->assertSame('local_contract_ready', $contract['decision']);
        $this->assertTrue($contract['operation_contract']['dry_run_required']);
        $this->assertTrue($contract['operation_contract']['diff_required_before_commit']);
        $this->assertTrue($contract['operation_contract']['explicit_confirmation_required']);
        $this->assertTrue($contract['operation_contract']['tenant_scope_required']);
        $this->assertSame(500, $contract['limits']['default_commit_limit']);
        $this->assertTrue($contract['limits']['requires_admin_step_up_for_commit']);
        $this->assertContains('running', $contract['job_progress']['states']);
        $this->assertContains('failed', $contract['job_progress']['progress_fields']);
        $this->assertTrue($contract['rollback']['snapshot_or_inverse_patch_required']);
        $this->assertTrue($contract['rollback']['irreversible_operations_require_four_eyes']);
        $this->assertContains('bulk.rolled_back', $contract['audit']['events']);
        $this->assertContains('confirmation_token', $contract['audit']['metadata']);
        $this->assertTrue($contract['audit']['sensitive_values_redacted']);
    }
}
