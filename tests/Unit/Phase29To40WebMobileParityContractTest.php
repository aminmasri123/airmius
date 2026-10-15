<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class Phase29To40WebMobileParityContractTest extends TestCase
{
    private function path(string $path): string
    {
        return dirname(__DIR__, 2).'/'.$path;
    }

    public function test_every_phase_has_shared_backend_web_and_native_evidence(): void
    {
        $evidence = [
            29 => ['tests/Unit/SponsorCampaignWebReadinessContractTest.php', 'tests/Unit/SponsorCampaignMobileReadinessContractTest.php'],
            30 => ['tests/Unit/MarketplacePhase30WebReadinessContractTest.php', 'tests/Unit/MarketplacePhase30MobileReadinessContractTest.php'],
            31 => ['tests/Unit/SubscriptionPhase31WebReadinessContractTest.php', 'tests/Unit/SubscriptionPhase31MobileReadinessContractTest.php'],
            32 => ['tests/Unit/GuardianPhase32WebReadinessContractTest.php', 'tests/Unit/GuardianPhase32MobileReadinessContractTest.php'],
            33 => ['routes/admin.php', 'tests/Unit/PlatformAdminPhase33SecurityContractTest.php'],
            34 => ['app/Support/ClubPermissions.php', 'tests/Unit/ClubPrivacyBoundaryPhase34ContractTest.php'],
            35 => ['resources/js/app.js', 'tests/Unit/InputUsabilityPhase35ContractTest.php'],
            36 => ['routes/api.php', 'tests/Unit/DeveloperTechnicalCompletenessPhase36ContractTest.php'],
            37 => ['app/Support/MandatoryRegressionJourneyRegistry.php', 'tests/Unit/MandatoryRegressionPhase37ContractTest.php'],
            38 => ['resources/js/Pages/Auth/Dashboard/Rides/Index.vue', 'tests/Unit/RideSharingPhase38ContractTest.php'],
            39 => ['resources/js/Pages/Auth/Dashboard/Support/Index.vue', 'tests/Unit/SupportHelpPhase39ContractTest.php'],
            40 => ['tests/Unit/PublicContentPhase40ContractTest.php', 'mobile/airmius_mobile/lib/screens/guest_portal_screen.dart'],
        ];

        self::assertSame(range(29, 40), array_keys($evidence));
        foreach ($evidence as $phase => $paths) {
            foreach ($paths as $path) {
                self::assertFileExists($this->path($path), "T{$phase}: {$path}");
            }
        }
    }

    public function test_mobile_public_content_has_native_privacy_and_legal_equivalents(): void
    {
        $guest = file_get_contents($this->path('mobile/airmius_mobile/lib/screens/guest_portal_screen.dart'));
        $legal = file_get_contents($this->path('mobile/airmius_mobile/lib/screens/legal_status_center_screen.dart'));
        $privacy = file_get_contents($this->path('mobile/airmius_mobile/lib/screens/privacy_consent_center_screen.dart'));

        foreach (['GuestClubDirectoryScreen', 'BlogMediaCenterScreen', 'SponsorsCenterScreen', 'LegalStatusCenterScreen'] as $screen) {
            self::assertStringContainsString($screen, $guest, $screen);
        }
        self::assertStringContainsString('PrivacyConsentCenterScreen', $legal);
        self::assertStringContainsString("'ads_personalization_consent'", $privacy);
        self::assertStringContainsString("'ads_measurement_consent'", $privacy);
        self::assertStringContainsString("'product_analytics_consent'", $privacy);
    }

    public function test_admin_workspace_links_real_specialist_modules(): void
    {
        $workspace = file_get_contents($this->path('mobile/airmius_mobile/lib/screens/admin_workspace_screen.dart'));
        foreach ([
            'AdminMembersScreen',
            'AdminClubsScreen',
            'AdminCommerceOperationsScreen',
            'AdminBackofficeScreen',
            'AdminMediaScreen',
            'EditorialManagementScreen',
            'SponsorManagementScreen',
            'AdminSupportTicketScreen',
            'AdminPlatformSettingsScreen',
        ] as $screen) {
            self::assertStringContainsString($screen, $workspace, $screen);
        }
    }
}
