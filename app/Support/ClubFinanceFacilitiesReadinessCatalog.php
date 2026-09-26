<?php

namespace App\Support;

final class ClubFinanceFacilitiesReadinessCatalog
{
    public const VERSION = '2026-09-26.club-finance-facilities-readiness.v1';

    public static function forClient(): array
    {
        return [
            'version' => self::VERSION,
            'facilities' => [
                'access_keys' => [
                    'issue',
                    'validity_window',
                    'return',
                    'loss_block',
                    'audit_log',
                    'resource_scope',
                ],
                'regression_matrix' => [
                    'calendar',
                    'web',
                    'api',
                    'app',
                    'concurrency',
                    'timezone',
                    'tenant_boundaries',
                ],
            ],
            'inventory_fleet' => [
                'maintenance_replacement' => [
                    'planned_maintenance',
                    'replacement_window',
                    'affected_item_block',
                    'responsible_notification',
                    'audit_status',
                ],
                'vehicle_resource' => [
                    'reservation',
                    'driver_permission',
                    'trip_log',
                    'mileage',
                    'key_handover',
                    'damage_record',
                    'cost_assignment',
                ],
                'regression_matrix' => [
                    'web',
                    'api',
                    'app',
                    'qr',
                    'concurrency',
                    'roles',
                    'inventory_migration',
                ],
            ],
            'tariffs_billing' => [
                'alternate_payer_household' => [
                    'payer_snapshot',
                    'household_grouping',
                    'collective_invoice',
                    'sepa_mandate_reference',
                    'notification_recipient',
                    'transaction_boundary',
                ],
                'hardship_installments' => [
                    'restricted_permission',
                    'four_eyes_approval',
                    'installment_plan',
                    'deferral',
                    'minimal_audit',
                    'exception_reason_redaction',
                ],
                'regression_matrix' => [
                    'calculation',
                    'rounding',
                    'periods',
                    'multi_club',
                    'web',
                    'api',
                    'app',
                    'legacy_inventory',
                ],
            ],
            'payments_acceptance' => [
                'go_no_go_sheets' => [
                    'mysql_concurrency',
                    'staging_mail',
                    'staging_bank',
                    'browser',
                    'real_device',
                    'legacy_migration',
                    'finance_domain_acceptance',
                ],
                'external_completion' => 'pending',
            ],
            'accounting_close' => [
                'tax_accounting_rules' => [
                    'versioned_vat_rules',
                    'versioned_accounting_rules',
                    'domain_approval_required_before_automation',
                    'effective_period',
                    'source_reference',
                ],
                'invoice_review_payment_release' => [
                    'role_separation',
                    'thresholds',
                    'four_eyes_approval',
                    'payment_release_status',
                    'audit_log',
                ],
                'e_invoice' => [
                    'inbound_validation',
                    'outbound_validation',
                    'safe_preview',
                    'archive',
                    'export',
                ],
                'year_end' => [
                    'tax_advisor_export',
                    'period_lock',
                    'reconciliation',
                    'staging_acceptance_required',
                ],
            ],
            'decision' => 'local_contract_ready_external_gates_open',
        ];
    }
}
