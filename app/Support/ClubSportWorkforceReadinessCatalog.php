<?php

namespace App\Support;

final class ClubSportWorkforceReadinessCatalog
{
    public const VERSION = '2026-09-26.club-sport-workforce-readiness.v1';

    public static function forClient(): array
    {
        return [
            'version' => self::VERSION,
            'training_development' => [
                'public_release' => [
                    'strict_opt_in',
                    'purpose_bound_media_release',
                    'club_scope_check',
                    'external_club_block',
                    'retroactive_withdrawal_effect',
                    'privacy_audit',
                ],
                'regression_matrix' => ['privacy', 'media', 'foreign_club', 'retroactive_visibility', 'web_api_app'],
            ],
            'competition_results' => [
                'match_report_publication' => [
                    'editorial_review',
                    'consent_check',
                    'visibility_check',
                    'correction_history',
                    'result_source_snapshot',
                ],
                'finance_link' => [
                    'entry_fee',
                    'competition_fee',
                    'invoice_reference',
                    'payment_status',
                    'cost_center_or_project',
                ],
                'regression_matrix' => ['web', 'api', 'app', 'sport_specific_results', 'finance'],
            ],
            'learning_offers' => [
                'end_to_end_channels' => [
                    'public_booking',
                    'admin_web',
                    'native_app',
                    'guest_access',
                    'capacity_deadline_payment',
                    'evaluation_export',
                ],
                'regression_matrix' => ['public', 'admin', 'mobile', 'guest', 'finance', 'waitlist'],
            ],
            'workforce' => [
                'compensation_processes' => [
                    'honorarium',
                    'expense_reimbursement',
                    'travel_costs',
                    'vacation',
                    'absence',
                    'employment_model_rules',
                    'review_required',
                ],
                'payroll_export' => [
                    'controlled_export',
                    'minimal_personal_data',
                    'role_bound',
                    'period_bound',
                    'audit_log',
                ],
                'regression_matrix' => ['web', 'api', 'app', 'roles', 'privacy'],
            ],
            'volunteers' => [
                'recognition' => [
                    'volunteer_certificate',
                    'thank_you_letter',
                    'hours_snapshot',
                    'notification',
                    'role_limited_issue',
                    'audit_log',
                ],
                'regression_matrix' => ['web', 'api', 'app', 'notifications', 'roles'],
            ],
            'decision' => 'local_contract_ready_external_gates_open',
        ];
    }
}
