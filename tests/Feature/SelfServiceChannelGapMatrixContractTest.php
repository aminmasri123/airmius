<?php

namespace Tests\Feature;

use Tests\TestCase;

class SelfServiceChannelGapMatrixContractTest extends TestCase
{
    public function test_gap_matrix_is_backed_by_web_api_app_and_regression_evidence(): void
    {
        $matrix = $this->read('docs/SELF_SERVICE_WEB_API_APP_GAP_MATRIX.md');

        foreach ([
            'Mitglieder-Self-Service',
            'Rechnungen und Zahlungsstatus',
            'Buchungen / Ressourcen',
            'Benachrichtigungen',
            'Dokumente und Dateien',
            'Digitale Mitgliedskarte',
        ] as $area) {
            $this->assertStringContainsString('| '.$area.' |', $matrix, "{$area} fehlt in der Matrix.");
        }

        $this->assertStringContainsString('Hauptcheckliste bleibt absichtlich unveraendert', $matrix);
        $this->assertStringContainsString('php artisan test --filter=SelfServiceChannelGapMatrixContractTest', $matrix);

        $this->assertEvidenceExists([
            'routes/auth.php' => [
                "Route::get('/settings'",
                "Route::get('/club-memberships'",
                "Route::post('/clubs/{club}/membership/{user}/invoices'",
                "Route::get('/club-inventory'",
                "Route::get('/files'",
                "Route::get('/notifications'",
            ],
            'routes/api.php' => [
                "Route::get('/settings'",
                "Route::get('/portal'",
                "Route::get('/portal/{section}'",
                "Route::get('/billing/invoices'",
                "Route::get('/notifications'",
                "Route::get('/clubs/{club}/policy-documents'",
                "Route::get('/clubs/{club}/inventory'",
                "Route::post('/clubs/{club}/inventory/{item}/checkout'",
                "Route::get('/clubs/{club}/member-card'",
                "Route::post('/clubs/{club}/member-card/verify'",
                "Route::post('/clubs/{club}/finance-entries'",
                "Route::get('/files'",
            ],
            'app/Http/Controllers/Api/V1/SettingsController.php' => [
                'public function invoices',
                'public function invoice',
            ],
            'app/Http/Controllers/Api/V1/MemberPortalController.php' => [
                'final class MemberPortalController',
                'public function show',
                'public function details',
            ],
            'app/Http/Controllers/Api/V1/NotificationController.php' => [
                'class NotificationController',
                'public function show',
            ],
            'app/Http/Controllers/Api/V1/ClubInventoryController.php' => [
                'public function checkout',
                'public function recordMovement',
            ],
            'app/Http/Controllers/Api/V1/ClubMemberCardController.php' => [
                'public function show',
                'public function rotate',
                'public function verify',
            ],
            'app/Http/Controllers/FileController.php' => [
                'public function store',
                'public function download',
            ],
        ]);

        $this->assertFilesExist([
            'mobile/airmius_mobile/lib/screens/settings_center_screen.dart',
            'mobile/airmius_mobile/lib/screens/profile_screen.dart',
            'mobile/airmius_mobile/lib/screens/billing_detail_screen.dart',
            'mobile/airmius_mobile/lib/screens/club_member_finance_screen.dart',
            'mobile/airmius_mobile/lib/screens/club_asset_inventory_checkout_suite_screen.dart',
            'mobile/airmius_mobile/lib/screens/facility_booking_resource_scheduler_suite_screen.dart',
            'mobile/airmius_mobile/lib/screens/notifications_center_screen.dart',
            'mobile/airmius_mobile/lib/screens/notification_preferences_screen.dart',
            'mobile/airmius_mobile/lib/screens/file_manager_screen.dart',
            'mobile/airmius_mobile/lib/screens/club_policy_documents_screen.dart',
            'mobile/airmius_mobile/lib/screens/member_card_screen.dart',
            'mobile/airmius_mobile/lib/screens/member_card_qr_scanner_screen.dart',
            'tests/Feature/MemberPortalOverviewApiTest.php',
            'tests/Feature/MobileClubMembershipParityApiTest.php',
            'tests/Feature/ClubMembershipInvoiceWorkflowTest.php',
            'tests/Feature/ClubInventoryApiTest.php',
            'tests/Feature/NotificationCenterFeatureTest.php',
            'tests/Feature/NotificationRoutingContractTest.php',
            'tests/Feature/FileManagerFeatureTest.php',
            'tests/Feature/ClubPolicyDocumentTest.php',
            'tests/Feature/ClubMemberCardTest.php',
            'tests/Frontend/clubPolicyDocumentsRender.test.mjs',
        ]);
    }

    private function assertEvidenceExists(array $files): void
    {
        foreach ($files as $path => $needles) {
            $content = $this->read($path);

            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $content, "{$needle} fehlt in {$path}.");
            }
        }
    }

    private function assertFilesExist(array $paths): void
    {
        foreach ($paths as $path) {
            $this->assertFileExists(base_path($path), "{$path} fehlt.");
        }
    }

    private function read(string $path): string
    {
        $fullPath = base_path($path);
        $this->assertFileExists($fullPath, "{$path} fehlt.");

        return file_get_contents($fullPath);
    }
}
