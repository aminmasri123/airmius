<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SupportHelpPhase39ContractTest extends TestCase
{
    public function test_phase_39_documentation_covers_every_scenario(): void
    {
        $report = file_get_contents(__DIR__.'/../../docs/PHASE_39_SUPPORT_HELP_2026-10-15.md');

        foreach (range(1, 7) as $number) {
            self::assertStringContainsString(sprintf('T39-%02d', $number), $report);
        }
    }

    public function test_support_routes_expose_private_conversation_without_admin_only_entry(): void
    {
        $routes = file_get_contents(__DIR__.'/../../routes/api.php');
        $webRoutes = file_get_contents(__DIR__.'/../../routes/auth.php');

        self::assertStringContainsString("Route::get('/support'", $webRoutes);
        self::assertStringContainsString("Route::get('/support/tickets/{supportTicket}'", $routes);
        self::assertStringContainsString("Route::post('/support/tickets/{supportTicket}/messages'", $routes);
    }

    public function test_backend_enforces_ownership_scope_confidentiality_and_notifications(): void
    {
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/SupportTicketController.php');

        self::assertStringContainsString('authorizeTicketConversation(', $controller);
        self::assertStringContainsString('scopeAllowsTicket($scope, $ticket)', $controller);
        self::assertStringContainsString("'is_confidential' => \$data['category'] === 'safety'", $controller);
        self::assertStringContainsString("'support.ticket_reply'", $controller);
        self::assertStringContainsString("'support.requester_reply'", $controller);
        self::assertStringContainsString("abort_if(\$supportTicket->status === 'closed'", $controller);
    }

    public function test_web_and_mobile_offer_ticket_details_and_replies(): void
    {
        $web = file_get_contents(__DIR__.'/../../resources/js/Pages/Auth/Dashboard/Support/Index.vue');
        $mobile = file_get_contents(__DIR__.'/../../mobile/airmius_mobile/lib/screens/support_helpdesk_screen.dart');
        $client = file_get_contents(__DIR__.'/../../mobile/airmius_mobile/lib/core/airmius_api_client.dart');

        self::assertStringContainsString('toggleConversation', $web);
        self::assertStringContainsString('sendReply', $web);
        self::assertStringContainsString("form.category === 'safety'", $web);
        self::assertStringContainsString('_openTicket(', $mobile);
        self::assertStringContainsString("'safety'", $mobile);
        self::assertStringContainsString('replySupportTicket(', $client);
    }
}
