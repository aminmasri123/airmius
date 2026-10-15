<?php

namespace Tests\Unit;

use App\Models\CommerceOrder;
use App\Models\ConnectedSportAccount;
use App\Models\SocialAccount;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class ClubPrivacyBoundaryPhase34ContractTest extends TestCase
{
    private function source(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }

    public function test_authenticated_web_mutations_are_not_exempt_from_csrf_protection(): void
    {
        $bootstrap = $this->source('bootstrap/app.php');

        foreach ([
            "'checkout/subscriptions/*'",
            "'team-join-requests/*/approve'",
            "'team-join-requests/*/decline'",
            "'teams/*/members/*'",
            "'clubs/*/members/*'",
        ] as $unsafeExemption) {
            self::assertStringNotContainsString($unsafeExemption, $bootstrap);
        }

        self::assertStringContainsString("'webhooks/stripe'", $bootstrap);
        self::assertStringContainsString("'api/v1/auth/login'", $bootstrap);
    }

    public function test_tokens_and_session_secrets_are_not_serialized_or_logged(): void
    {
        self::assertContains('guardian_consent_token', (new User)->getHidden());
        self::assertContains('access_token', (new CommerceOrder)->getHidden());
        self::assertContains('access_token', (new SocialAccount)->getHidden());
        self::assertContains('refresh_token', (new SocialAccount)->getHidden());
        self::assertContains('access_token', (new ConnectedSportAccount)->getHidden());
        self::assertContains('refresh_token', (new ConnectedSportAccount)->getHidden());

        $bootstrap = $this->source('bootstrap/app.php');
        self::assertStringNotContainsString("'session_id' =>", $bootstrap);
        self::assertStringNotContainsString("'referer' =>", $bootstrap);
    }

    public function test_nested_membership_document_ids_are_scoped_to_the_parent_club(): void
    {
        foreach ([
            'app/Http/Controllers/ClubMembershipController.php',
            'app/Http/Controllers/Api/V1/ClubController.php',
        ] as $controller) {
            $source = $this->source($controller);
            self::assertStringContainsString("Rule::exists('files', 'id')->where('club_id', \$club->id)", $source);
        }
    }

    public function test_workspace_cache_realtime_and_output_boundaries_are_explicit(): void
    {
        $workspace = $this->source('app/Services/WorkspaceContextService.php');
        self::assertStringContainsString('->linkedToUser($user)', $workspace);
        self::assertStringContainsString("session()->forget('club_id')", $workspace);

        $apiCache = $this->source('app/Http/Middleware/EnforceApiCachePolicy.php');
        self::assertStringContainsString('private, no-store, no-cache, must-revalidate, max-age=0', $apiCache);

        $inertia = $this->source('app/Http/Middleware/HandleInertiaRequests.php');
        self::assertStringContainsString('no-store, no-cache, must-revalidate, private', $inertia);

        $files = $this->source('app/Http/Controllers/FileController.php');
        self::assertStringNotContainsString('private, max-age=86400', $files);
        self::assertGreaterThanOrEqual(2, substr_count($files, 'private, no-store, no-cache, max-age=0'));

        $uploads = $this->source('app/Http/Controllers/Api/V1/UploadController.php');
        self::assertStringNotContainsString('private, max-age=86400', $uploads);
        self::assertStringContainsString('private, no-store, no-cache, max-age=0', $uploads);

        $channels = $this->source('routes/channels.php');
        self::assertStringContainsString("Broadcast::channel('notifications.user.{userId}'", $channels);
        self::assertStringContainsString('(int) $user->id === $userId', $channels);
        self::assertStringContainsString("Broadcast::channel('events.club.{club}'", $channels);
        self::assertStringContainsString('ClubPermissions::allows($club, $user, ClubPermissions::EVENTS_EDIT)', $channels);

        $mobileContainer = $this->source('mobile/airmius_mobile/lib/core/airmius_service_container.dart');
        self::assertStringContainsString('await queuedTransport.clear()', $mobileContainer);
        self::assertStringContainsString('ownerUserId: _activeUserId', $mobileContainer);
        self::assertStringContainsString("['authorization', 'cookie'].contains(key.toLowerCase())", $mobileContainer);
        self::assertStringContainsString('item.ownerUserId != _activeUserId', $mobileContainer);
        self::assertStringContainsString("headers['Authorization'] = authorization", $mobileContainer);
    }

    public function test_plan_entitlements_permissions_and_rendering_security_remain_separate(): void
    {
        $planFeatures = $this->source('app/Services/PlanFeatureService.php');
        self::assertStringContainsString('public function ensureAllows(Club $club, string $feature', $planFeatures);

        $clubPermissions = $this->source('app/Support/ClubPermissions.php');
        self::assertStringContainsString('Club-scoped permissions', $clubPermissions);
        self::assertStringContainsString('public static function allows(', $clubPermissions);

        $sanitizer = $this->source('app/Support/BlogContentSanitizer.php');
        self::assertStringContainsString("['script', 'style', 'iframe', 'object', 'embed'", $sanitizer);
        self::assertStringContainsString("['https', 'http', 'mailto', 'tel']", $sanitizer);

        $headers = $this->source('app/Http/Middleware/ApplySecurityHeaders.php');
        self::assertStringContainsString("object-src 'none'", $headers);
        self::assertStringContainsString("frame-ancestors 'none'", $headers);
        self::assertStringContainsString("form-action 'self'", $headers);
    }
}
