# azure-mailer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `promoseven/azure-mailer`, a Laravel mail transport driver that sends email via the Microsoft 365 Graph API using Azure AD Client Credentials.

**Architecture:** Three focused classes — `TokenManager` (OAuth2 token + caching), `GraphClient` (HTTP calls to Graph API), `AzureTransport` (Symfony transport adapter) — wired together by `AzureMailerServiceProvider`. Dependencies flow one way: Transport → Client → TokenManager.

**Tech Stack:** PHP 8.2, Laravel 11/12, Symfony Mailer 7, Laravel Http facade (Guzzle), Laravel Cache facade, Orchestra Testbench 10, PHPUnit 11.

---

## File Map

| File | Action | Purpose |
|------|--------|---------|
| `C:\Users\IT Department\Desktop\azure-mailer\composer.json` | Create | Package manifest, autoloading, dependencies |
| `src/Exceptions/AuthenticationException.php` | Create | Thrown when Azure AD token fetch fails |
| `src/Exceptions/GraphApiException.php` | Create | Thrown when Graph API returns an error |
| `src/Graph/TokenManager.php` | Create | Fetches + caches OAuth2 access token |
| `src/Graph/GraphClient.php` | Create | POSTs mail payload to Graph API |
| `src/Transport/AzureTransport.php` | Create | Symfony AbstractTransport — builds payload from Email object |
| `src/AzureMailerServiceProvider.php` | Create | Registers `azure` transport with Laravel MailManager |
| `config/azure-mailer.php` | Create | Publishable defaults (save_to_sent_items, timeout, graph_api_version) |
| `tests/TestCase.php` | Create | Orchestra Testbench base with array cache driver |
| `tests/Graph/TokenManagerTest.php` | Create | Token fetch, caching, invalidation, error handling |
| `tests/Graph/GraphClientTest.php` | Create | Payload send, 401 retry, error handling |
| `tests/Transport/AzureTransportTest.php` | Create | Payload building (HTML, text, CC/BCC/Reply-To, attachments) |
| `C:\Users\IT Department\Desktop\OperationModule\composer.json` | Modify | Add path repository + require azure-mailer |
| `C:\Users\IT Department\Desktop\OperationModule\config\mail.php` | Modify | Add azure mailer entry |
| `C:\Users\IT Department\Desktop\OperationModule\.env.example` | Modify | Add AZURE_* env vars |

---

## Task 1: Package scaffold

**Files:**
- Create: `C:\Users\IT Department\Desktop\azure-mailer\composer.json`
- Create: `C:\Users\IT Department\Desktop\azure-mailer\config\azure-mailer.php`

- [ ] **Step 1: Create the package directory and composer.json**

Create `C:\Users\IT Department\Desktop\azure-mailer\composer.json`:

```json
{
    "name": "promoseven/azure-mailer",
    "description": "Laravel mail transport for Microsoft 365 via Azure AD Graph API",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.2",
        "illuminate/support": "^11.0|^12.0",
        "illuminate/http": "^11.0|^12.0",
        "illuminate/mail": "^11.0|^12.0",
        "symfony/mailer": "^7.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "orchestra/testbench": "^10.0"
    },
    "autoload": {
        "psr-4": {
            "PromoSeven\\AzureMailer\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "PromoSeven\\AzureMailer\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "PromoSeven\\AzureMailer\\AzureMailerServiceProvider"
            ]
        }
    },
    "scripts": {
        "test": "vendor/bin/phpunit"
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

- [ ] **Step 2: Create directory structure**

Run from `C:\Users\IT Department\Desktop\azure-mailer`:

```powershell
New-Item -ItemType Directory -Force src/Exceptions, src/Graph, src/Transport, config, tests/Graph, tests/Transport
```

- [ ] **Step 3: Create the publishable config file**

Create `C:\Users\IT Department\Desktop\azure-mailer\config\azure-mailer.php`:

```php
<?php

