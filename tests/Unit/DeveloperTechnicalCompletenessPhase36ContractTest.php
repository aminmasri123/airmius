<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DeveloperTechnicalCompletenessPhase36ContractTest extends TestCase
{
    public function test_all_phase_36_cases_are_present_in_the_technical_inventory(): void
    {
        $checklist = file_get_contents(__DIR__.'/../../docs/Airmius_Testcheckliste_Web_App_2026-09-29.md');
        $inventory = file_get_contents(__DIR__.'/../../docs/Airmius_Testinventar_2026-09-29.md');
        $phaseReport = file_get_contents(__DIR__.'/../../docs/PHASE_36_TECHNICAL_COMPLETENESS_2026-10-15.md');

        foreach (range(1, 15) as $number) {
            $id = sprintf('T36-%02d', $number);
            self::assertStringContainsString($id, $checklist);
            self::assertStringContainsString($id, $phaseReport);
        }

        self::assertStringContainsString('airmius:dispatch-domain-outbox', $inventory);
        self::assertStringContainsString('chat.conversation.{conversation}', $inventory);
    }

    public function test_every_scheduled_command_uses_an_overlap_lock(): void
    {
        $console = file_get_contents(__DIR__.'/../../routes/console.php');

        preg_match_all('/Schedule::command\(/', $console, $commands);
        preg_match_all('/->withoutOverlapping\(/', $console, $locks);

        self::assertNotEmpty($commands[0]);
        self::assertCount(count($commands[0]), $locks[0]);
    }

    public function test_delivery_recovery_and_provider_contracts_exist(): void
    {
        foreach ([
            'app/Console/Commands/DispatchDomainOutboxEvents.php',
            'app/Jobs/PublishDomainOutboxEvent.php',
            'tests/Feature/CrossModuleOutboxTest.php',
            'tests/Feature/PaymentWebhookSignatureTest.php',
            'tests/Feature/ProviderWebhookAndBankIdempotencyTest.php',
            'tests/Feature/DatabaseBackupRestoreTest.php',
            'tests/Feature/HttpDeliveryContractTest.php',
            'tests/Unit/RequestPerformanceTelemetryTest.php',
        ] as $path) {
            self::assertFileExists(__DIR__.'/../../'.$path, $path);
        }
    }

    public function test_realtime_status_is_recipient_scoped_in_backend_and_web(): void
    {
        $channels = file_get_contents(__DIR__.'/../../routes/channels.php');
        $event = file_get_contents(__DIR__.'/../../app/Events/UserStatusUpdated.php');
        $layout = file_get_contents(__DIR__.'/../../resources/js/Components/Auth/Layouts/AppLayout.vue');

        self::assertStringContainsString("Broadcast::channel('users.status.{userId}'", $channels);
        self::assertStringNotContainsString("Broadcast::channel('users.status'", $channels);
        self::assertStringContainsString("new PrivateChannel('users.status.'.\$userId)", $event);
        self::assertStringContainsString('.private(`users.status.${page.props.auth.user.id}`)', $layout);
        self::assertStringNotContainsString(".join('users.status')", $layout);
    }

    public function test_mobile_sync_external_api_and_technical_access_are_guarded(): void
    {
        $api = file_get_contents(__DIR__.'/../../routes/api.php');
        $external = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/ExternalClubMemberController.php');
        $bootstrap = file_get_contents(__DIR__.'/../../bootstrap/app.php');

        self::assertStringContainsString("Route::match(['get', 'post'], '/mobile/sync'", $api);
        self::assertStringContainsString("Route::prefix('/external')", $api);
        self::assertStringContainsString('tokenCan($ability)', $external);
        self::assertStringContainsString('ClubPermissions::allows($club', $external);
        self::assertStringContainsString("health: '/up'", $bootstrap);
        self::assertStringContainsString('EnsureApiCorsHeaders::class', $bootstrap);
        self::assertStringContainsString('validateCsrfTokens(except:', $bootstrap);
    }
}
