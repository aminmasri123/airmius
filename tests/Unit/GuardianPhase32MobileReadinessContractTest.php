<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class GuardianPhase32MobileReadinessContractTest extends TestCase
{
    public function test_mobile_guardian_center_uses_protected_api_lifecycle(): void
    {
        $root = dirname(__DIR__, 2);
        $screen = file_get_contents($root.'/mobile/airmius_mobile/lib/screens/guardian_center_screen.dart');
        $client = file_get_contents($root.'/mobile/airmius_mobile/lib/core/airmius_api_client.dart');
        $routes = file_get_contents($root.'/routes/api.php');

        self::assertStringContainsString('guardianChildren()', $screen);
        self::assertStringContainsString('guardianInvitations()', $screen);
        self::assertStringContainsString('approveGuardianChild', $screen);
        self::assertStringContainsString('revokeGuardianChild', $screen);
        self::assertStringContainsString('acceptGuardianInvitation', $screen);
        self::assertStringContainsString('declineGuardianInvitation', $screen);
        self::assertStringContainsString("'/api/v1/guardian/children'", $client);
        self::assertStringContainsString("'/guardian/children'", $routes);
        self::assertStringContainsString("'/guardian/invitations'", $routes);
    }
}
