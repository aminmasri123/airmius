<?php

namespace App\Support;

final class CrossDeviceAcceptanceRegistry
{
    public const CONTRACT = 'cross-device-experience.v1';

    public const MOBILE_VERSION = '1.0.37+122';

    public const PLATFORM_KEYS = ['web_mobile', 'web_desktop', 'android', 'ios'];

    public const LOCALE_KEYS = ['de', 'en', 'fr', 'ar_rtl'];

    public const JOURNEY_KEYS = [
        'guest_discovery_checkout',
        'auth_secure_session',
        'push_and_deep_links',
        'event_files_and_training',
        'route_training_event',
        'recruiting_handoff',
        'refund_payout_reconciliation',
        'privacy_accessibility',
    ];

    public const ASSISTIVE_KEYS = [
        'keyboard_only',
        'nvda_or_jaws',
        'voiceover',
        'talkback',
        'zoom_text_200_400',
        'reflow_focus_touch',
        'forced_colors_reduced_motion',
    ];

    public const MOBILE_GATE_IDS = [
        'android_release_build',
        'ios_release_build',
        'screenshots',
        'real_device_smoke',
        'real_api_qa',
        'localization_qa',
        'secure_token_storage',
    ];

    /** @return array<string, mixed> */
    public static function definitions(): array
    {
        return [
            'contract' => self::CONTRACT,
            'release_version' => ReleaseReadinessReport::VERSION,
            'mobile_version' => self::MOBILE_VERSION,
            'platforms' => [
                'web_mobile' => ['owner' => 'Frontend / QA', 'real_device_required' => false, 'viewports' => ['360x800', '390x844']],
                'web_desktop' => ['owner' => 'Frontend / QA', 'real_device_required' => false, 'viewports' => ['1280x720', '1440x900']],
                'android' => ['owner' => 'Mobile / QA', 'real_device_required' => true, 'assistive_technology' => 'TalkBack'],
                'ios' => ['owner' => 'Mobile / QA', 'real_device_required' => true, 'assistive_technology' => 'VoiceOver'],
            ],
            'locales' => [
                'de' => ['direction' => 'ltr', 'native_review_required' => true],
                'en' => ['direction' => 'ltr', 'native_review_required' => true],
                'fr' => ['direction' => 'ltr', 'native_review_required' => true, 'long_label_review_required' => true],
                'ar_rtl' => ['direction' => 'rtl', 'native_review_required' => true, 'semantic_back_translation_required' => true],
            ],
            'journeys' => self::JOURNEY_KEYS,
            'assistive_technology' => self::ASSISTIVE_KEYS,
            'real_device_checklist_keys' => collect(range(1, 19))
                ->map(static fn (int $index): string => sprintf('CDX-%02d-', $index))
                ->all(),
            'mobile_gate_ids' => self::MOBILE_GATE_IDS,
            'external_gate_ids' => [
                'mobile_real_devices',
                'native_localization_qa',
                'wcag_human_acceptance',
            ],
            'artifacts' => [
                'mobile/airmius_mobile/lib/airmius_app.dart',
                'mobile/airmius_mobile/lib/core/airmius_l10n.dart',
                'mobile/airmius_mobile/lib/core/airmius_accessibility_scope.dart',
                'mobile/airmius_mobile/test/widget_test.dart',
                'mobile/airmius_mobile/scripts/run_android_real_device_smoke.sh',
                'mobile/airmius_mobile/scripts/run_ios_real_device_smoke.sh',
                'mobile/airmius_mobile/scripts/assert_real_device_smoke_evidence.sh',
                'mobile/airmius_mobile/store_listing/release/real_device_smoke_test_runbook.md',
                'mobile/airmius_mobile/store_listing/localization/localization_release_matrix.md',
                'tests/Feature/GuestExperienceOptimizationTest.php',
                'tests/Feature/LocalizationAcceptanceContractTest.php',
                'tests/Feature/Wcag22AccessibilityContractTest.php',
                'resources/release/cross_device_evidence.template.json',
            ],
            'privacy' => [
                'stores_personal_data' => false,
                'stores_device_identifiers' => false,
                'stores_credentials' => false,
                'stores_raw_urls_or_paths' => false,
                'stores_free_text' => false,
                'reviewer_identity_only_in_authoritative_manifest' => true,
            ],
        ];
    }
}