return [
    'save_to_sent_items' => false,
    'timeout'            => 30,
    'graph_api_version'  => 'v1.0',
];
```

- [ ] **Step 4: Install dependencies**

Run from `C:\Users\IT Department\Desktop\azure-mailer`:

```powershell
composer install
```

Expected: `vendor/` created, `vendor/bin/phpunit` exists.

- [ ] **Step 5: Create phpunit.xml**

Create `C:\Users\IT Department\Desktop\azure-mailer\phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 6: Create base TestCase**

Create `C:\Users\IT Department\Desktop\azure-mailer\tests\TestCase.php`:

```php
<?php

namespace PromoSeven\AzureMailer\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use PromoSeven\AzureMailer\AzureMailerServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [AzureMailerServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
    }
}
```

- [ ] **Step 7: Verify PHPUnit runs (no tests yet)**

Run from `C:\Users\IT Department\Desktop\azure-mailer`:

```powershell
vendor/bin/phpunit
```

Expected output: `No tests executed!` or `0 tests, 0 assertions`. No errors.

- [ ] **Step 8: Init git and commit scaffold**

Run from `C:\Users\IT Department\Desktop\azure-mailer`:

```powershell
git init
git add composer.json phpunit.xml config/azure-mailer.php tests/TestCase.php
git commit -m "chore: scaffold azure-mailer package"
```

---

## Task 2: Exceptions

**Files:**
- Create: `src/Exceptions/AuthenticationException.php`
- Create: `src/Exceptions/GraphApiException.php`

- [ ] **Step 1: Write failing tests for both exceptions**

Create `C:\Users\IT Department\Desktop\azure-mailer\tests\ExceptionsTest.php`:

```php
<?php

namespace PromoSeven\AzureMailer\Tests;

use PromoSeven\AzureMailer\Exceptions\AuthenticationException;
use PromoSeven\AzureMailer\Exceptions\GraphApiException;

class ExceptionsTest extends TestCase
{
    public function test_authentication_exception_formats_message(): void
    {
        $e = AuthenticationException::fromResponse('invalid_client', 'The client secret is incorrect.');

        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->assertStringContainsString('invalid_client', $e->getMessage());
        $this->assertStringContainsString('The client secret is incorrect.', $e->getMessage());
    }

    public function test_graph_api_exception_formats_message(): void
    {
        $e = GraphApiException::fromResponse('ErrorItemNotFound', 'The specified object was not found.');

        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->assertStringContainsString('ErrorItemNotFound', $e->getMessage());
        $this->assertStringContainsString('The specified object was not found.', $e->getMessage());
    }
}
```

- [ ] **Step 2: Run tests — expect failure (class not found)**

```powershell
vendor/bin/phpunit tests/ExceptionsTest.php
```

Expected: FAIL with `Class "PromoSeven\AzureMailer\Exceptions\AuthenticationException" not found`.

- [ ] **Step 3: Create AuthenticationException**

Create `C:\Users\IT Department\Desktop\azure-mailer\src\Exceptions\AuthenticationException.php`:

```php
<?php

namespace PromoSeven\AzureMailer\Exceptions;

class AuthenticationException extends \RuntimeException
{
    public static function fromResponse(string $error, string $description): self
    {
        return new self("Azure AD authentication failed: [{$error}] {$description}");
    }
}
```

- [ ] **Step 4: Create GraphApiException**

Create `C:\Users\IT Department\Desktop\azure-mailer\src\Exceptions\GraphApiException.php`:

```php
<?php

namespace PromoSeven\AzureMailer\Exceptions;

class GraphApiException extends \RuntimeException
{
    public static function fromResponse(string $code, string $message): self
    {
        return new self("Graph API error: [{$code}] {$message}");
    }
}
```

- [ ] **Step 5: Run tests — expect pass**

```powershell
vendor/bin/phpunit tests/ExceptionsTest.php
```

Expected: `2 tests, 4 assertions` — all PASS.

- [ ] **Step 6: Commit**

```powershell
git add src/Exceptions/ tests/ExceptionsTest.php
git commit -m "feat: add AuthenticationException and GraphApiException"
```

---

## Task 3: TokenManager

