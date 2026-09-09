<?php

declare(strict_types=1);

namespace PromoSeven\AzureMailer\Tests\Graph;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PromoSeven\AzureMailer\Exceptions\GraphApiException;
use PromoSeven\AzureMailer\Graph\GraphClient;
use PromoSeven\AzureMailer\Graph\TokenManager;
use PromoSeven\AzureMailer\Tests\TestCase;

class GraphClientTest extends TestCase
{
    private array $config = [
        'tenant_id' => 'test-tenant',
        'client_id' => 'test-client-id',
        'client_secret' => 'test-secret',
        'from_address' => 'sender@example.com',
        'timeout' => 30,
        'graph_api_version' => 'v1.0',
    ];

    private array $payload = [
        'message' => [
            'subject' => 'Test',
            'body' => ['contentType' => 'HTML', 'content' => '<p>Hello</p>'],
            'toRecipients' => [['emailAddress' => ['address' => 'to@example.com', 'name' => '']]],
            'ccRecipients' => [],
            'bccRecipients' => [],
            'replyTo' => [],
            'attachments' => [],
        ],
        'saveToSentItems' => false,
    ];

    public function test_sends_payload_with_bearer_token(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            'graph.microsoft.com/*' => Http::response('', 202),
        ]);

        $client = new GraphClient(new TokenManager($this->config), $this->config);
        $client->send($this->payload);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'graph.microsoft.com/v1.0/users/sender@example.com/sendMail')
                && $request->hasHeader('Authorization', 'Bearer tok');
        });
    }

    public function test_retries_with_fresh_token_on_401(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('azure-mailer: 401 received, retrying with fresh token');

        Http::fake([
            'login.microsoftonline.com/*' => Http::sequence()
                ->push(['access_token' => 'stale-token', 'expires_in' => 3600], 200)
                ->push(['access_token' => 'fresh-token', 'expires_in' => 3600], 200),
            'graph.microsoft.com/*' => Http::sequence()
                ->push('', 401)
                ->push('', 202),
        ]);

        $client = new GraphClient(new TokenManager($this->config), $this->config);
        $client->send($this->payload);

        Http::assertSentCount(4); // 2 token fetches + 2 graph calls
    }

    public function test_throws_graph_api_exception_on_second_401(): void
    {
        Log::shouldReceive('warning')->once();

        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            'graph.microsoft.com/*' => Http::response([
                'error' => ['code' => 'InvalidAuthenticationToken', 'message' => 'Token is expired.'],
            ], 401),
        ]);

        $this->expectException(GraphApiException::class);
        $this->expectExceptionMessage('InvalidAuthenticationToken');

        $client = new GraphClient(new TokenManager($this->config), $this->config);
        $client->send($this->payload);
    }

    public function test_throws_graph_api_exception_on_other_error(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            'graph.microsoft.com/*' => Http::response([
                'error' => ['code' => 'ErrorInvalidRecipients', 'message' => 'Recipient address is invalid.'],
            ], 400),
        ]);

        $this->expectException(GraphApiException::class);
        $this->expectExceptionMessage('ErrorInvalidRecipients');

        $client = new GraphClient(new TokenManager($this->config), $this->config);
        $client->send($this->payload);
    }

    public function test_uses_graph_api_version_from_config(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            'graph.microsoft.com/*' => Http::response('', 202),
        ]);

        $config = array_merge($this->config, ['graph_api_version' => 'beta']);
        $client = new GraphClient(new TokenManager($config), $config);
        $client->send($this->payload);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'graph.microsoft.com/beta/');
        });
    }
}
