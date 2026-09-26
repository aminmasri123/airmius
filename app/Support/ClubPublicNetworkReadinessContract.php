<?php

namespace App\Support;

final class ClubPublicNetworkReadinessContract
{
    public const VERSION = '2026-09-26.club-public-network-readiness.v1';

    public static function forClient(): array
    {
        return [
            'version' => self::VERSION,
            'public_profiles' => [
                'optional_publication_only' => true,
                'separate_public_projection_required' => true,
                'consent_required_for_people_profiles' => true,
                'club_source' => 'Club::visibleTo + PublicClubController',
                'trainer_source' => 'approved UserRoleApplication::trainer + public projection',
                'athlete_source' => 'explicit athlete publication profile',
                'internal_fields_excluded' => [
                    'member_records',
                    'billing',
                    'private_files',
                    'role_assignments',
                    'guardian_data',
                    'support_notes',
                ],
                'withdrawal_unpublishes_projection' => true,
            ],
            'trainer_discovery' => [
                'uses_published_projection_only' => true,
                'trial_training_requires_opt_in' => true,
                'open_team_slots_source' => 'published_team_capacity_projection',
                'contact_flow' => 'public_contact_request',
                'spam_protection' => [
                    'public_self_service_throttle',
                    'idempotency_key',
                    'honeypot_or_turnstile_ready',
                    'moderation_queue_for_abuse',
                ],
                'no_private_schedule_or_roster_exposure' => true,
            ],
            'cross_club_cooperation' => [
                'requires_bilateral_approval' => true,
                'requires_data_sharing_agreement' => true,
                'responsibilities_are_separate_per_club' => true,
                'event_visibility_uses_published_fields_only' => true,
                'participant_lists_remain_private_to_authorized_clubs' => true,
                'revocation_stops_future_cross_club_sync' => true,
                'audit_events' => [
                    'drafted',
                    'approved_by_origin_club',
                    'approved_by_partner_club',
                    'agreement_attached',
                    'published',
                    'revoked',
                ],
            ],
            'sponsor_contact' => [
                'opt_in_initiation_only' => true,
                'public_sponsor_source' => 'Sponsor::publiclyVerified',
                'club_contact_source' => 'public_contact_request',
                'internal_crm_hidden' => true,
                'member_data_never_disclosed' => true,
                'club_decides_response' => true,
                'rate_limited_public_endpoint' => true,
                'commercial_intent_logged_without_private_notes' => true,
            ],
            'public_internal_isolation' => [
                'public_projection_only' => true,
                'negative_tests_required' => [
                    'foreign_club_ids_return_forbidden_or_not_found',
                    'private_files_not_in_public_search',
                    'private_cache_payloads_not_shared',
                    'notifications_resolve_recipients_by_tenant',
                    'broadcast_channels_require_authorizer',
                ],
                'local_contract_sources' => [
                    'MultiClubPlatformIsolationReport',
                    'ExternalApiContractTest',
                    'PublicDiscoverySeoTest',
                    'SupportCenterWebTest',
                    'ClubSponsorPermissionsTest',
                ],
                'external_security_review' => 'pending',
                'production_cache_review' => 'pending',
                'independent_acceptance_required_before_release' => true,
            ],
            'decision' => 'local_contract_ready_external_security_gates_open',
        ];
    }
}