**Files:**
- Create: `src/Graph/TokenManager.php`
- Create: `tests/Graph/TokenManagerTest.php`

- [ ] **Step 1: Write failing tests**

Create `C:\Users\IT Department\Desktop\azure-mailer\tests\Graph\TokenManagerTest.php`:

```php
<?php

namespace PromoSeven\AzureMailer\Tests\Graph;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PromoSeven\AzureMailer\Exceptions\AuthenticationException;
use PromoSeven\AzureMailer\Graph\TokenManager;
use PromoSeven\AzureMailer\Tests\TestCase;

class TokenManagerTest extends TestCase
{
    private array $config = [
        'tenant_id'     => 'test-tenant',
        'client_id'     => 'test-client-id',
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
                'expires_in'   => 3600,
                'token_type'   => 'Bearer',
            ], 200),
        ]);

        $manager = new TokenManager($this->config);
        $token   = $manager->getToken();

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
                'expires_in'   => 3600,
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
                'expires_in'   => 3600,
            ], 200),
        ]);

        $manager = new TokenManager($this->config);
        $manager->getToken();

        $this->assertTrue(Cache::has('azure_mailer_token_test-client-id'));
    }

    public function test_throws_authentication_exception_on_azure_error(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'error'             => 'invalid_client',
                'error_description' => 'The client secret supplied is incorrect.',
            ], 401),
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('invalid_client');

        (new TokenManager($this->config))->getToken();
    }
}
```

- [ ] **Step 2: Run tests — expect failure**

```powershell
vendor/bin/phpunit tests/Graph/TokenManagerTest.php
```

Expected: FAIL with `Class "PromoSeven\AzureMailer\Graph\TokenManager" not found`.

- [ ] **Step 3: Implement TokenManager**

Create `C:\Users\IT Department\Desktop\azure-mailer\src\Graph\TokenManager.php`:

```php
<?php

namespace PromoSeven\AzureMailer\Graph;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PromoSeven\AzureMailer\Exceptions\AuthenticationException;

class TokenManager
{
    public function __construct(private readonly array $config) {}

    public function getToken(): string
    {
        $key = $this->cacheKey();

        if ($token = Cache::get($key)) {
            return $token;
        }

        return $this->fetchAndCache($key);
    }

    public function invalidate(): void
    {
        Cache::forget($this->cacheKey());
    }

    private function fetchAndCache(string $key): string
    {
        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$this->config['tenant_id']}/oauth2/v2.0/token",
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'scope'         => 'https://graph.microsoft.com/.default',
            ]
        );

        if (! $response->successful()) {
            $body = $response->json();
            throw AuthenticationException::fromResponse(
                $body['error'] ?? 'unknown_error',
                $body['error_description'] ?? 'No description provided'
            );
        }

        $body = $response->json();
        $ttl  = ($body['expires_in'] ?? 3600) - 60;

        Cache::put($key, $body['access_token'], $ttl);

        return $body['access_token'];
    }

    private function cacheKey(): string
    {
        return 'azure_mailer_token_' . $this->config['client_id'];
    }
}
```

- [ ] **Step 4: Run tests — expect all pass**

```powershell
vendor/bin/phpunit tests/Graph/TokenManagerTest.php
```

Expected: `5 tests, 8 assertions` — all PASS.

- [ ] **Step 5: Commit**

```powershell
git add src/Graph/TokenManager.php tests/Graph/TokenManagerTest.php
git commit -m "feat: add TokenManager with OAuth2 client credentials and cache"
```

---

## Task 4: GraphClient

**Files:**
- Create: `src/Graph/GraphClient.php`
- Create: `tests/Graph/GraphClientTest.php`

- [ ] **Step 1: Write failing tests**

Create `C:\Users\IT Department\Desktop\azure-mailer\tests\Graph\GraphClientTest.php`:

