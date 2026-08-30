<?php

declare(strict_types=1);

namespace PromoSeven\AzureMailer\Tests\Graph;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PromoSeven\AzureMailer\Exceptions\AuthenticationException;
use PromoSeven\AzureMailer\Graph\TokenManager;
use PromoSeven\AzureMailer\Tests\TestCase;

class TokenManagerTest extends TestCase
{
    private array $config = [
        'tenant_id' => 'test-tenant',
        'client_id' => 'test-client-id',
        'client_secret' => 'test-secret',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_fetches_token_from_azure_on_cache_miss(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'my-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $manager = new TokenManager($this->config);
        $token = $manager->getToken();

        $this->assertSame('my-access-token', $token);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'test-tenant/oauth2/v2.0/token')
                && $request['grant_type'] === 'client_credentials'
                && $request['client_id'] === 'test-client-id'
                && $request['client_secret'] === 'test-secret'
                && $request['scope'] === 'https://graph.microsoft.com/.default';
        });
    }

    public function test_returns_cached_token_without_hitting_azure(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'first-token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $manager = new TokenManager($this->config);
        $manager->getToken(); // first call — hits Azure
        $manager->getToken(); // second call — should use cache

        Http::assertSentCount(1);
    }

    public function test_invalidate_clears_cached_token(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::sequence()
                ->push(['access_token' => 'token-one', 'expires_in' => 3600], 200)
                ->push(['access_token' => 'token-two', 'expires_in' => 3600], 200),
        ]);

        $manager = new TokenManager($this->config);
        $manager->getToken();    // fetches token-one
        $manager->invalidate();  // clears cache
        $second = $manager->getToken(); // fetches token-two

        $this->assertSame('token-two', $second);
        Http::assertSentCount(2);
    }

    public function test_token_is_cached_with_ttl_of_expires_in_minus_60(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'ttl-token',
                'expires_in' => 120, // 120 - 60 = 60 second TTL
            ], 200),
        ]);

        $manager = new TokenManager($this->config);
        $manager->getToken();

        // Token should be in cache immediately after fetch
        $this->assertTrue(Cache::has('azure_mailer_token_test-client-id'));

        // Advance time past the TTL (61 seconds)
        $this->travel(61)->seconds();

        // Token should now be expired from cache
        $this->assertFalse(Cache::has('azure_mailer_token_test-client-id'));
    }

    public function test_throws_authentication_exception_on_azure_error(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'error' => 'invalid_client',
                'error_description' => 'The client secret supplied is incorrect.',
            ], 401),
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('invalid_client');

        (new TokenManager($this->config))->getToken();
    }
}
