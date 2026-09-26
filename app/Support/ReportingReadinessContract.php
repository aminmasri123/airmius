<?php

namespace App\Support;

final class ReportingReadinessContract
{
    public const VERSION = '2026-09-26.reporting-readiness.v1';

    public static function forClient(): array
    {
        return [
            'version' => self::VERSION,
            'coverage_dimensions' => [
                self::dimension('age', ['birth_date_bucket', 'minimum_group_size', 'as_of_date']),
                self::dimension('department', ['club_department_id', 'membership_scope', 'effective_period']),
                self::dimension('membership', ['status', 'type', 'entry_date', 'exit_date']),
                self::dimension('training', ['sessions', 'attendance', 'privacy_scope', 'team_context']),
                self::dimension('course', ['bookings', 'waitlist', 'capacity', 'public_private_split']),
                self::dimension('finance', ['receivables', 'payments', 'open_amounts', 'period']),
                self::dimension('trainer_hours', ['trainer_id', 'minutes', 'role_scope', 'approval_state']),
                self::dimension('volunteering', ['service_hours', 'requirements', 'credited_until']),
                self::dimension('resources', ['inventory', 'loans', 'reservations', 'condition']),
                self::dimension('material', ['stock', 'consumption', 'damage', 'correction_movements']),
            ],
            'regression' => [
                'query_budget_required' => true,
                'index_coverage_required' => true,
                'timezone_policy' => 'club_timezone_with_utc_storage',
                'period_policy' => 'club_year_period_or_explicit_date_range',
                'privacy_policy' => [
                    'minimum_group_size_required' => true,
                    'private_notes_excluded' => true,
                    'member_level_export_requires_permission' => true,
                ],
                'large_dataset_strategy' => [
                    'async_export',
                    'chunked_query',
                    'bounded_preview',
                    'download_expiry',
                ],
                'reconciliation_sources' => [
                    'club_user',
                    'club_external_members',
                    'training_logs',
                    'learning_enrollments',
                    'invoices',
                    'payments',
                    'club_inventory_items',
                ],
            ],
            'decision' => 'local_contract_ready',
        ];
    }

    private static function dimension(string $key, array $fields): array
    {
        return [
            'key' => $key,
            'status' => 'covered_by_contract',
            'fields' => $fields,
        ];
    }
}