```php
<?php

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
        'tenant_id'          => 'test-tenant',
        'client_id'          => 'test-client-id',
        'client_secret'      => 'test-secret',
        'from_address'       => 'sender@example.com',
        'timeout'            => 30,
        'graph_api_version'  => 'v1.0',
    ];

    private array $payload = [
        'message' => [
            'subject' => 'Test',
            'body'    => ['contentType' => 'HTML', 'content' => '<p>Hello</p>'],
            'toRecipients' => [['emailAddress' => ['address' => 'to@example.com', 'name' => '']]],
            'ccRecipients'  => [],
            'bccRecipients' => [],
            'replyTo'       => [],
            'attachments'   => [],
        ],
        'saveToSentItems' => false,
    ];

    private function makeTokenManager(string $token = 'fake-token'): TokenManager
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => $token,
                'expires_in'   => 3600,
            ], 200),
        ]);

        return new TokenManager($this->config);
    }

    public function test_sends_payload_with_bearer_token(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            'graph.microsoft.com/*'       => Http::response('', 202),
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
            'graph.microsoft.com/*'       => Http::response([
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
            'graph.microsoft.com/*'       => Http::response([
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
            'graph.microsoft.com/*'       => Http::response('', 202),
        ]);

        $config = array_merge($this->config, ['graph_api_version' => 'beta']);
        $client = new GraphClient(new TokenManager($config), $config);
        $client->send($this->payload);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'graph.microsoft.com/beta/');
        });
    }
}
```

- [ ] **Step 2: Run tests — expect failure**

```powershell
vendor/bin/phpunit tests/Graph/GraphClientTest.php
```

Expected: FAIL with `Class "PromoSeven\AzureMailer\Graph\GraphClient" not found`.

- [ ] **Step 3: Implement GraphClient**

Create `C:\Users\IT Department\Desktop\azure-mailer\src\Graph\GraphClient.php`:

```php
<?php

namespace PromoSeven\AzureMailer\Graph;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PromoSeven\AzureMailer\Exceptions\GraphApiException;

class GraphClient
{
    public function __construct(
        private readonly TokenManager $tokenManager,
        private readonly array $config
    ) {}

    public function send(array $payload): void
    {
        $url = $this->endpoint();

        $response = Http::withToken($this->tokenManager->getToken())
            ->timeout($this->config['timeout'] ?? 30)
            ->post($url, $payload);

        if ($response->status() === 401) {
            Log::warning('azure-mailer: 401 received, retrying with fresh token');
            $this->tokenManager->invalidate();

            $response = Http::withToken($this->tokenManager->getToken())
                ->timeout($this->config['timeout'] ?? 30)
                ->post($url, $payload);
        }

        if (! $response->successful()) {
            $error = $response->json('error', []);
            throw GraphApiException::fromResponse(
                $error['code'] ?? (string) $response->status(),
                $error['message'] ?? 'Unknown error'
            );
        }
    }

    private function endpoint(): string
    {
        $version = $this->config['graph_api_version'] ?? 'v1.0';
        $from    = $this->config['from_address'];

        return "https://graph.microsoft.com/{$version}/users/{$from}/sendMail";
    }
}
```

- [ ] **Step 4: Run tests — expect all pass**

```powershell
vendor/bin/phpunit tests/Graph/GraphClientTest.php
```

Expected: `5 tests` — all PASS.

- [ ] **Step 5: Commit**

```powershell
git add src/Graph/GraphClient.php tests/Graph/GraphClientTest.php
git commit -m "feat: add GraphClient with 401 retry and error handling"
```

---

## Task 5: AzureTransport

**Files:**
- Create: `src/Transport/AzureTransport.php`
- Create: `tests/Transport/AzureTransportTest.php`

- [ ] **Step 1: Write failing tests**

Create `C:\Users\IT Department\Desktop\azure-mailer\tests\Transport\AzureTransportTest.php`:

