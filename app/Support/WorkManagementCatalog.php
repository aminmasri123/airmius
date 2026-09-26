<?php

namespace App\Support;

final class WorkManagementCatalog
{
    public const VERSION = '2026-09-26.work-management.v1';

    public static function forClient(): array
    {
        return [
            'version' => self::VERSION,
            'models' => [
                self::model('task', ['title', 'status', 'due_at', 'assignees', 'watchers', 'dependencies', 'recurrence', 'checklist']),
                self::model('project', ['title', 'status', 'owner', 'members', 'watchers', 'milestones', 'dependencies', 'checklist_rollup']),
            ],
            'statuses' => ['draft', 'open', 'in_progress', 'blocked', 'waiting', 'done', 'cancelled'],
            'roles' => [
                'assignees' => 'responsible_users_required_for_active_tasks',
                'watchers' => 'read_and_notification_observers_without_completion_rights',
                'owner' => 'single_project_responsible',
            ],
            'dependencies' => [
                'types' => ['blocks', 'blocked_by', 'relates_to'],
                'rules' => ['same_tenant_only', 'no_self_dependency', 'cycle_detection_required'],
            ],
            'recurrence' => [
                'patterns' => ['daily', 'weekly', 'monthly', 'yearly', 'custom_interval'],
                'rules' => ['next_instance_after_completion', 'bounded_until_or_count', 'idempotency_key_per_occurrence'],
            ],
            'checklist' => [
                'item_fields' => ['title', 'status', 'assignee_id', 'due_at', 'completed_at'],
                'rollup' => ['total', 'completed', 'overdue'],
            ],
            'forms' => [
                'versioned_field_types' => ['text', 'number', 'date', 'select', 'checkbox', 'file', 'signature'],
                'rules' => ['server_validation_required', 'conditional_visibility', 'purpose_binding_required_for_files', 'immutable_version_after_activation'],
            ],
            'approvals' => [
                'modes' => ['single', 'multi_person', 'four_eyes'],
                'rules' => ['role_scope_resolution', 'delegation_resolution', 'self_approval_blocked', 'escalation_path_required'],
            ],
            'automation' => [
                'states' => ['draft', 'sandbox_test', 'active', 'paused', 'retired'],
                'execution_guards' => ['dry_run_supported', 'immutable_execution_log', 'idempotency_key_required', 'failed_job_manual_retry_safe'],
                'critical_decisions' => ['human_step_required', 'technical_self_approval_blocked'],
            ],
            'reminders_escalations' => [
                'job_kinds' => ['reminder', 'escalation'],
                'statuses' => ['queued', 'running', 'completed', 'failed'],
                'guards' => ['tenant_scoped_idempotency_key', 'queue_dispatched_once', 'failed_jobs_visible', 'permission_checked_manual_retry', 'audit_logged'],
            ],
            'reference_flows' => [
                'membership_intake',
                'free_course_place',
                'license_expiry',
                'equipment_damage',
                'membership_end',
            ],
            'regression_matrix' => [
                'loop_detection',
                'deduplication',
                'concurrency_locking',
                'error_recovery',
                'permission_boundaries',
                'web_api_app_parity',
            ],
        ];
    }

    private static function model(string $key, array $requiredFields): array
    {
        return [
            'key' => $key,
            'tenant_scoped' => true,
            'required_fields' => $requiredFields,
            'audit_events' => [
                "work.{$key}.created",
                "work.{$key}.updated",
                "work.{$key}.completed",
            ],
        ];
    }
}
