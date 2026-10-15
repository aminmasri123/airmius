<?php

namespace App\Support;

final class MandatoryRegressionJourneyRegistry
{
    public const CONTRACT = 'mandatory-regression-journeys.v1';

    public static function definitions(): array
    {
        return [
            'T37-01' => self::journey('Neuer Sportler', [
                'tests/Feature/RegistrationTest.php',
                'tests/Feature/EmailVerificationTest.php',
                'tests/Feature/ClubMembershipApplicationFlowTest.php',
            ], ['multi_account', 'push_delivery']),
            'T37-02' => self::journey('Rechnung bis Zahlung', [
                'tests/Feature/NotificationRoutingContractTest.php',
                'tests/Feature/MemberBillingInvoiceAccessTest.php',
                'tests/Feature/ClubPartialPaymentTest.php',
                'tests/Feature/MobileDeepLinkContractTest.php',
            ], ['real_push', 'pdf_rendering']),
            'T37-03' => self::journey('Umgekehrte Plattformen', [
                'tests/Feature/ClubMembershipInvoiceWorkflowTest.php',
                'tests/Feature/PaymentMvpFlowTest.php',
                'tests/Feature/MobileClubMembershipParityApiTest.php',
            ], ['real_device']),
            'T37-04' => self::journey('Teamisolation', [
                'tests/Feature/ClubWorkspaceIsolationTest.php',
                'tests/Feature/MultiClubPlatformIsolationContractTest.php',
            ], ['two_club_accounts']),
            'T37-05' => self::journey('Zusammenarbeit', [
                'tests/Feature/ClubTaskApiTest.php',
                'tests/Frontend/clubCalendar.test.mjs',
            ], ['two_devices', 'attachment_download']),
            'T37-06' => self::journey('Kalendernavigation', [
                'tests/Feature/ClubMemberFamilyCalendarReadinessCatalogTest.php',
                'tests/Frontend/clubCalendar.test.mjs',
            ], ['browser_history', 'native_navigation']),
            'T37-07' => self::journey('Mobile Lesbarkeit', [
                'tests/Unit/InputUsabilityPhase35ContractTest.php',
                'mobile/airmius_mobile/test/widget_test.dart',
            ], ['large_text', 'small_display']),
            'T37-08' => self::journey('Zweiter Verein', [
                'tests/Feature/MultiClubPlatformIsolationContractTest.php',
                'tests/Unit/ClubPrivacyBoundaryPhase34ContractTest.php',
            ], ['two_club_accounts', 'offline_cache']),
            'T37-09' => self::journey('Import bis Nutzung', [
                'tests/Feature/ClubMembershipImportFlowTest.php',
                'tests/Feature/MobileClubExternalInvitationApiTest.php',
                'tests/Feature/ClubMemberCardTest.php',
            ], ['email_delivery']),
            'T37-10' => self::journey('Trainerablauf', [
                'tests/Feature/TrainingPlanApiCrudTest.php',
                'tests/Feature/TrainingLogApiCrudTest.php',
                'tests/Feature/TrainingWorkflowIntegrationTest.php',
            ], ['trainer_athlete_accounts']),
            'T37-11' => self::journey('Sponsorablauf', [
                'tests/Feature/SponsorProfileParityTest.php',
                'tests/Feature/SponsorAgencyWorkspaceContractTest.php',
            ], ['controlled_impression']),
            'T37-12' => self::journey('Elternablauf', [
                'tests/Feature/GuardianAccessFlowTest.php',
                'tests/Feature/GuardianMultiRelationshipPrivacyRegressionTest.php',
            ], ['minor_and_guardian_accounts']),
            'T37-13' => self::journey('Vereinsloeschung zuruecknehmen', [
                'tests/Feature/ClubDeletionLifecycleTest.php',
                'docs/club-deletion-lifecycle.md',
            ], ['scheduled_time_travel']),
            'T37-14' => self::journey('Administrator nativ', [
                'tests/Feature/NativeAdminParityTest.php',
                'tests/Feature/AdminAreaSecurityTest.php',
            ], ['super_admin_and_scoped_admin']),
            'T37-15' => self::journey('Kauf bis Erstattung', [
                'tests/Feature/PaymentMvpFlowTest.php',
                'tests/Feature/CommerceRefundReconciliationTest.php',
            ], ['test_payment_provider']),
        ];
    }

    private static function journey(string $name, array $automatedEvidence, array $manualGates): array
    {
        return [
            'name' => $name,
            'platforms' => ['web', 'app'],
            'automated_evidence' => $automatedEvidence,
            'manual_gates' => $manualGates,
        ];
    }
}