```php
<?php

namespace PromoSeven\AzureMailer\Tests\Transport;

use PromoSeven\AzureMailer\Graph\GraphClient;
use PromoSeven\AzureMailer\Tests\TestCase;
use PromoSeven\AzureMailer\Transport\AzureTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class AzureTransportTest extends TestCase
{
    private function makeTransport(array $captured = [], array $config = []): AzureTransport
    {
        $client = $this->createMock(GraphClient::class);
        $client->method('send')->willReturnCallback(function (array $payload) use (&$captured) {
            $captured[] = $payload;
        });

        return new AzureTransport($client, array_merge(['save_to_sent_items' => false], $config));
    }

    private function sendEmail(AzureTransport $transport, Email $email): array
    {
        $captured = [];
        $client   = $this->createMock(GraphClient::class);
        $client->method('send')->willReturnCallback(function (array $payload) use (&$captured) {
            $captured[] = $payload;
        });

        $transport = new AzureTransport($client, ['save_to_sent_items' => false]);
        $envelope  = Envelope::create($email);
        $transport->send($email, $envelope);

        return $captured[0] ?? [];
    }

    public function test_sends_html_body(): void
    {
        $captured = [];
        $client   = $this->createMock(GraphClient::class);
        $client->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (array $payload) use (&$captured) {
                $captured = $payload;
            });

        $email = (new Email())
            ->from('from@example.com')
            ->to('to@example.com')
            ->subject('Hello')
            ->html('<p>World</p>');

        $transport = new AzureTransport($client, ['save_to_sent_items' => false]);
        $transport->send($email, Envelope::create($email));

        $this->assertSame('Hello', $captured['message']['subject']);
        $this->assertSame('HTML', $captured['message']['body']['contentType']);
        $this->assertSame('<p>World</p>', $captured['message']['body']['content']);
        $this->assertSame('to@example.com', $captured['message']['toRecipients'][0]['emailAddress']['address']);
        $this->assertFalse($captured['saveToSentItems']);
    }

    public function test_falls_back_to_text_body_when_no_html(): void
    {
        $captured = [];
        $client   = $this->createMock(GraphClient::class);
        $client->method('send')->willReturnCallback(function ($p) use (&$captured) { $captured = $p; });

        $email = (new Email())
            ->from('from@example.com')
            ->to('to@example.com')
            ->subject('Text only')
            ->text('Plain text content');

        $transport = new AzureTransport($client, ['save_to_sent_items' => false]);
        $transport->send($email, Envelope::create($email));

        $this->assertSame('Text', $captured['message']['body']['contentType']);
        $this->assertSame('Plain text content', $captured['message']['body']['content']);
    }

    public function test_maps_cc_bcc_and_reply_to(): void
    {
        $captured = [];
        $client   = $this->createMock(GraphClient::class);
        $client->method('send')->willReturnCallback(function ($p) use (&$captured) { $captured = $p; });

        $email = (new Email())
            ->from('from@example.com')
            ->to('to@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->replyTo('reply@example.com')
            ->subject('Recipients test')
            ->html('<p>hi</p>');

        $transport = new AzureTransport($client, ['save_to_sent_items' => false]);
        $transport->send($email, Envelope::create($email));

        $this->assertSame('cc@example.com', $captured['message']['ccRecipients'][0]['emailAddress']['address']);
        $this->assertSame('bcc@example.com', $captured['message']['bccRecipients'][0]['emailAddress']['address']);
        $this->assertSame('reply@example.com', $captured['message']['replyTo'][0]['emailAddress']['address']);
    }

    public function test_encodes_attachments_as_base64(): void
    {
        $captured = [];
        $client   = $this->createMock(GraphClient::class);
        $client->method('send')->willReturnCallback(function ($p) use (&$captured) { $captured = $p; });

        $email = (new Email())
            ->from('from@example.com')
            ->to('to@example.com')
            ->subject('With attachment')
            ->html('<p>see attachment</p>')
            ->attachData('PDF content here', 'report.pdf', ['mime' => 'application/pdf']);

        $transport = new AzureTransport($client, ['save_to_sent_items' => false]);
        $transport->send($email, Envelope::create($email));

        $attachment = $captured['message']['attachments'][0];
        $this->assertSame('#microsoft.graph.fileAttachment', $attachment['@odata.type']);
        $this->assertSame('report.pdf', $attachment['name']);
        $this->assertSame(base64_encode('PDF content here'), $attachment['contentBytes']);
        $this->assertStringContainsString('application', $attachment['contentType']);
    }

    public function test_save_to_sent_items_is_configurable(): void
    {
        $captured = [];
        $client   = $this->createMock(GraphClient::class);
        $client->method('send')->willReturnCallback(function ($p) use (&$captured) { $captured = $p; });

        $email = (new Email())
            ->from('from@example.com')
            ->to('to@example.com')
            ->subject('Save it')
            ->html('<p>keep</p>');

        $transport = new AzureTransport($client, ['save_to_sent_items' => true]);
        $transport->send($email, Envelope::create($email));

        $this->assertTrue($captured['saveToSentItems']);
    }

    public function test_to_string_returns_azure(): void
    {
        $client    = $this->createMock(GraphClient::class);
        $transport = new AzureTransport($client, []);

        $this->assertSame('azure', (string) $transport);
    }
}
```

