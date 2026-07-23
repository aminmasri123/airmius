<?php

namespace Tests\Unit;

use App\Http\Controllers\SocialAuthController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SocialAuthMobileReturnUrlTest extends TestCase
{
    #[DataProvider('returnUrls')]
    public function test_only_expected_mobile_return_urls_are_allowed(string $url, bool $expected): void
    {
        $controller = new SocialAuthController;
        $method = new ReflectionMethod($controller, 'isAllowedMobileReturnUrl');

        $this->assertSame($expected, $method->invoke($controller, $url));
    }

    public static function returnUrls(): array
    {
        return [
            'native callback' => ['airmius://auth/callback', true],
            'native wrong host' => ['airmius://attacker/callback', false],
            'native wrong path' => ['airmius://auth/other', false],
            'native injected query is still fixed callback' => ['airmius://auth/callback?source=mobile', true],
            'approved web callback' => ['https://app.airmius.com/auth/callback', true],
            'local web callback' => ['http://localhost/auth/callback', true],
            'unapproved web host' => ['https://example.com/auth/callback', false],
            'javascript scheme' => ['javascript:alert(1)', false],
        ];
    }
}
