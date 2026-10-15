<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PlatformAdminPhase33SecurityContractTest extends TestCase
{
    private function projectPath(string $path): string
    {
        return dirname(__DIR__, 2).'/'.$path;
    }

    public function test_shared_user_admin_paths_protect_privileged_accounts_and_role_assignment(): void
    {
        $policy = file_get_contents($this->projectPath('app/Policies/UserPolicy.php'));
        $controller = file_get_contents($this->projectPath('app/Http/Controllers/MemberController.php'));
        $platformApi = file_get_contents($this->projectPath('app/Http/Controllers/Api/V1/PlatformAdminController.php'));

        self::assertStringContainsString('Roles::PROTECTED_PLATFORM', $policy);
        self::assertStringContainsString("hasRole('super_admin')", $policy);
        self::assertStringContainsString('array_intersect($data[\'roles\'], Roles::PROTECTED_PLATFORM)', $controller);
        self::assertStringContainsString("whereNotIn('name', Roles::PROTECTED_PLATFORM)", $controller);
        self::assertStringContainsString('hasAnyRole(Roles::PROTECTED_PLATFORM)', $platformApi);
    }

    public function test_global_media_writes_require_manage_permission_on_web_and_api(): void
    {
        $controller = file_get_contents($this->projectPath('app/Http/Controllers/MediaGuidelineController.php'));
        $blogController = file_get_contents($this->projectPath('app/Http/Controllers/BlogPostController.php'));
        $webRoutes = file_get_contents($this->projectPath('routes/admin.php'));
        $apiRoutes = file_get_contents($this->projectPath('routes/api.php'));

        self::assertStringContainsString("can('blog.manage')", $controller);
        self::assertStringContainsString("can('system.manage')", $controller);
        self::assertStringContainsString("'canManage'", $controller);
        self::assertStringContainsString("name('blogs.content-images.store')", $webRoutes);
        self::assertStringContainsString("\$this->canBlog(\$request, 'blog.create') || \$this->canBlog(\$request, 'blog.update')", $blogController);
        self::assertStringContainsString("middleware('throttle:file-uploads')->name('admin.media-guidelines.visuals')", $apiRoutes);

        $webScreen = file_get_contents($this->projectPath('resources/js/Pages/Auth/Dashboard/MediaGuidelines/Index.vue'));
        $mobileScreen = file_get_contents($this->projectPath('mobile/airmius_mobile/lib/screens/admin_media_screen.dart'));
        self::assertStringContainsString('v-if="props.canManage"', $webScreen);
        self::assertStringContainsString('bool get _canManage', $mobileScreen);
    }

    public function test_native_admin_routes_are_real_actions_not_menu_placeholders(): void
    {
        $routes = file_get_contents($this->projectPath('routes/api.php'));
        $client = file_get_contents($this->projectPath('mobile/airmius_mobile/lib/core/airmius_api_client.dart'));
        $screen = file_get_contents($this->projectPath('mobile/airmius_mobile/lib/screens/admin_members_screen.dart'));

        foreach (['admin/members/{user}', 'admin/platform/users/{user}/status', 'admin/platform/roles/{role}'] as $path) {
            self::assertStringContainsString($path, $routes);
        }
        foreach (['adminMember(', 'adminUpdateMember(', 'adminDeleteMember('] as $method) {
            self::assertStringContainsString($method, $client);
        }
        self::assertStringContainsString('await _client.adminUpdateMember', $screen);
        self::assertStringContainsString('await _client.adminDeleteMember', $screen);
    }

    public function test_all_phase_33_platform_admin_scenarios_have_real_web_and_native_contracts(): void
    {
        $webRoutes = file_get_contents($this->projectPath('routes/admin.php'));
        $apiRoutes = file_get_contents($this->projectPath('routes/api.php'));
        $workspace = file_get_contents($this->projectPath('mobile/airmius_mobile/lib/screens/admin_workspace_screen.dart'));
        $client = file_get_contents($this->projectPath('mobile/airmius_mobile/lib/core/airmius_api_client.dart'));

        $webContracts = [
            'admin/clubs/{club}/verification-status',
            'admin/club-verifications/{club}/approve',
            'admin/clubs/{club}',
            'admin/members/{user}',
            'admin/members/{user}/inactivity-notice',
            'admin/roles/{role}',
            'admin/trainer-applications/{application}/approve',
            'admin/moderation/reports/{report}/appeal',
            'admin/blogs/{blogPost}',
            'admin/media-guidelines/visuals',
            'admin/sports/{sport}',
            'admin/badges/{badge}',
            'admin/subscription-invoices/{subscriptionInvoice}/mark-paid',
            'admin/commerce/orders/{order}/refund',
            'admin/commerce/payouts/{payout}/paid',
            'admin/sponsors/{sponsor}',
            'admin/mail-center/{mailDelivery}/resend',
            'admin/operating-contracts/{operatingContract}',
            'admin/product-analytics',
            'admin/settings',
            'admin/commerce/export.csv',
        ];
        foreach ($webContracts as $contract) {
            self::assertStringContainsString($contract, $webRoutes, $contract);
        }

        $nativeContracts = [
            'admin/platform/clubs/{club}/verification-status',
            'admin/members/{user}',
            'admin/platform/roles/{role}',
            'admin/trainer-applications/{application}/approve',
            'admin/platform/moderation/reports/{report}/appeal',
            'admin/media-guidelines/visuals',
            'admin/platform/sports/{sport}',
            'admin/platform/badges/{badge}',
            'admin/backoffice/subscription-invoices/{subscriptionInvoice}/mark-paid',
            'admin/commerce/orders/{order}/refund',
            'admin/commerce/payouts/{payout}/paid',
            'admin/support/tickets/{supportTicket}',
            'admin/backoffice/contracts/{operatingContract}',
            'admin/product-analytics',
            'admin/system/settings',
            'admin/commerce/export',
        ];
        foreach ($nativeContracts as $contract) {
            self::assertStringContainsString($contract, $apiRoutes, $contract);
        }

        foreach (['AdminMembersScreen', 'AdminClubsScreen', 'AdminCommerceOperationsScreen', 'AdminBackofficeScreen', 'AdminMailCenterScreen', 'AdminPlatformSettingsScreen'] as $screen) {
            self::assertStringContainsString($screen, $workspace, $screen);
        }
        foreach (['adminPlatformDashboard', 'adminCommerceDashboard', 'adminBackofficeDashboard', 'adminSystemDashboard', 'adminProductAnalytics'] as $method) {
            self::assertStringContainsString($method, $client, $method);
        }

        $analytics = file_get_contents($this->projectPath('app/Services/ProductAnalyticsService.php'));
        $refunds = file_get_contents($this->projectPath('app/Services/CommerceRefundService.php'));
        $payouts = file_get_contents($this->projectPath('app/Services/MarketplacePayoutService.php'));
        self::assertStringContainsString('minimum_group_size', $analytics);
        self::assertStringContainsString('lockForUpdate()', $refunds);
        self::assertStringContainsString('idempotency_key', $refunds);
        self::assertStringContainsString('lockForUpdate()', $payouts);
        self::assertStringContainsString("'payout.paid'", $payouts);
    }
}