- [ ] **Step 2: Run tests — expect failure**

```powershell
vendor/bin/phpunit tests/Transport/AzureTransportTest.php
```

Expected: FAIL with `Class "PromoSeven\AzureMailer\Transport\AzureTransport" not found`.

- [ ] **Step 3: Implement AzureTransport**

Create `C:\Users\IT Department\Desktop\azure-mailer\src\Transport\AzureTransport.php`:

```php
<?php

namespace PromoSeven\AzureMailer\Transport;

use PromoSeven\AzureMailer\Graph\GraphClient;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class AzureTransport extends AbstractTransport
{
    public function __construct(
        private readonly GraphClient $client,
        private readonly array $config = []
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (! $email instanceof Email) {
            return;
        }

        $this->client->send($this->buildPayload($email));
    }

    public function __toString(): string
    {
        return 'azure';
    }

    private function buildPayload(Email $email): array
    {
        return [
            'message' => [
                'subject'       => $email->getSubject() ?? '',
                'body'          => [
                    'contentType' => $email->getHtmlBody() !== null ? 'HTML' : 'Text',
                    'content'     => $email->getHtmlBody() ?? $email->getTextBody() ?? '',
                ],
                'toRecipients'  => $this->mapAddresses($email->getTo()),
                'ccRecipients'  => $this->mapAddresses($email->getCc()),
                'bccRecipients' => $this->mapAddresses($email->getBcc()),
                'replyTo'       => $this->mapAddresses($email->getReplyTo()),
                'attachments'   => $this->mapAttachments($email),
            ],
            'saveToSentItems' => (bool) ($this->config['save_to_sent_items'] ?? false),
        ];
    }

    private function mapAddresses(array $addresses): array
    {
        return array_map(fn ($addr) => [
            'emailAddress' => [
                'address' => $addr->getAddress(),
                'name'    => $addr->getName() ?? '',
            ],
        ], $addresses);
    }

    private function mapAttachments(Email $email): array
    {
        $result = [];

        foreach ($email->getAttachments() as $attachment) {
            if (! $attachment instanceof DataPart) {
                continue;
            }

            $result[] = [
                '@odata.type'  => '#microsoft.graph.fileAttachment',
                'name'         => $attachment->getFilename() ?? 'attachment',
                'contentType'  => $attachment->getMediaType() . '/' . $attachment->getMediaSubtype(),
                'contentBytes' => base64_encode($attachment->getBody()),
            ];
        }

        return $result;
    }
}
```

- [ ] **Step 4: Run tests — expect all pass**

```powershell
vendor/bin/phpunit tests/Transport/AzureTransportTest.php
```

Expected: `6 tests` — all PASS.

- [ ] **Step 5: Run full test suite**

```powershell
vendor/bin/phpunit
```

Expected: All tests pass across all test files.

- [ ] **Step 6: Commit**

```powershell
git add src/Transport/AzureTransport.php tests/Transport/AzureTransportTest.php
git commit -m "feat: add AzureTransport with payload building and attachment support"
```

---

## Task 6: Service Provider

