<?php

namespace App\Support;

final class MultiClubPlatformIsolationReport
{
    public const VERSION = '2026-09-26.multi-club-isolation.v1';

    public static function make(): array
    {
        return [
            'version' => self::VERSION,
            'domains' => [
                self::domain('models', [
                    'club_id_or_membership_scope_required',
                    'foreign_record_ids_return_not_found_or_forbidden',
                    'role_assignments_resolve_effective_scope_per_club_department_team',
                ]),
                self::domain('files', [
                    'file_policy_authorizes_object_access',
                    'signed_downloads_are_short_lived_and_purpose_bound',
                    'personal_files_do_not_unlock_club_files',
                ]),
                self::domain('search', [
                    'global_search_filters_by_effective_permissions',
                    'public_search_uses_published_projection_only',
                    'private_files_and_invoices_require_authenticated_scope',
                ]),
                self::domain('queue_jobs', [
                    'jobs_store_tenant_scope',
                    'idempotency_keys_are_tenant_bound',
                    'manual_retry_requires_permission',
                ]),
                self::domain('caches', [
                    'public_cache_contains_only_published_projection',
                    'tenant_private_payloads_are_not_shared_across_clubs',
                    'release_evidence_never_stores_cache_values',
                ]),
                self::domain('exports', [
                    'external_api_uses_club_url_scope',
                    'export_contracts_include_tenant_meta',
                    'privacy_exports_apply_existing_subject_rights',
                ]),
                self::domain('notifications', [
                    'recipients_resolved_from_active_membership_and_permission',
                    'cross_club_recipient_lists_are_not_materialized_in_payloads',
                    'explicit_denials_override_legacy_roles',
                ]),
                self::domain('broadcast_channels', [
                    'private_channels_authorize_current_membership_or_object_access',
                    'club_event_channels_reject_foreign_tenant_members',
                    'channel_names_do_not_grant_access_without_authorizer',
                ]),
            ],
            'public_internal_split' => [
                'public_projection_only' => true,
                'internal_identifiers_hidden_from_public_payloads' => true,
                'private_contact_billing_and_notes_excluded' => true,
                'public_contact_requires_opt_in_flow' => true,
            ],
            'external_gates' => [
                'independent_security_review' => 'pending',
                'production_cache_review' => 'pending',
                'real_provider_webhook_review' => 'pending',
            ],
            'decision' => 'local_contract_ready_external_security_gates_open',
        ];
    }

    private static function domain(string $key, array $checks): array
    {
        return [
            'key' => $key,
            'status' => 'covered_by_local_contract',
            'checks' => $checks,
        ];
    }
}
