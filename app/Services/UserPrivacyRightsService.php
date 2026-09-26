<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\OrganizationJobInterest;
use App\Models\User;
use App\Support\MinorSafety;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserPrivacyRightsService
{
    private const PROCESS_SCHEMA = 'airmius.privacy-rights-process.v1';

    private const PROCESSING_INVENTORY_SCHEMA = 'airmius.processing-activity-inventory.v1';

    private const CONSENT_FIELDS = [
        'ads_personalization' => 'ads_personalization_consent',
        'ads_measurement' => 'ads_measurement_consent',
        'product_analytics' => 'product_analytics_consent',
    ];

    private const RECRUITING_PROFILE_SHARING = 'recruiting_profile_sharing';

    public function correct(User $user, array $data): User
    {
        $fields = $this->correctionFields($data);

        if ($fields === []) {
            return $user->refresh();
        }

        return DB::transaction(function () use ($user, $fields) {
            $currentFirstName = $user->first_name;
            $currentLastName = $user->last_name;

            if (array_key_exists('first_name', $fields)) {
                $currentFirstName = trim((string) $fields['first_name']);
            }

            if (array_key_exists('last_name', $fields)) {
                $currentLastName = trim((string) $fields['last_name']);
            }

            if (array_key_exists('first_name', $fields) || array_key_exists('last_name', $fields)) {
                $fields['name'] = trim($currentFirstName.' '.$currentLastName);
            }

            if (array_key_exists('email', $fields) && ! hash_equals((string) $user->email, (string) $fields['email'])) {
                $fields['email_verified_at'] = null;
            }

            if (array_key_exists('country', $fields)) {
                $fields['country'] = Str::upper((string) $fields['country']);
            }

            if (MinorSafety::birthDateRequiresGuardianConsent($fields['birth_date'] ?? $user->birth_date)) {
                $fields = array_merge($fields, MinorSafety::privacyDefaults());
            }

            $user->forceFill($fields)->save();

            Activity::create([
                'user_id' => $user->id,
                'type' => 'privacy.profile_corrected',
                'data' => [
                    'fields' => array_values(array_diff(array_keys($fields), ['email_verified_at', 'name'])),
                    'corrected_at' => now()->toJSON(),
                ],
            ]);

            return $user->refresh();
        });
    }

    public function withdrawConsents(User $user, array $consents = []): array
    {
        $normalized = collect($consents)
            ->map(fn ($consent) => Str::lower((string) $consent))
            ->filter()
            ->unique()
            ->values();

        $selected = $normalized->isEmpty() || $normalized->contains('all')
            ? [...array_keys(self::CONSENT_FIELDS), self::RECRUITING_PROFILE_SHARING]
            : $normalized->filter(fn (string $consent) => array_key_exists($consent, self::CONSENT_FIELDS)
                || $consent === self::RECRUITING_PROFILE_SHARING)->values()->all();

        if ($selected === []) {
            return [];
        }

        DB::transaction(function () use ($user, $selected) {
            $updates = [];

            foreach ($selected as $consent) {
                if (isset(self::CONSENT_FIELDS[$consent])) {
                    $updates[self::CONSENT_FIELDS[$consent]] = false;
                }
            }

            if ($updates !== []) {
                $user->forceFill($updates)->save();
            }
            if (in_array(self::RECRUITING_PROFILE_SHARING, $selected, true)) {
                OrganizationJobInterest::query()
                    ->where('user_id', $user->id)
                    ->whereNotNull('profile_consent_at')
                    ->update([
                        'shared_profile_fields' => null,
                        'profile_consent_at' => null,
                    ]);
            }

            Activity::create([
                'user_id' => $user->id,
                'type' => 'privacy.consent_withdrawn',
                'data' => [
                    'consents' => array_values($selected),
                    'withdrawn_at' => now()->toJSON(),
                ],
            ]);
        });

        return array_values($selected);
    }

    public static function supportedConsents(): array
    {
        return [...array_keys(self::CONSENT_FIELDS), self::RECRUITING_PROFILE_SHARING];
    }

    public function rightsProcessMatrix(): array
    {
        return [
            'schema' => self::PROCESS_SCHEMA,
            'default_response_deadline_days' => 30,
            'identity_checks' => [
                'authenticated_session',
                'email_verification_code_for_erasure',
                'fresh_unique_email_for_correction',
            ],
            'audit_events' => [
                'privacy.profile_corrected',
                'privacy.consent_withdrawn',
                'privacy.restriction_case_opened',
                'privacy.restriction_case_decided',
                'privacy.objection_case_opened',
                'privacy.objection_case_decided',
                'privacy.data_erasure_code_sent',
                'privacy.data_erasure_completed',
            ],
            'case_controls' => [
                'deadline_clock' => 'created_at_plus_deadline_days',
                'identity_verification_required' => true,
                'decision_note_required' => true,
                'status_flow' => ['submitted', 'identity_pending', 'in_review', 'fulfilled', 'rejected', 'closed'],
            ],
            'cases' => [
                'access' => [
                    'right' => 'access',
                    'deadline_days' => 30,
                    'identity_check' => 'authenticated_session',
                    'channels' => [
                        'web_route' => 'auth.settings.privacy.export',
                        'api_route' => 'api.v1.privacy.export',
                    ],
                    'status' => 'implemented',
                    'evidence' => ['json_export_schema', 'route_listed_in_export', 'feature_test'],
                ],
                'rectification' => [
                    'right' => 'rectification',
                    'deadline_days' => 30,
                    'identity_check' => 'authenticated_session_with_unique_email_validation',
                    'channels' => [
                        'web_route' => 'auth.settings.privacy.correct',
                        'api_route' => 'api.v1.privacy.correct',
                    ],
                    'status' => 'implemented',
                    'evidence' => ['field_allowlist', 'email_reverification', 'activity_log'],
                ],
                'restriction' => [
                    'right' => 'restriction',
                    'deadline_days' => 30,
                    'identity_check' => 'authenticated_session_or_support_identity_review',
                    'channels' => [
                        'web_route' => 'auth.settings.privacy.withdraw-consents',
                        'api_route' => 'api.v1.privacy.withdraw-consents',
                        'case_queue' => 'privacy.support_case.restriction',
                    ],
                    'status' => 'implemented',
                    'evidence' => ['purpose_consent_withdrawal', 'recruiting_profile_sharing_withdrawal', 'formal_case_queue', 'deadline_clock', 'decision_note_required', 'activity_log'],
                ],
                'objection' => [
                    'right' => 'objection',
                    'deadline_days' => 30,
                    'identity_check' => 'authenticated_session_or_support_identity_review',
                    'channels' => [
                        'web_route' => 'auth.settings.privacy.withdraw-consents',
                        'api_route' => 'api.v1.privacy.withdraw-consents',
                        'case_queue' => 'privacy.support_case.objection',
                    ],
                    'status' => 'implemented',
                    'evidence' => ['ads_personalization_withdrawal', 'ads_measurement_withdrawal', 'product_analytics_withdrawal', 'formal_case_queue', 'deadline_clock', 'decision_note_required'],
                ],
                'erasure' => [
                    'right' => 'erasure',
                    'deadline_days' => 30,
                    'identity_check' => 'email_code_bound_to_selected_categories',
                    'channels' => [
                        'web_routes' => ['auth.settings.privacy.erasure', 'auth.settings.privacy.erasure.code', 'auth.settings.privacy.erasure.destroy'],
                        'api_routes' => ['api.v1.privacy.data-erasure.code', 'api.v1.privacy.data-erasure.destroy'],
                    ],
                    'status' => 'implemented',
                    'evidence' => ['category_allowlist', 'email_code_confirmation', 'retained_data_summary'],
                ],
            ],
        ];
    }

    public function processingActivityInventory(): array
    {
        return [
            'schema' => self::PROCESSING_INVENTORY_SCHEMA,
            'version' => 1,
            'published_on' => '2026-09-26',
            'controller' => [
                'name' => 'Airmius Plattformbetrieb',
                'role' => 'platform_controller',
            ],
            'club_boundary_controls' => [
                'tenant_key' => 'club_id',
                'member_scope' => 'authenticated_user_club_memberships_only',
                'cross_club_rule' => 'no_foreign_club_records_in_subject_exports_or_case_work',
                'inventory_payload_rule' => 'no_record_ids_no_names_no_free_text',
            ],
            'rights_controls' => [
                'process_schema' => self::PROCESS_SCHEMA,
                'rights' => ['access', 'rectification', 'restriction', 'objection', 'erasure'],
                'default_response_deadline_days' => 30,
                'identity_verification_required' => true,
            ],
            'activities' => [
                [
                    'key' => 'account_profile',
                    'processing_activity' => 'Account- und Profilverwaltung',
                    'purpose' => 'Registrierung, Anmeldung, Profilpflege, Rollen- und Sicherheitseinstellungen bereitstellen.',
                    'legal_basis' => ['gdpr_art_6_1_b_contract', 'gdpr_art_6_1_f_platform_security'],
                    'responsible_parties' => ['platform_operations', 'data_subject'],
                    'data_categories' => ['identity', 'contact', 'profile', 'security_settings', 'guardian_consent'],
                    'recipients' => ['platform_authorized_staff', 'email_delivery_provider_when_required'],
                    'retention_rule' => 'account_lifecycle_then_erasure_or_anonymization_after_confirmed_deletion_unless_legal_hold_applies',
                    'club_scope' => 'global_user_record_with_club_membership_links_only',
                ],
                [
                    'key' => 'club_membership',
                    'processing_activity' => 'Vereinsmitgliedschaft und Rollen im Verein',
                    'purpose' => 'Mitgliedschaft, Beitragsfähigkeit, Vereinsrollen, Mannschafts- und Abteilungszuordnung verwalten.',
                    'legal_basis' => ['gdpr_art_6_1_b_membership_contract', 'gdpr_art_6_1_c_statutory_records', 'gdpr_art_6_1_f_club_governance'],
                    'responsible_parties' => ['club_controller', 'authorized_club_role_holders'],
                    'data_categories' => ['membership_status', 'club_roles', 'department_assignment', 'team_assignment', 'member_numbers'],
                    'recipients' => ['authorized_club_role_holders', 'payment_provider_when_fees_apply'],
                    'retention_rule' => 'active_membership_then_former_member_retention_by_data_type_and_legal_hold',
                    'club_scope' => 'strict_same_club_only',
                ],
                [
                    'key' => 'communications_notifications',
                    'processing_activity' => 'Kommunikation, Benachrichtigungen und Zustellungen',
                    'purpose' => 'Systemnachrichten, Vereinsinformationen, Sicherheitsmeldungen und Zustellstatus ausliefern.',
                    'legal_basis' => ['gdpr_art_6_1_b_service_delivery', 'gdpr_art_6_1_f_operational_communication', 'gdpr_art_6_1_a_optional_newsletter_consent'],
                    'responsible_parties' => ['platform_operations', 'club_controller_for_club_messages'],
                    'data_categories' => ['contact', 'notification_preferences', 'delivery_status', 'message_metadata'],
                    'recipients' => ['email_delivery_provider', 'push_delivery_provider', 'authorized_club_senders'],
                    'retention_rule' => 'delivery_logs_limited_to_operational_evidence_then_purged_or_aggregated',
                    'club_scope' => 'club_messages_limited_to_sender_club_and_recipient_membership',
                ],
                [
                    'key' => 'payments_billing',
                    'processing_activity' => 'Zahlungen, Beiträge, Rechnungen und Spenden',
                    'purpose' => 'Mitgliedsbeiträge, Rechnungen, Zahlungen, Rückerstattungen, Spenden und Buchungsnachweise abwickeln.',
                    'legal_basis' => ['gdpr_art_6_1_b_payment_contract', 'gdpr_art_6_1_c_tax_and_accounting_obligations'],
                    'responsible_parties' => ['platform_operations', 'club_controller_for_club_finance'],
                    'data_categories' => ['billing_identity', 'payment_reference', 'invoice', 'booking_receipt', 'donation_metadata'],
                    'recipients' => ['payment_provider', 'accounting_exports_authorized_by_club', 'tax_authorities_when_required'],
                    'retention_rule' => 'statutory_accounting_retention_then_restricted_archive_or_erasure',
                    'club_scope' => 'finance_records_restricted_to_owning_club',
                ],
                [
                    'key' => 'content_files_training',
                    'processing_activity' => 'Inhalte, Dateien, Sport- und Trainingsdaten',
                    'purpose' => 'Beiträge, Dateien, Trainingsplanung, Sportprofile und Aktivitätsdaten im gewählten Sichtbarkeitsrahmen bereitstellen.',
                    'legal_basis' => ['gdpr_art_6_1_b_service_delivery', 'gdpr_art_6_1_a_optional_integration_consent', 'gdpr_art_6_1_f_abuse_prevention'],
                    'responsible_parties' => ['platform_operations', 'club_controller_for_club_content', 'data_subject'],
                    'data_categories' => ['content', 'files', 'sport_profile', 'training_metrics', 'integration_activity'],
                    'recipients' => ['authorized_viewers_by_visibility', 'sport_integration_provider_when_connected', 'moderation_staff_when_flagged'],
                    'retention_rule' => 'user_controlled_content_until_deletion_or_account_erasure_with_moderation_and_legal_hold_exceptions',
                    'club_scope' => 'visibility_and_team_membership_must_match_club_context',
                ],
                [
                    'key' => 'privacy_rights_cases',
                    'processing_activity' => 'Datenschutzrechte und Löschfallbearbeitung',
                    'purpose' => 'Auskunft, Berichtigung, Einschränkung, Widerspruch, Widerruf und Löschung fristgebunden bearbeiten.',
                    'legal_basis' => ['gdpr_art_6_1_c_legal_obligation', 'gdpr_art_6_1_f_auditability'],
                    'responsible_parties' => ['platform_privacy_team', 'club_controller_when_club_records_are_in_scope'],
                    'data_categories' => ['case_metadata', 'identity_check', 'decision_note', 'retention_summary', 'erasure_categories'],
                    'recipients' => ['privacy_team', 'authorized_club_contact_for_scoped_club_records'],
                    'retention_rule' => 'rights_case_evidence_retained_for_accountability_then_purged_after_limitation_period',
                    'club_scope' => 'case_work_may_reference_only_clubs_linked_to_the_requesting_subject',
                ],
            ],
        ];
    }

    private function correctionFields(array $data): array
    {
        $allowed = [
            'first_name',
            'last_name',
            'email',
            'country',
            'street',
            'house_number',
            'postal_code',
            'city',
            'state',
            'birth_date',
            'gender',
            'bio',
            'guardian_email',
            'athlete_license_number',
            'athlete_license_valid_until',
            'profile_visibility',
            'direct_message_privacy',
            'friend_request_privacy',
        ];

        return collect($data)
            ->only($allowed)
            ->mapWithKeys(function ($value, string $key) {
                if (is_string($value)) {
                    $value = trim($value);
                }

                return [$key => $value === '' ? null : $value];
            })
            ->all();
    }
}
