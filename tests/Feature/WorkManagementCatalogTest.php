<?php

namespace Tests\Feature;

use App\Support\WorkManagementCatalog;
use Tests\TestCase;

class WorkManagementCatalogTest extends TestCase
{
    public function test_meta_exposes_task_and_project_model_contract(): void
    {
        $catalog = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->json('data.catalogs.work_management');

        $this->assertSame(WorkManagementCatalog::VERSION, $catalog['version']);

        $models = collect($catalog['models'])->keyBy('key');

        foreach (['task', 'project'] as $key) {
            $this->assertTrue($models[$key]['tenant_scoped']);
            $this->assertContains('status', $models[$key]['required_fields']);
            $this->assertContains('watchers', $models[$key]['required_fields']);
        }

        $this->assertContains('assignees', $models['task']['required_fields']);
        $this->assertContains('due_at', $models['task']['required_fields']);
        $this->assertContains('dependencies', $models['task']['required_fields']);
        $this->assertContains('recurrence', $models['task']['required_fields']);
        $this->assertContains('checklist', $models['task']['required_fields']);
        $this->assertContains('cycle_detection_required', $catalog['dependencies']['rules']);
        $this->assertContains('idempotency_key_per_occurrence', $catalog['recurrence']['rules']);
        $this->assertSame(['total', 'completed', 'overdue'], $catalog['checklist']['rollup']);
    }

    public function test_meta_exposes_workflow_forms_approvals_automation_and_regression_contract(): void
    {
        $catalog = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->json('data.catalogs.work_management');

        $this->assertContains('file', $catalog['forms']['versioned_field_types']);
        $this->assertContains('purpose_binding_required_for_files', $catalog['forms']['rules']);
        $this->assertContains('four_eyes', $catalog['approvals']['modes']);
        $this->assertContains('self_approval_blocked', $catalog['approvals']['rules']);
        $this->assertContains('sandbox_test', $catalog['automation']['states']);
        $this->assertContains('dry_run_supported', $catalog['automation']['execution_guards']);
        $this->assertContains('human_step_required', $catalog['automation']['critical_decisions']);
        $this->assertSame(['reminder', 'escalation'], $catalog['reminders_escalations']['job_kinds']);
        $this->assertContains('failed_jobs_visible', $catalog['reminders_escalations']['guards']);
        $this->assertContains('permission_checked_manual_retry', $catalog['reminders_escalations']['guards']);
        $this->assertSame([
            'membership_intake',
            'free_course_place',
            'license_expiry',
            'equipment_damage',
            'membership_end',
        ], $catalog['reference_flows']);
        $this->assertContains('web_api_app_parity', $catalog['regression_matrix']);
    }
}
