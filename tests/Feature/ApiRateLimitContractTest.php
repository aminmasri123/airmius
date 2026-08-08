<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiRateLimitContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_api_v1_write_routes_are_rate_limited(): void
    {
        $expected = [
            'api.v1.auth.login' => 'throttle:10,1',
            'api.v1.auth.register' => 'throttle:5,1',
            'api.v1.auth.password.email' => 'throttle:5,1',
            'api.v1.auth.password.reset' => 'throttle:5,1',
            'api.v1.auth.two-factor.challenge' => 'throttle:6,1',
            'api.v1.auth.email.verify' => 'throttle:10,1',
            'api.v1.me.email.verification.send' => 'throttle:6,1',
            'api.v1.me.two-factor.store' => 'throttle:5,1',
            'api.v1.me.two-factor.confirm' => 'throttle:6,1',
            'api.v1.me.two-factor.destroy' => 'throttle:5,1',
            'api.v1.me.two-factor.recovery-codes' => 'throttle:5,1',
            'api.v1.account.deletion-code' => 'throttle:5,1',
            'api.v1.account.destroy' => 'throttle:5,1',
            'api.v1.post-images.store' => 'throttle:file-uploads',
            'api.v1.uploads.store' => 'throttle:file-uploads',
            'api.v1.uploads.share' => 'throttle:file-share',
            'api.v1.files.folders.share' => 'throttle:file-share',
            'api.v1.files.upload-intents.store' => 'throttle:file-uploads',
            'api.v1.files.folders.store' => 'throttle:file-folder-create',
            'api.v1.posts.comments.store' => 'throttle:content-comments',
            'api.v1.comments.update' => 'throttle:content-comments',
            'api.v1.commerce.products.reviews.store' => 'throttle:content-comments',
            'api.v1.reports.store' => 'throttle:content-reports',
            'api.v1.public.recruiting.jobs.interest' => 'throttle:content-reports',
            'api.v1.chat.messages.store' => 'throttle:chat-messages',
            'api.v1.chat.messages.reactions.store' => 'throttle:chat-messages',
            'api.v1.chat.typing' => 'throttle:chat-presence',
            'api.v1.clubs.membership-invoices.payments.store' => 'throttle:payment-actions',
            'api.v1.clubs.donations.store' => 'throttle:payment-actions',
            'api.v1.clubs.prepayments.store' => 'throttle:payment-actions',
            'api.v1.clubs.payments.update' => 'throttle:payment-actions',
            'api.v1.clubs.subscriptions.cancel' => 'throttle:payment-actions',
            'api.v1.clubs.subscriptions.renew' => 'throttle:payment-actions',
            'api.v1.subscription-plans.checkout' => 'throttle:payment-actions',
            'api.v1.subscription-checkouts.cancel' => 'throttle:payment-actions',
            'api.v1.subscriptions.user.cancel' => 'throttle:payment-actions',
            'api.v1.subscriptions.user.renew' => 'throttle:payment-actions',
            'api.v1.admin.commerce.orders.mark-paid' => 'throttle:payment-actions',
            'api.v1.admin.commerce.orders.refund' => 'throttle:payment-actions',
            'api.v1.admin.commerce.payouts.paid' => 'throttle:payment-actions',
            'api.v1.admin.subscription-checkouts.mark-paid' => 'throttle:payment-actions',
        ];

        foreach ($expected as $routeName => $middleware) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Route [{$routeName}] is missing.");
            $this->assertContains($middleware, $route->gatherMiddleware(), "Route [{$routeName}] must use [{$middleware}].");
        }
    }

    public function test_api_v1_rate_limit_errors_use_contract_shape(): void
    {
        $response = null;

        for ($attempt = 1; $attempt <= 21; $attempt++) {
            $response = $this
                ->withServerVariables(['REMOTE_ADDR' => '198.51.100.77'])
                ->getJson("/api/v1/auth/register/email?email=rate{$attempt}@example.test");
        }

        $response
            ->assertStatus(429)
            ->assertJsonStructure([
                'message',
                'errors',
                'code',
                'error' => ['code', 'message'],
                'meta' => ['api_version', 'contract_version', 'request_id'],
            ])
            ->assertJsonPath('code', 'rate_limited')
            ->assertJsonPath('error.code', 'rate_limited');
    }
}
