<?php

namespace App\Support;

final class IntegrationCatalog
{
    public const VERSION = '2026-09-26';

    /**
     * Canonical, non-secret contract inventory for external integration work.
     */
    public static function forClient(): array
    {
        return [
            'version' => self::VERSION,
            'security_baseline' => [
                'credentials' => 'encrypted_reference_only',
                'scopes' => 'least_privilege_per_capability',
                'credential_rotation' => 'required_per_provider_policy',
                'webhook_signatures' => 'required_for_write_events',
                'rate_limits' => 'provider_and_tenant_bound',
                'idempotency' => 'provider_key + external_id + tenant_scope',
                'audit' => 'technical_event_without_secret_payloads',
            ],
            'adapter_standard' => [
                'encrypted_credentials' => true,
                'minimal_scopes' => true,
                'rotation_supported' => true,
                'webhook_signature_verification' => true,
                'tenant_rate_limit' => true,
                'idempotency_key' => 'provider_key + external_id + tenant_scope',
                'secrets_never_returned_to_clients' => true,
            ],
            'sync_observability' => [
                'per_record_statuses' => ['pending', 'synced', 'conflict', 'duplicate', 'quarantined', 'retryable_error', 'permanent_error'],
                'cursor_required' => true,
                'conflict_payload_is_redacted' => true,
                'controlled_retry_requires_permission' => true,
                'quarantine_requires_operator_decision' => true,
            ],
            'portable_export' => [
                'manifest_version' => 'airmius.portable-export.v1',
                'checksums_required' => true,
                'relationships_required' => true,
                'files_inventory_required' => true,
                'documented_import_format_required' => true,
                'download_audit_required' => true,
            ],
            'test_matrix' => [
                'contract_tests' => true,
                'sandbox_tests' => true,
                'timeouts' => true,
                'bounded_retries' => true,
                'schema_change_detection' => true,
                'security_tests' => true,
                'provider_staging_evidence' => 'external_gate',
            ],
            'contracts' => [
                self::contract(
                    key: 'generic_import',
                    label: 'Generic CSV/XLSX import',
                    direction: 'inbound',
                    domains: ['members', 'finance', 'events', 'inventory'],
                    formats: ['csv', 'xlsx'],
                    capabilities: ['preview', 'field_mapping', 'normalization', 'deduplication', 'atomic_commit', 'error_report'],
                    requiredFields: ['tenant_scope', 'source_file', 'mapping', 'commit_token'],
                    optionalFields: ['dedupe_strategy', 'send_invitation', 'dry_run'],
                    syncModel: 'commit_batch',
                ),
                self::contract(
                    key: 'portable_export',
                    label: 'Portable data export',
                    direction: 'outbound',
                    domains: ['members', 'clubs', 'teams', 'finance', 'files', 'audit'],
                    formats: ['jsonl', 'csv', 'binary_files', 'manifest.json'],
                    capabilities: ['manifest', 'checksums', 'relationships', 'file_inventory', 'retention_metadata'],
                    requiredFields: ['tenant_scope', 'export_id', 'manifest_version', 'checksums'],
                    optionalFields: ['purpose', 'expires_at', 'redaction_profile'],
                    syncModel: 'snapshot',
                ),
                self::contract(
                    key: 'bank',
                    label: 'Bank and payment reconciliation',
                    direction: 'bidirectional',
                    domains: ['finance', 'sepa', 'payments'],
                    formats: ['pain.008', 'camt.053', 'csv'],
                    capabilities: ['sepa_export', 'return_import', 'statement_match', 'fee_recharge', 'reconciliation_audit'],
                    requiredFields: ['club_id', 'batch_reference', 'amount_minor', 'currency', 'booking_date'],
                    optionalFields: ['mandate_reference', 'end_to_end_id', 'bank_transaction_id'],
                    syncModel: 'cursor',
                ),
                self::contract(
                    key: 'calendar',
                    label: 'Calendar exchange',
                    direction: 'bidirectional',
                    domains: ['events', 'training', 'teams'],
                    formats: ['icalendar', 'json'],
                    capabilities: ['event_export', 'attendance_context', 'update_cursor', 'conflict_marker'],
                    requiredFields: ['event_id', 'starts_at', 'ends_at', 'timezone', 'visibility'],
                    optionalFields: ['location', 'team_id', 'recurrence', 'external_calendar_id'],
                    syncModel: 'cursor',
                ),
                self::contract(
                    key: 'mail',
                    label: 'Transactional mail events',
                    direction: 'inbound',
                    domains: ['mail', 'notifications', 'sepa'],
                    formats: ['json_webhook'],
                    capabilities: ['delivery_status', 'bounce_status', 'provider_message_id', 'signature_verification'],
                    requiredFields: ['provider', 'message_id', 'event_type', 'occurred_at'],
                    optionalFields: ['notice_id', 'recipient_hash', 'failure_class'],
                    syncModel: 'webhook_event',
                ),
                self::contract(
                    key: 'association',
                    label: 'Association and federation records',
                    direction: 'bidirectional',
                    domains: ['members', 'licenses', 'clubs'],
                    formats: ['csv', 'json'],
                    capabilities: ['member_number', 'license_validity', 'status_import', 'change_export'],
                    requiredFields: ['club_id', 'member_reference', 'association_key', 'status'],
                    optionalFields: ['license_number', 'valid_from', 'valid_until', 'discipline'],
                    syncModel: 'batch_delta',
                ),
                self::contract(
                    key: 'access_control',
                    label: 'Access control and check-in',
                    direction: 'bidirectional',
                    domains: ['member_cards', 'events', 'facilities'],
                    formats: ['json_webhook', 'csv'],
                    capabilities: ['card_state', 'qr_checkin', 'facility_permission', 'quarantine_unknown_card'],
                    requiredFields: ['club_id', 'card_reference', 'event_or_facility_id', 'occurred_at'],
                    optionalFields: ['device_id', 'operator_id', 'denial_reason'],
                    syncModel: 'webhook_event',
                ),
                self::contract(
                    key: 'ticketing',
                    label: 'Ticketing and support exchange',
                    direction: 'bidirectional',
                    domains: ['support', 'operations'],
                    formats: ['json'],
                    capabilities: ['ticket_create', 'status_sync', 'comment_sync', 'attachment_reference'],
                    requiredFields: ['tenant_scope', 'ticket_reference', 'status', 'updated_at'],
                    optionalFields: ['priority', 'assignee_reference', 'external_url'],
                    syncModel: 'cursor',
                ),
                self::contract(
                    key: 'pos',
                    label: 'Point of sale and register imports',
                    direction: 'inbound',
                    domains: ['commerce', 'finance', 'inventory'],
                    formats: ['csv', 'json'],
                    capabilities: ['sales_import', 'tax_summary', 'inventory_delta', 'daily_close_reference'],
                    requiredFields: ['club_id', 'external_receipt_id', 'sold_at', 'gross_amount_minor', 'currency'],
                    optionalFields: ['vat_rate', 'product_reference', 'payment_method', 'cashier_reference'],
                    syncModel: 'batch_delta',
                ),
                self::contract(
                    key: 'time_tracking',
                    label: 'Time tracking exchange',
                    direction: 'bidirectional',
                    domains: ['volunteers', 'staff', 'events'],
                    formats: ['csv', 'json'],
                    capabilities: ['time_entry_import', 'approval_status', 'event_assignment', 'conflict_marker'],
                    requiredFields: ['club_id', 'person_reference', 'started_at', 'ended_at', 'activity_type'],
                    optionalFields: ['event_id', 'team_id', 'approval_reference', 'notes'],
                    syncModel: 'cursor',
                ),
            ],
        ];
    }

    private static function contract(
        string $key,
        string $label,
        string $direction,
        array $domains,
        array $formats,
        array $capabilities,
        array $requiredFields,
        array $optionalFields,
        string $syncModel,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'direction' => $direction,
            'domains' => $domains,
            'formats' => $formats,
            'capabilities' => $capabilities,
            'required_fields' => $requiredFields,
            'optional_fields' => $optionalFields,
            'sync_model' => $syncModel,
            'tenant_scoped' => true,
            'retry_policy' => 'bounded_exponential_backoff',
            'failure_states' => ['conflict', 'duplicate', 'quarantined', 'retryable_error', 'permanent_error'],
        ];
    }
}
