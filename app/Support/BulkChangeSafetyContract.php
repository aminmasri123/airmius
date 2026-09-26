<?php

namespace App\Support;

final class BulkChangeSafetyContract
{
    public const VERSION = '2026-09-26.bulk-change-safety.v1';

    public static function forClient(): array
    {
        return [
            'version' => self::VERSION,
            'operation_contract' => [
                'dry_run_required' => true,
                'diff_required_before_commit' => true,
                'scope_limit_required' => true,
                'explicit_confirmation_required' => true,
                'idempotency_key_required' => true,
                'tenant_scope_required' => true,
            ],
            'limits' => [
                'default_preview_limit' => 100,
                'default_commit_limit' => 500,
                'requires_chunking_above' => 100,
                'requires_admin_step_up_for_commit' => true,
            ],
            'job_progress' => [
                'states' => ['draft', 'previewed', 'confirmed', 'queued', 'running', 'completed', 'failed', 'rolled_back'],
                'progress_fields' => ['total', 'processed', 'changed', 'skipped', 'failed'],
                'failure_report_required' => true,
                'partial_failure_is_visible' => true,
            ],
            'rollback' => [
                'strategy_required' => true,
                'snapshot_or_inverse_patch_required' => true,
                'rollback_is_permission_checked' => true,
                'rollback_is_audited' => true,
                'irreversible_operations_require_four_eyes' => true,
            ],
            'audit' => [
                'events' => [
                    'bulk.previewed',
                    'bulk.confirmed',
                    'bulk.queued',
                    'bulk.completed',
                    'bulk.failed',
                    'bulk.rolled_back',
                ],
                'metadata' => [
                    'actor_id',
                    'club_id',
                    'operation',
                    'dry_run_hash',
                    'confirmation_token',
                    'idempotency_key',
                    'changed_count',
                ],
                'sensitive_values_redacted' => true,
            ],
            'decision' => 'local_contract_ready',
        ];
    }
}
