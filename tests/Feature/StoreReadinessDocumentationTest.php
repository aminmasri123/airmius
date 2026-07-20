<?php

namespace Tests\Feature;

use Tests\TestCase;

class StoreReadinessDocumentationTest extends TestCase
{
    public function test_mobile_store_readiness_artifacts_are_present(): void
    {
        foreach ($this->requiredStoreReadinessFiles() as $path) {
            $this->assertFileExists(base_path($path), "Missing store-readiness artifact: {$path}");
        }
    }

    public function test_release_manifest_tracks_store_submission_gates(): void
    {
        $manifest = $this->releaseManifest();

        $this->assertSame('Airmius Mobile', $manifest['product']);
        $this->assertSame('1.0.0+1', $manifest['version']);
        $this->assertSame('com.airmius.app', $manifest['android_application_id']);
        $this->assertSame('com.airmius.app', $manifest['ios_bundle_id']);

        $gateIds = collect($manifest['gates'])->pluck('id')->all();

        foreach ([
            'logo_theme_asset_mapping',
            'screenshots',
            'legal_privacy',
            'store_review_account',
            'store_release_notes',
            'store_submission_readiness',
            'release_go_no_go',
        ] as $gateId) {
            $this->assertContains($gateId, $gateIds, "Release manifest is missing gate: {$gateId}");
        }
    }

    public function test_store_icons_have_required_dimensions(): void
    {
        $this->assertImageDimensions('public/icons/airmius-icon-192.png', 192, 192);
        $this->assertImageDimensions('public/icons/airmius-icon-512.png', 512, 512);
        $this->assertImageDimensions('public/icons/airmius-maskable-192.png', 192, 192);
        $this->assertImageDimensions('public/icons/airmius-maskable-512.png', 512, 512);
        $this->assertImageDimensions('mobile/airmius_mobile/android/app/src/main/res/mipmap-xxxhdpi/ic_launcher.png', 192, 192);
        $this->assertImageDimensions('mobile/airmius_mobile/ios/Runner/Assets.xcassets/AppIcon.appiconset/Icon-App-1024x1024@1x.png', 1024, 1024);
    }

    public function test_support_and_privacy_contacts_are_configured_for_store_forms(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('LEGAL_SUPPORT_EMAIL=support@airmius.com', $envExample);
        $this->assertStringContainsString('LEGAL_PRIVACY_EMAIL=datenschutz@airmius.com', $envExample);
        $this->assertStringContainsString('MAIL_SUPPORT_FROM_ADDRESS=support@airmius.com', $envExample);

        $this->assertSame('support@airmius.com', config('legal.support_email'));
        $this->assertSame('datenschutz@airmius.com', config('legal.privacy_email'));
    }

    public function test_store_listing_and_privacy_texts_are_not_empty_placeholders(): void
    {
        foreach ([
            'mobile/airmius_mobile/store_listing/play_store/de-DE/full_description.txt' => 40,
            'mobile/airmius_mobile/store_listing/play_store/de-DE/short_description.txt' => 10,
            'mobile/airmius_mobile/store_listing/app_store/de-DE/description.txt' => 40,
            'mobile/airmius_mobile/store_listing/app_store/de-DE/subtitle.txt' => 10,
            'mobile/airmius_mobile/store_listing/app_store/de-DE/promotional_text.txt' => 10,
            'mobile/airmius_mobile/store_listing/privacy/play_data_safety_draft.md' => 40,
            'mobile/airmius_mobile/store_listing/privacy/app_store_privacy_draft.md' => 40,
            'mobile/airmius_mobile/store_listing/screenshots/screenshot_capture_plan.md' => 40,
            'mobile/airmius_mobile/store_listing/release/store_review_account_runbook.md' => 40,
            'mobile/airmius_mobile/store_listing/release/store_release_notes.md' => 40,
        ] as $path => $minimumLength) {
            $contents = trim((string) file_get_contents(base_path($path)));

            $this->assertGreaterThan($minimumLength, mb_strlen($contents), "Store-readiness text is too short: {$path}");
            $this->assertStringNotContainsStringIgnoringCase('todo', $contents, "Store-readiness text contains TODO: {$path}");
            $this->assertStringNotContainsStringIgnoringCase('change-me', $contents, "Store-readiness text contains change-me: {$path}");
        }
    }

    private function requiredStoreReadinessFiles(): array
    {
        return [
            'mobile/airmius_mobile/pubspec.yaml',
            'mobile/airmius_mobile/android/app/build.gradle.kts',
            'mobile/airmius_mobile/android/key.properties.example',
            'mobile/airmius_mobile/android/app/src/main/AndroidManifest.xml',
            'mobile/airmius_mobile/android/app/src/main/res/mipmap-anydpi-v26/ic_launcher.xml',
            'mobile/airmius_mobile/android/app/src/main/res/mipmap-anydpi-v26/ic_launcher_round.xml',
            'mobile/airmius_mobile/ios/Runner/Info.plist',
            'mobile/airmius_mobile/ios/ExportOptions.plist.example',
            'mobile/airmius_mobile/ios/Runner/Assets.xcassets/AppIcon.appiconset/Contents.json',
            'mobile/airmius_mobile/store_listing/play_store/de-DE/full_description.txt',
            'mobile/airmius_mobile/store_listing/play_store/de-DE/short_description.txt',
            'mobile/airmius_mobile/store_listing/app_store/de-DE/description.txt',
            'mobile/airmius_mobile/store_listing/app_store/de-DE/subtitle.txt',
            'mobile/airmius_mobile/store_listing/app_store/de-DE/promotional_text.txt',
            'mobile/airmius_mobile/store_listing/privacy/play_data_safety_draft.md',
            'mobile/airmius_mobile/store_listing/privacy/app_store_privacy_draft.md',
            'mobile/airmius_mobile/store_listing/privacy/privacy_submission_matrix.md',
            'mobile/airmius_mobile/store_listing/screenshots/screenshot_capture_plan.md',
            'mobile/airmius_mobile/store_listing/release/release_evidence_manifest.json',
            'mobile/airmius_mobile/store_listing/release/store_submission_readiness_runbook.md',
            'mobile/airmius_mobile/store_listing/release/store_review_account_runbook.md',
            'mobile/airmius_mobile/store_listing/release/store_release_notes.md',
            'mobile/airmius_mobile/store_listing/release/manual_gate_signoff_template.md',
            'mobile/airmius_mobile/store_listing/release/manual_evidence_gates.md',
        ];
    }

    private function releaseManifest(): array
    {
        $contents = file_get_contents(base_path('mobile/airmius_mobile/store_listing/release/release_evidence_manifest.json'));
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', (string) $contents);

        return json_decode((string) $contents, true, flags: JSON_THROW_ON_ERROR);
    }

    private function assertImageDimensions(string $path, int $width, int $height): void
    {
        $size = getimagesize(base_path($path));

        $this->assertIsArray($size, "Image is unreadable: {$path}");
        $this->assertSame($width, $size[0], "Unexpected image width: {$path}");
        $this->assertSame($height, $size[1], "Unexpected image height: {$path}");
    }
}
