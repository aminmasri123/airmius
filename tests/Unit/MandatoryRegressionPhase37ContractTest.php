<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\V1\MobileDeepLinkController;
use App\Support\MandatoryRegressionJourneyRegistry;
use PHPUnit\Framework\TestCase;

class MandatoryRegressionPhase37ContractTest extends TestCase
{
    private function source(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }

    public function test_every_phase_37_journey_has_web_app_and_existing_evidence(): void
    {
        $definitions = MandatoryRegressionJourneyRegistry::definitions();
        $expected = array_map(fn (int $number) => sprintf('T37-%02d', $number), range(1, 15));

        self::assertSame($expected, array_keys($definitions));

        foreach ($definitions as $id => $journey) {
            self::assertSame(['web', 'app'], $journey['platforms'], $id);
            self::assertNotEmpty($journey['automated_evidence'], $id);
            self::assertNotEmpty($journey['manual_gates'], $id);

            foreach ($journey['automated_evidence'] as $path) {
                self::assertFileExists(dirname(__DIR__, 2).'/'.$path, $id.': '.$path);
            }
        }
    }

    public function test_phase_report_and_checklist_cover_all_journeys(): void
    {
        $checklist = $this->source('docs/Airmius_Testcheckliste_Web_App_2026-09-29.md');
        $report = $this->source('docs/PHASE_37_MANDATORY_JOURNEYS_2026-10-15.md');

        foreach (array_keys(MandatoryRegressionJourneyRegistry::definitions()) as $id) {
            self::assertStringContainsString($id, $checklist);
            self::assertStringContainsString($id, $report);
        }
    }

    public function test_invoice_notification_resolves_to_the_same_web_and_app_destination(): void
    {
        $routing = $this->source('app/Support/NotificationRouting.php');
        $resolver = $this->source('app/Http/Controllers/Api/V1/MobileDeepLinkController.php');
        $mobile = $this->source('mobile/airmius_mobile/lib/core/airmius_deep_links.dart');

        self::assertStringContainsString("'airmius://billing/invoices/'.\$invoiceId", $routing);
        self::assertStringContainsString("'billing' => \$this->billingTarget(\$segments, \$url)", $resolver);
        self::assertStringContainsString("'billing_invoice'", $resolver);
        self::assertStringContainsString("'/api/v1/billing/invoices/'.\$invoice", $resolver);
        self::assertStringContainsString("['club_invoice', 'subscription_invoice']", $resolver);
        self::assertStringContainsString("'/settings?tab=billing#billing'", $resolver);
        self::assertStringContainsString("AirmiusDeepLinkTargetType.billingInvoice", $mobile);
    }

    public function test_invoice_deep_link_resolution_preserves_kind_and_rejects_invalid_paths(): void
    {
        $method = new \ReflectionMethod(MobileDeepLinkController::class, 'targetFor');
        $controller = new MobileDeepLinkController;

        $target = $method->invoke($controller, 'airmius://billing/invoices/35?kind=subscription_invoice');
        self::assertTrue($target['recognized']);
        self::assertSame('billing_invoice', $target['key']);
        self::assertSame(['invoice' => '35', 'kind' => 'subscription_invoice'], $target['params']);
        self::assertSame('/api/v1/billing/invoices/35?kind=subscription_invoice', $target['api_endpoint']);
        self::assertSame('/settings?tab=billing#billing', $target['web_fallback_url']);

        $invalid = $method->invoke($controller, 'airmius://billing/invoices/not-a-number');
        self::assertFalse($invalid['recognized']);
        self::assertSame('unknown', $invalid['key']);
    }

    public function test_club_deletion_has_thirty_day_cancellation_and_governance_notification_contract(): void
    {
        $service = $this->source('app/Services/ClubDeletionService.php');

        self::assertStringContainsString('addDays(30)', $service);
        self::assertStringContainsString('public function cancel(', $service);
        self::assertStringContainsString('notificationSender(', $service);
        self::assertStringContainsString('ClubAuditLog::record(', $service);
    }
}