**Files:**
- Create: `src/AzureMailerServiceProvider.php`

- [ ] **Step 1: Create the service provider**

Create `C:\Users\IT Department\Desktop\azure-mailer\src\AzureMailerServiceProvider.php`:

```php
<?php

namespace PromoSeven\AzureMailer;

use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;
use PromoSeven\AzureMailer\Graph\GraphClient;
use PromoSeven\AzureMailer\Graph\TokenManager;
use PromoSeven\AzureMailer\Transport\AzureTransport;

class AzureMailerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/azure-mailer.php', 'azure-mailer');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/azure-mailer.php' => config_path('azure-mailer.php'),
        ], 'azure-mailer-config');

        $this->app->make(MailManager::class)->extend('azure', function (array $config) {
            $merged = array_merge(config('azure-mailer', []), $config);

            return new AzureTransport(
                new GraphClient(new TokenManager($merged), $merged),
                $merged
            );
        });
    }
}
```

- [ ] **Step 2: Write a smoke test for the service provider**

Add this test to a new file `C:\Users\IT Department\Desktop\azure-mailer\tests\ServiceProviderTest.php`:

```php
<?php

namespace PromoSeven\AzureMailer\Tests;

use Illuminate\Mail\MailManager;
use PromoSeven\AzureMailer\Transport\AzureTransport;

class ServiceProviderTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('mail.mailers.azure', [
            'transport'     => 'azure',
            'tenant_id'     => 'test-tenant',
            'client_id'     => 'test-client-id',
            'client_secret' => 'test-secret',
            'from_address'  => 'sender@example.com',
        ]);
    }

    public function test_azure_transport_is_registered_with_mail_manager(): void
    {
        $manager   = $this->app->make(MailManager::class);
        $transport = $manager->mailer('azure')->getSymfonyTransport();

        $this->assertInstanceOf(AzureTransport::class, $transport);
        $this->assertSame('azure', (string) $transport);
    }
}
```

- [ ] **Step 3: Run the smoke test**

```powershell
vendor/bin/phpunit tests/ServiceProviderTest.php
```

Expected: `1 test` — PASS.

- [ ] **Step 4: Run full test suite to confirm nothing regressed**

```powershell
vendor/bin/phpunit
```

Expected: All tests pass.

- [ ] **Step 5: Commit**

```powershell
git add src/AzureMailerServiceProvider.php tests/ServiceProviderTest.php
git commit -m "feat: add AzureMailerServiceProvider with transport registration and config publishing"
```

---

## Task 7: Wire into OperationModule

**Files:**
- Modify: `C:\Users\IT Department\Desktop\OperationModule\composer.json`
- Modify: `C:\Users\IT Department\Desktop\OperationModule\config\mail.php`
- Modify: `C:\Users\IT Department\Desktop\OperationModule\.env.example`

- [ ] **Step 1: Add path repository and require in OperationModule's composer.json**

In `C:\Users\IT Department\Desktop\OperationModule\composer.json`, add to the `repositories` array:

```json
{
    "type": "path",
    "url": "../azure-mailer"
}
```

And add to `require`:

```json
"promoseven/azure-mailer": "*"
```

The `repositories` array should look like:

```json
"repositories": [
    {
        "type": "path",
        "url": "../ultra-message"
    },
    {
        "type": "path",
        "url": "../azure-mailer"
    }
]
```

- [ ] **Step 2: Run composer update**

Run from `C:\Users\IT Department\Desktop\OperationModule`:

```powershell
composer require promoseven/azure-mailer:*
```

Expected: Package installed, `vendor/promoseven/azure-mailer` symlink created.

- [ ] **Step 3: Add the azure mailer to config/mail.php**

In `C:\Users\IT Department\Desktop\OperationModule\config\mail.php`, inside the `'mailers'` array, add:

```php
'azure' => [
    'transport'     => 'azure',
    'tenant_id'     => env('AZURE_TENANT_ID'),
    'client_id'     => env('AZURE_CLIENT_ID'),
    'client_secret' => env('AZURE_CLIENT_SECRET'),
    'from_address'  => env('AZURE_MAIL_FROM_ADDRESS'),
],
```

