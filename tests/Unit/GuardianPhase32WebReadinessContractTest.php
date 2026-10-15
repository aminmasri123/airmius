<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class GuardianPhase32WebReadinessContractTest extends TestCase
{
    public function test_parent_access_and_minor_protection_contracts_are_present(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = file_get_contents($root.'/routes/web.php');
        $access = file_get_contents($root.'/app/Http/Controllers/GuardianAccessController.php');
        $consent = file_get_contents($root.'/app/Http/Controllers/GuardianConsentController.php');
        $state = file_get_contents($root.'/app/Support/GuardianConsentState.php');
        $safety = file_get_contents($root.'/app/Support/MinorSafety.php');
        $relationships = file_get_contents($root.'/app/Services/GuardianChildRelationshipService.php');

        self::assertStringContainsString("'/eltern-login'", $routes);
        self::assertStringContainsString("where('expires_at', '>=', now())", $access);
        self::assertStringContainsString("guardian_consent_requested_at', '>=', \$requestedAfter", $consent);
        self::assertStringContainsString("'guardian_consent_token' => Str::random(64)", $consent);
        self::assertStringContainsString('completeAgeTransition', $state);
        self::assertStringContainsString("removeRole('minor_pending_consent')", $state);
        self::assertStringContainsString('canDirectMessage', $safety);
        self::assertStringContainsString('isGuardianPair', $safety);
        self::assertStringContainsString("assignRole(Role::findOrCreate('guardian', 'web'))", $relationships);
        self::assertStringContainsString('A guardian account must be at least 16 years old.', $relationships);
    }
}
