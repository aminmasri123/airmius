<?php

namespace App\Support;

use App\Services\PlanFeatureService;

final class ClubModuleSettingsContract
{
    public const VERSION = '2026-09-26.club-module-settings.v1';

    public static function forClient(): array
    {
        return [
            'version' => self::VERSION,
            'defaults' => [
                'club_is_listed' => true,
                'teams_are_listed' => true,
                'members_can_post_to_club' => true,
                'members_can_post_to_teams' => true,
                'contact_details_public' => false,
                'letterhead' => [
                    'show_logo' => true,
                    'header' => null,
                    'address_line' => null,
                    'footer' => null,
                ],
                'brand_colors_fallback_to_airmius_theme' => true,
            ],
            'governance' => [
                'module_catalog_source' => PlatformModuleRegistry::class,
                'plan_gate_source' => PlanFeatureService::class,
                'profile_rights_source' => ClubProfilePermissions::class,
                'api_payload' => 'ClubResource.subscription_capabilities',
                'unknown_features_default_allowed' => true,
                'write_actions_require_server_authorization' => true,
                'explicit_denials_override_legacy_roles' => true,
            ],
            'module_policy' => collect(PlatformModuleRegistry::definitions())
                ->map(fn (array $module, string $key): array => [
                    'key' => $key,
                    'domain' => $module['domain'],
                    'label_key' => $module['label_key'],
                    'requires_plan_check' => in_array($key, [
                        'members',
                        'events',
                        'training',
                        'files',
                        'sponsors',
                        'subscriptions',
                        'marketplace',
                    ], true),
                    'requires_permission_check' => ! in_array($key, [
                        'account',
                        'notifications',
                        'feed_story',
                        'blog',
                    ], true),
                    'fallback_behavior' => 'hide_or_read_only_when_unavailable',
                ])
                ->values()
                ->all(),
            'feature_minimum_plans' => [
                'member_import' => 'starter',
                'invoices' => 'starter',
                'payment_tracking' => 'starter',
                'member_onboarding' => 'starter',
                'exercise_library_custom' => 'starter',
                'club_cockpit' => 'club',
                'trainer_cockpit' => 'club',
                'attendance_qr' => 'club',
                'payment_reminders' => 'club',
                'sponsors' => 'club',
                'advanced_roles' => 'club',
                'season_planning' => 'pro',
                'facility_booking' => 'pro',
                'material_management' => 'pro',
                'sepa_export' => 'pro',
                'bank_reconciliation' => 'pro',
                'datev_export' => 'pro',
                'sponsor_crm' => 'elite',
                'api' => 'elite',
            ],
            'terminology' => [
                'source' => 'club metadata and membership application document type labels',
                'locales' => ['de', 'en', 'fr', 'ar'],
                'fallback_order' => ['requested_locale', 'de', 'en', 'key'],
                'server_validates_required_german_label' => true,
                'missing_optional_locale_falls_back' => true,
            ],
            'appearance' => [
                'palette_fields' => ['brand_primary_color', 'brand_secondary_color', 'brand_accent_color'],
                'color_format' => '#RRGGBB',
                'document_template_limit' => 20,
                'one_default_template_per_type' => true,
                'private_document_templates_only_for_branding_editors' => true,
                'public_palette_allowed' => true,
            ],
            'decision' => 'local_contract_ready',
        ];
    }
}