- [ ] **Step 4: Add env vars to .env.example**

Add to `C:\Users\IT Department\Desktop\OperationModule\.env.example`:

```env
# Microsoft 365 / Azure AD Mail
AZURE_TENANT_ID=
AZURE_CLIENT_ID=
AZURE_CLIENT_SECRET=
AZURE_MAIL_FROM_ADDRESS=
```

- [ ] **Step 5: Verify package auto-discovery**

Run from `C:\Users\IT Department\Desktop\OperationModule`:

```powershell
php artisan package:discover --ansi
```

Expected output includes: `Discovered Package: promoseven/azure-mailer`.

- [ ] **Step 6: Commit OperationModule changes**

Run from `C:\Users\IT Department\Desktop\OperationModule`:

```powershell
git add composer.json composer.lock config/mail.php .env.example
git commit -m "feat: integrate promoseven/azure-mailer as mail transport"
```

---

## Task 8: README

**Files:**
- Create: `C:\Users\IT Department\Desktop\azure-mailer\README.md`

- [ ] **Step 1: Write README**

Create `C:\Users\IT Department\Desktop\azure-mailer\README.md`:

```markdown
# promoseven/azure-mailer

Laravel mail transport for Microsoft 365 via the Azure AD Graph API.
Replaces SMTP with a Client Credentials OAuth2 flow — drop-in compatible with
Laravel's `Mail` facade, Mailables, Notifications, and queued mail.

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- An Azure AD App Registration with `Mail.Send` application permission

## Installation

```bash
composer require promoseven/azure-mailer
```

## Azure AD Setup

1. Go to **Azure Portal → App Registrations → New registration**
2. Note the **Tenant ID**, **Client ID**
3. Under **Certificates & secrets**, create a new **Client secret**
4. Under **API permissions**, add **Microsoft Graph → Application permissions → Mail.Send**
5. Click **Grant admin consent**

## Configuration

Add to `config/mail.php` under `mailers`:

```php
'azure' => [
    'transport'     => 'azure',
    'tenant_id'     => env('AZURE_TENANT_ID'),
    'client_id'     => env('AZURE_CLIENT_ID'),
    'client_secret' => env('AZURE_CLIENT_SECRET'),
    'from_address'  => env('AZURE_MAIL_FROM_ADDRESS'),
],
```

Set `.env`:

```env
MAIL_MAILER=azure

AZURE_TENANT_ID=your-tenant-id
AZURE_CLIENT_ID=your-client-id
AZURE_CLIENT_SECRET=your-client-secret
AZURE_MAIL_FROM_ADDRESS=noreply@yourdomain.com
```

The `from_address` must be a mailbox in your Microsoft 365 tenant.

## Advanced config (optional)

Publish the config file to override defaults:

```bash
php artisan vendor:publish --tag=azure-mailer-config
```

This creates `config/azure-mailer.php`:

```php
return [
    'save_to_sent_items' => false,  // set true to keep copies in Sent folder
    'timeout'            => 30,     // HTTP timeout in seconds
    'graph_api_version'  => 'v1.0', // or 'beta'
];
```

## Usage

No changes needed — use Laravel mail exactly as before:

```php
Mail::to('user@example.com')->send(new OrderConfirmation($order));
```

## License

MIT
```

- [ ] **Step 2: Commit README**

Run from `C:\Users\IT Department\Desktop\azure-mailer`:

```powershell
git add README.md
git commit -m "docs: add README with installation and Azure AD setup guide"
```

---

## Final verification

- [ ] **Run the full package test suite one last time**

Run from `C:\Users\IT Department\Desktop\azure-mailer`:

```powershell
vendor/bin/phpunit --testdox
```

Expected: All tests pass, output shows each test name.

- [ ] **Verify OperationModule still boots**

Run from `C:\Users\IT Department\Desktop\OperationModule`:

```powershell
php artisan about
```

Expected: No errors, `promoseven/azure-mailer` visible in package list.
