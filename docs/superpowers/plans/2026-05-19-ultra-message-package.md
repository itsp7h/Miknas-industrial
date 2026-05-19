# Ultra Message Package — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `promoseven/ultra-message`, a reusable Laravel package that wraps the UltraMSG WhatsApp API, then integrate it into OperationModule with a database-backed Settings UI and per-event notification classes.

**Architecture:** Three phases. Phase 1 builds the standalone Composer package (separate directory/repo). Phase 2 installs it into OperationModule and wires up the Settings UI + dynamic config. Phase 3 wires individual Laravel Notification classes to ERP events.

**Tech Stack:** PHP 8.2, Laravel 12, UltraMSG REST API, Orchestra Testbench 10 (package tests), PHPUnit 11, SQLite (OperationModule DB), Tailwind CSS + Alpine.js (Settings UI)

---

## File Map

### Phase 1 — Package (new directory: `../ultra-message/` — sibling to OperationModule)

| Action | Path | Responsibility |
|--------|------|----------------|
| Create | `composer.json` | Package metadata, autoloading, Laravel discovery |
| Create | `config/ultra-message.php` | Default config values |
| Create | `src/UltraMessageServiceProvider.php` | Register client singleton, load config/routes |
| Create | `src/UltraMessageClient.php` | All HTTP calls to UltraMSG API |
| Create | `src/UltraMessageMessage.php` | Fluent DTO for outbound messages |
| Create | `src/UltraMessageChannel.php` | Laravel Notification channel |
| Create | `src/UltraMessageFake.php` | Test double — records sends, no HTTP |
| Create | `src/UltraMessageException.php` | Single exception type for all API errors |
| Create | `src/Facades/UltraMessage.php` | Laravel Facade → UltraMessageClient |
| Create | `src/Events/UltraMessageWebhookReceived.php` | Event fired on incoming webhook |
| Create | `src/Http/Controllers/WebhookController.php` | Handles POST to webhook route |
| Create | `routes/webhook.php` | Registers webhook POST route |
| Create | `tests/UltraMessageClientTest.php` | Unit tests for the client |
| Create | `tests/UltraMessageChannelTest.php` | Unit tests for the channel |
| Create | `tests/TestCase.php` | Orchestra Testbench base test case |

### Phase 2 — OperationModule Integration

| Action | Path | Responsibility |
|--------|------|----------------|
| Modify | `composer.json` | Add VCS repository + require package |
| Create | `database/migrations/xxxx_create_settings_table.php` | `settings` key/value table |
| Create | `database/migrations/xxxx_add_whatsapp_number_to_suppliers.php` | `whatsapp_number` column |
| Create | `database/migrations/xxxx_add_whatsapp_number_to_customers.php` | `whatsapp_number` column |
| Create | `database/migrations/xxxx_add_whatsapp_number_to_users.php` | `whatsapp_number` column |
| Create | `app/Models/Setting.php` | Key/value model with static get/set helpers |
| Modify | `app/Models/Supplier.php` | Add `routeNotificationFor('ultra_message')` |
| Modify | `app/Models/Customer.php` | Add `routeNotificationFor('ultra_message')` |
| Modify | `app/Models/User.php` | Add `routeNotificationFor('ultra_message')` |
| Modify | `app/Providers/AppServiceProvider.php` | Boot dynamic config resolver |
| Modify | `bootstrap/app.php` | Exclude webhook path from CSRF |
| Create | `app/Http/Controllers/SettingsController.php` | Show/update integration settings |
| Create | `resources/views/settings/integrations.blade.php` | WhatsApp settings form |
| Modify | `routes/web.php` | Add settings routes |
| Modify | `resources/views/layouts/navigation.blade.php` | Add Settings sidebar link (Admin only) |
| Modify | `resources/views/purchase/suppliers/create.blade.php` | Add whatsapp_number field |
| Modify | `resources/views/purchase/suppliers/edit.blade.php` | Add whatsapp_number field |
| Modify | `resources/views/sales/customers/create.blade.php` | Add whatsapp_number field |
| Modify | `resources/views/sales/customers/edit.blade.php` | Add whatsapp_number field |
| Modify | `app/Http/Controllers/Purchase/SupplierController.php` | Store/update whatsapp_number |
| Modify | `app/Http/Controllers/Sales/CustomerController.php` | Store/update whatsapp_number |

### Phase 3 — Notifications

| Action | Path | Responsibility |
|--------|------|----------------|
| Create | `app/Notifications/Purchase/PurchaseOrderConfirmedNotification.php` | Notify supplier on PO confirm |
| Create | `app/Notifications/Purchase/GoodsReceiptConfirmedNotification.php` | Notify store manager on GRN confirm |
| Create | `app/Notifications/Sales/SalesOrderConfirmedNotification.php` | Notify customer on SO confirm |
| Create | `app/Notifications/Sales/InvoiceCreatedNotification.php` | Notify customer on invoice creation |
| Create | `app/Notifications/Sales/DeliveryDispatchedNotification.php` | Notify customer on dispatch |
| Create | `app/Notifications/Inventory/LowStockAlertNotification.php` | Notify store manager on low stock |
| Create | `app/Notifications/Production/ProductionOrderCompletedNotification.php` | Notify production manager on completion |
| Modify | `app/Http/Controllers/Purchase/PurchaseOrderController.php` | Trigger PO confirmed notification |
| Modify | `app/Http/Controllers/Purchase/GoodsReceiptNoteController.php` | Trigger GRN confirmed notification |
| Modify | `app/Http/Controllers/Sales/SalesOrderController.php` | Trigger SO confirmed notification |
| Modify | `app/Http/Controllers/Sales/SalesInvoiceController.php` | Trigger invoice created notification |
| Modify | `app/Http/Controllers/Sales/DeliveryNoteController.php` | Trigger dispatch notification |
| Modify | `app/Http/Controllers/Inventory/StockMovementController.php` | Trigger low stock check + notification |
| Modify | `app/Http/Controllers/Production/ProductionOrderController.php` | Trigger production complete notification |

---

## PHASE 1 — The Package

> Work in a new directory: create `ultra-message/` as a sibling to `OperationModule/` (e.g. `C:\Users\IT Department\Desktop\ultra-message\`).

---

### Task 1: Package scaffold — composer.json and directory structure

**Files:**
- Create: `ultra-message/composer.json`
- Create: `ultra-message/src/` (empty dir placeholder)
- Create: `ultra-message/config/ultra-message.php`
- Create: `ultra-message/routes/webhook.php`
- Create: `ultra-message/tests/TestCase.php`

- [ ] **Step 1: Create the package directory and composer.json**

```bash
mkdir -p ultra-message/src/Facades ultra-message/src/Events ultra-message/src/Http/Controllers ultra-message/config ultra-message/routes ultra-message/tests
```

Create `ultra-message/composer.json`:

```json
{
    "name": "promoseven/ultra-message",
    "description": "Laravel WhatsApp integration via UltraMSG API",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.2",
        "illuminate/support": "^11.0|^12.0",
        "illuminate/http": "^11.0|^12.0",
        "illuminate/notifications": "^11.0|^12.0",
        "illuminate/routing": "^11.0|^12.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "orchestra/testbench": "^10.0"
    },
    "autoload": {
        "psr-4": {
            "PromoSeven\\UltraMessage\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "PromoSeven\\UltraMessage\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "PromoSeven\\UltraMessage\\UltraMessageServiceProvider"
            ],
            "aliases": {
                "UltraMessage": "PromoSeven\\UltraMessage\\Facades\\UltraMessage"
            }
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

- [ ] **Step 2: Create the default config file**

Create `ultra-message/config/ultra-message.php`:

```php
<?php

return [
    'instance_id'    => env('ULTRAMSG_INSTANCE_ID'),
    'token'          => env('ULTRAMSG_TOKEN'),
    'webhook_secret' => env('ULTRAMSG_WEBHOOK_SECRET', null),
    'webhook_path'   => env('ULTRAMSG_WEBHOOK_PATH', 'ultra-message/webhook'),
    'timeout'        => env('ULTRAMSG_TIMEOUT', 30),
    'enabled'        => env('ULTRAMSG_ENABLED', true),
];
```

- [ ] **Step 3: Create the Orchestra Testbench base test case**

Create `ultra-message/tests/TestCase.php`:

```php
<?php

namespace PromoSeven\UltraMessage\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use PromoSeven\UltraMessage\UltraMessageServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [UltraMessageServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('ultra-message.instance_id', 'instance123');
        $app['config']->set('ultra-message.token', 'test-token');
        $app['config']->set('ultra-message.enabled', true);
    }
}
```

- [ ] **Step 4: Run composer install**

```bash
cd ultra-message && composer install
```

Expected: Vendor directory created, no errors.

- [ ] **Step 5: Commit**

```bash
cd ultra-message
git init
git add .
git commit -m "feat: scaffold ultra-message package"
```

---

### Task 2: UltraMessageException

**Files:**
- Create: `ultra-message/src/UltraMessageException.php`

- [ ] **Step 1: Create the exception class**

Create `ultra-message/src/UltraMessageException.php`:

```php
<?php

namespace PromoSeven\UltraMessage;

use RuntimeException;

class UltraMessageException extends RuntimeException {}
```

- [ ] **Step 2: Commit**

```bash
git add src/UltraMessageException.php
git commit -m "feat: add UltraMessageException"
```

---

### Task 3: UltraMessageClient — core HTTP wrapper

**Files:**
- Create: `ultra-message/src/UltraMessageClient.php`
- Create: `ultra-message/tests/UltraMessageClientTest.php`

- [ ] **Step 1: Write the failing tests**

Create `ultra-message/tests/UltraMessageClientTest.php`:

```php
<?php

namespace PromoSeven\UltraMessage\Tests;

use Illuminate\Support\Facades\Http;
use PromoSeven\UltraMessage\UltraMessageClient;
use PromoSeven\UltraMessage\UltraMessageException;

class UltraMessageClientTest extends TestCase
{
    private UltraMessageClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new UltraMessageClient([
            'instance_id' => 'instance123',
            'token'       => 'test-token',
            'timeout'     => 30,
            'enabled'     => true,
        ]);
    }

    public function test_send_text_posts_correct_payload(): void
    {
        Http::fake([
            'api.ultramsg.com/*' => Http::response(['sent' => 'true', 'id' => 'msg1'], 200),
        ]);

        $result = $this->client->sendText('+971501234567', 'Hello World');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'instance123/messages/chat')
                && $request['to'] === '+971501234567'
                && $request['body'] === 'Hello World'
                && $request['token'] === 'test-token';
        });

        $this->assertEquals(['sent' => 'true', 'id' => 'msg1'], $result);
    }

    public function test_send_text_throws_on_api_error(): void
    {
        Http::fake([
            'api.ultramsg.com/*' => Http::response(['error' => 'invalid token'], 200),
        ]);

        $this->expectException(UltraMessageException::class);
        $this->expectExceptionMessage('invalid token');

        $this->client->sendText('+971501234567', 'Hello');
    }

    public function test_send_text_throws_on_http_failure(): void
    {
        Http::fake([
            'api.ultramsg.com/*' => Http::response([], 500),
        ]);

        $this->expectException(UltraMessageException::class);

        $this->client->sendText('+971501234567', 'Hello');
    }

    public function test_send_returns_early_when_disabled(): void
    {
        Http::fake();

        $client = new UltraMessageClient([
            'instance_id' => 'instance123',
            'token'       => 'test-token',
            'timeout'     => 30,
            'enabled'     => false,
        ]);

        $result = $client->sendText('+971501234567', 'Hello');

        Http::assertNothingSent();
        $this->assertEquals([], $result);
    }

    public function test_send_image_posts_correct_payload(): void
    {
        Http::fake([
            'api.ultramsg.com/*' => Http::response(['sent' => 'true'], 200),
        ]);

        $this->client->sendImage('+971501234567', 'https://example.com/img.jpg', 'Caption');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'instance123/messages/image')
                && $request['image'] === 'https://example.com/img.jpg'
                && $request['caption'] === 'Caption';
        });
    }

    public function test_send_document_posts_correct_payload(): void
    {
        Http::fake([
            'api.ultramsg.com/*' => Http::response(['sent' => 'true'], 200),
        ]);

        $this->client->sendDocument('+971501234567', 'https://example.com/file.pdf', 'invoice.pdf', 'Your invoice');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'instance123/messages/document')
                && $request['document'] === 'https://example.com/file.pdf'
                && $request['filename'] === 'invoice.pdf'
                && $request['caption'] === 'Your invoice';
        });
    }

    public function test_send_location_posts_correct_payload(): void
    {
        Http::fake([
            'api.ultramsg.com/*' => Http::response(['sent' => 'true'], 200),
        ]);

        $this->client->sendLocation('+971501234567', 25.197197, 55.2721877, 'Dubai, UAE');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'instance123/messages/location')
                && $request['lat'] == 25.197197
                && $request['lng'] == 55.2721877
                && $request['address'] === 'Dubai, UAE';
        });
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
cd ultra-message && ./vendor/bin/phpunit tests/UltraMessageClientTest.php
```

Expected: ERRORS — `UltraMessageClient` class not found.

- [ ] **Step 3: Implement UltraMessageClient**

Create `ultra-message/src/UltraMessageClient.php`:

```php
<?php

namespace PromoSeven\UltraMessage;

use Illuminate\Support\Facades\Http;

class UltraMessageClient
{
    private const BASE_URL = 'https://api.ultramsg.com';

    private string $instanceId;
    private string $token;
    private int $timeout;
    private bool $enabled;

    public function __construct(array $config)
    {
        $this->instanceId = $config['instance_id'] ?? '';
        $this->token      = $config['token'] ?? '';
        $this->timeout    = $config['timeout'] ?? 30;
        $this->enabled    = $config['enabled'] ?? true;
    }

    protected function post(string $endpoint, array $data): array
    {
        if (!$this->enabled) {
            return [];
        }

        $response = Http::timeout($this->timeout)
            ->asForm()
            ->post(self::BASE_URL . "/{$this->instanceId}/{$endpoint}", array_merge($data, [
                'token' => $this->token,
            ]));

        if ($response->failed()) {
            throw new UltraMessageException("UltraMSG HTTP error: {$response->status()}");
        }

        $body = $response->json() ?? [];

        if (isset($body['error'])) {
            throw new UltraMessageException($body['error']);
        }

        return $body;
    }

    protected function get(string $endpoint, array $query = []): array
    {
        if (!$this->enabled) {
            return [];
        }

        $response = Http::timeout($this->timeout)
            ->get(self::BASE_URL . "/{$this->instanceId}/{$endpoint}", array_merge($query, [
                'token' => $this->token,
            ]));

        if ($response->failed()) {
            throw new UltraMessageException("UltraMSG HTTP error: {$response->status()}");
        }

        $body = $response->json() ?? [];

        if (isset($body['error'])) {
            throw new UltraMessageException($body['error']);
        }

        return $body;
    }

    public function sendText(string $to, string $message, ?string $replyId = null): array
    {
        $data = ['to' => $to, 'body' => $message];
        if ($replyId !== null) {
            $data['quoted_id'] = $replyId;
        }
        return $this->post('messages/chat', $data);
    }

    public function sendImage(string $to, string $imageUrl, string $caption = ''): array
    {
        return $this->post('messages/image', [
            'to'      => $to,
            'image'   => $imageUrl,
            'caption' => $caption,
        ]);
    }

    public function sendDocument(string $to, string $fileUrl, string $filename, string $caption = ''): array
    {
        return $this->post('messages/document', [
            'to'       => $to,
            'document' => $fileUrl,
            'filename' => $filename,
            'caption'  => $caption,
        ]);
    }

    public function sendAudio(string $to, string $audioUrl): array
    {
        return $this->post('messages/audio', [
            'to'    => $to,
            'audio' => $audioUrl,
        ]);
    }

    public function sendVoice(string $to, string $audioUrl): array
    {
        return $this->post('messages/voice', [
            'to'    => $to,
            'audio' => $audioUrl,
        ]);
    }

    public function sendVideo(string $to, string $videoUrl, string $caption = ''): array
    {
        return $this->post('messages/video', [
            'to'      => $to,
            'video'   => $videoUrl,
            'caption' => $caption,
        ]);
    }

    public function sendSticker(string $to, string $stickerUrl): array
    {
        return $this->post('messages/sticker', [
            'to'      => $to,
            'sticker' => $stickerUrl,
        ]);
    }

    public function sendContact(string $to, string $contactId): array
    {
        return $this->post('messages/contact', [
            'to'      => $to,
            'contact' => $contactId,
        ]);
    }

    public function sendLocation(string $to, float $lat, float $lng, string $address = ''): array
    {
        return $this->post('messages/location', [
            'to'      => $to,
            'lat'     => $lat,
            'lng'     => $lng,
            'address' => $address,
        ]);
    }

    public function sendReaction(string $to, string $messageId, string $emoji): array
    {
        return $this->post('messages/reaction', [
            'to'    => $to,
            'msgId' => $messageId,
            'emoji' => $emoji,
        ]);
    }

    public function deleteMessage(string $messageId): array
    {
        return $this->post('messages/delete', [
            'msgId' => $messageId,
        ]);
    }

    public function getInstanceStatus(): array
    {
        return $this->get('instance/status');
    }

    public function getChats(): array
    {
        return $this->get('chats/');
    }

    public function getContacts(): array
    {
        return $this->get('contacts/');
    }

    public function getGroups(): array
    {
        return $this->get('groups/');
    }
}
```

- [ ] **Step 4: Run tests — confirm they pass**

```bash
./vendor/bin/phpunit tests/UltraMessageClientTest.php
```

Expected: 5 tests, 5 assertions, PASS.

- [ ] **Step 5: Commit**

```bash
git add src/UltraMessageClient.php tests/UltraMessageClientTest.php
git commit -m "feat: implement UltraMessageClient with all send methods"
```

---

### Task 4: UltraMessageMessage DTO

**Files:**
- Create: `ultra-message/src/UltraMessageMessage.php`

- [ ] **Step 1: Create the DTO**

Create `ultra-message/src/UltraMessageMessage.php`:

```php
<?php

namespace PromoSeven\UltraMessage;

class UltraMessageMessage
{
    public string $type;
    public string $to = '';
    public array $payload = [];

    private function __construct(string $type, array $payload)
    {
        $this->type    = $type;
        $this->payload = $payload;
    }

    public static function text(string $message, ?string $replyId = null): self
    {
        return new self('text', ['body' => $message, 'quoted_id' => $replyId]);
    }

    public static function image(string $url, string $caption = ''): self
    {
        return new self('image', ['image' => $url, 'caption' => $caption]);
    }

    public static function document(string $url, string $filename, string $caption = ''): self
    {
        return new self('document', ['document' => $url, 'filename' => $filename, 'caption' => $caption]);
    }

    public static function audio(string $url): self
    {
        return new self('audio', ['audio' => $url]);
    }

    public static function voice(string $url): self
    {
        return new self('voice', ['audio' => $url]);
    }

    public static function video(string $url, string $caption = ''): self
    {
        return new self('video', ['video' => $url, 'caption' => $caption]);
    }

    public static function sticker(string $url): self
    {
        return new self('sticker', ['sticker' => $url]);
    }

    public static function contact(string $contactId): self
    {
        return new self('contact', ['contact' => $contactId]);
    }

    public static function location(float $lat, float $lng, string $address = ''): self
    {
        return new self('location', ['lat' => $lat, 'lng' => $lng, 'address' => $address]);
    }

    public function to(string $number): self
    {
        $this->to = $number;
        return $this;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/UltraMessageMessage.php
git commit -m "feat: add UltraMessageMessage DTO"
```

---

### Task 5: UltraMessageChannel

**Files:**
- Create: `ultra-message/src/UltraMessageChannel.php`
- Create: `ultra-message/tests/UltraMessageChannelTest.php`

- [ ] **Step 1: Write the failing tests**

Create `ultra-message/tests/UltraMessageChannelTest.php`:

```php
<?php

namespace PromoSeven\UltraMessage\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Notifications\Notification;
use PromoSeven\UltraMessage\UltraMessageChannel;
use PromoSeven\UltraMessage\UltraMessageClient;
use PromoSeven\UltraMessage\UltraMessageMessage;

class UltraMessageChannelTest extends TestCase
{
    public function test_channel_calls_send_text_for_text_message(): void
    {
        Http::fake(['api.ultramsg.com/*' => Http::response(['sent' => 'true'], 200)]);

        $client  = new UltraMessageClient([
            'instance_id' => 'instance123',
            'token'       => 'test-token',
            'timeout'     => 30,
            'enabled'     => true,
        ]);
        $channel = new UltraMessageChannel($client);

        $notifiable = new class {
            public string $whatsapp_number = '+971501234567';
            public function routeNotificationFor(string $channel, $notification = null): string
            {
                return $this->whatsapp_number;
            }
        };

        $notification = new class extends Notification {
            public function toUltraMessage($notifiable): UltraMessageMessage
            {
                return UltraMessageMessage::text('Test message');
            }
        };

        $channel->send($notifiable, $notification);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'messages/chat')
                && $request['body'] === 'Test message'
                && $request['to'] === '+971501234567';
        });
    }

    public function test_channel_uses_message_to_over_notifiable_route(): void
    {
        Http::fake(['api.ultramsg.com/*' => Http::response(['sent' => 'true'], 200)]);

        $client  = new UltraMessageClient([
            'instance_id' => 'instance123',
            'token'       => 'test-token',
            'timeout'     => 30,
            'enabled'     => true,
        ]);
        $channel = new UltraMessageChannel($client);

        $notifiable = new class {
            public function routeNotificationFor(string $channel, $notification = null): string
            {
                return '+9710000000';
            }
        };

        $notification = new class extends Notification {
            public function toUltraMessage($notifiable): UltraMessageMessage
            {
                return UltraMessageMessage::text('Override test')->to('+971999999');
            }
        };

        $channel->send($notifiable, $notification);

        Http::assertSent(fn($r) => $r['to'] === '+971999999');
    }

    public function test_channel_skips_when_no_recipient(): void
    {
        Http::fake();

        $client  = new UltraMessageClient([
            'instance_id' => 'instance123',
            'token'       => 'test-token',
            'timeout'     => 30,
            'enabled'     => true,
        ]);
        $channel = new UltraMessageChannel($client);

        $notifiable = new class {
            public function routeNotificationFor(string $channel, $notification = null): ?string
            {
                return null;
            }
        };

        $notification = new class extends Notification {
            public function toUltraMessage($notifiable): UltraMessageMessage
            {
                return UltraMessageMessage::text('No recipient');
            }
        };

        $channel->send($notifiable, $notification);

        Http::assertNothingSent();
    }
}
```

- [ ] **Step 2: Run tests — confirm they fail**

```bash
./vendor/bin/phpunit tests/UltraMessageChannelTest.php
```

Expected: ERRORS — `UltraMessageChannel` not found.

- [ ] **Step 3: Implement UltraMessageChannel**

Create `ultra-message/src/UltraMessageChannel.php`:

```php
<?php

namespace PromoSeven\UltraMessage;

use Illuminate\Notifications\Notification;

class UltraMessageChannel
{
    public function __construct(private UltraMessageClient $client) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toUltraMessage')) {
            return;
        }

        /** @var UltraMessageMessage $message */
        $message = $notification->toUltraMessage($notifiable);

        $to = $message->to ?: $notifiable->routeNotificationFor('ultra_message', $notification);

        if (!$to) {
            return;
        }

        match ($message->type) {
            'text'     => $this->client->sendText($to, $message->payload['body'], $message->payload['quoted_id'] ?? null),
            'image'    => $this->client->sendImage($to, $message->payload['image'], $message->payload['caption'] ?? ''),
            'document' => $this->client->sendDocument($to, $message->payload['document'], $message->payload['filename'], $message->payload['caption'] ?? ''),
            'audio'    => $this->client->sendAudio($to, $message->payload['audio']),
            'voice'    => $this->client->sendVoice($to, $message->payload['audio']),
            'video'    => $this->client->sendVideo($to, $message->payload['video'], $message->payload['caption'] ?? ''),
            'sticker'  => $this->client->sendSticker($to, $message->payload['sticker']),
            'contact'  => $this->client->sendContact($to, $message->payload['contact']),
            'location' => $this->client->sendLocation($to, $message->payload['lat'], $message->payload['lng'], $message->payload['address'] ?? ''),
            default    => throw new UltraMessageException("Unknown message type: {$message->type}"),
        };
    }
}
```

- [ ] **Step 4: Run tests — confirm they pass**

```bash
./vendor/bin/phpunit tests/UltraMessageChannelTest.php
```

Expected: 3 tests, PASS.

- [ ] **Step 5: Commit**

```bash
git add src/UltraMessageChannel.php tests/UltraMessageChannelTest.php
git commit -m "feat: implement UltraMessageChannel for Laravel Notifications"
```

---

### Task 6: UltraMessageFake (test double)

**Files:**
- Create: `ultra-message/src/UltraMessageFake.php`

- [ ] **Step 1: Create the fake**

Create `ultra-message/src/UltraMessageFake.php`:

```php
<?php

namespace PromoSeven\UltraMessage;

use PHPUnit\Framework\Assert;

class UltraMessageFake extends UltraMessageClient
{
    private array $sent = [];

    public function __construct()
    {
        // Skip parent constructor — no HTTP config needed in fake mode
    }

    protected function post(string $endpoint, array $data): array
    {
        $this->sent[] = ['endpoint' => $endpoint, 'data' => $data];
        return ['sent' => 'ok'];
    }

    protected function get(string $endpoint, array $query = []): array
    {
        return [];
    }

    public function assertSent(callable $callback): void
    {
        Assert::assertTrue(
            collect($this->sent)->contains($callback),
            'Expected UltraMessage was not sent.'
        );
    }

    public function assertNotSent(): void
    {
        Assert::assertEmpty($this->sent, 'Unexpected UltraMessage messages were sent.');
    }

    public function assertSentCount(int $count): void
    {
        Assert::assertCount($count, $this->sent, "Expected {$count} messages sent, got " . count($this->sent));
    }

    public function getSent(): array
    {
        return $this->sent;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/UltraMessageFake.php
git commit -m "feat: add UltraMessageFake for test support"
```

---

### Task 7: Facade + ServiceProvider

**Files:**
- Create: `ultra-message/src/Facades/UltraMessage.php`
- Create: `ultra-message/src/UltraMessageServiceProvider.php`

- [ ] **Step 1: Create the Facade**

Create `ultra-message/src/Facades/UltraMessage.php`:

```php
<?php

namespace PromoSeven\UltraMessage\Facades;

use Illuminate\Support\Facades\Facade;
use PromoSeven\UltraMessage\UltraMessageClient;
use PromoSeven\UltraMessage\UltraMessageFake;

/**
 * @method static array sendText(string $to, string $message, ?string $replyId = null)
 * @method static array sendImage(string $to, string $imageUrl, string $caption = '')
 * @method static array sendDocument(string $to, string $fileUrl, string $filename, string $caption = '')
 * @method static array sendAudio(string $to, string $audioUrl)
 * @method static array sendVoice(string $to, string $audioUrl)
 * @method static array sendVideo(string $to, string $videoUrl, string $caption = '')
 * @method static array sendSticker(string $to, string $stickerUrl)
 * @method static array sendContact(string $to, string $contactId)
 * @method static array sendLocation(string $to, float $lat, float $lng, string $address = '')
 * @method static array sendReaction(string $to, string $messageId, string $emoji)
 * @method static array deleteMessage(string $messageId)
 * @method static array getInstanceStatus()
 * @method static array getChats()
 * @method static array getContacts()
 * @method static array getGroups()
 *
 * @see \PromoSeven\UltraMessage\UltraMessageClient
 */
class UltraMessage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return UltraMessageClient::class;
    }

    public static function fake(): UltraMessageFake
    {
        $fake = new UltraMessageFake();
        static::swap($fake);
        return $fake;
    }

    public static function configUsing(callable $resolver): void
    {
        app()->instance('ultra-message.config-resolver', $resolver);
        app()->forgetInstance(UltraMessageClient::class);
    }
}
```

- [ ] **Step 2: Create the ServiceProvider**

Create `ultra-message/src/UltraMessageServiceProvider.php`:

```php
<?php

namespace PromoSeven\UltraMessage;

use Illuminate\Support\ServiceProvider;

class UltraMessageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ultra-message.php', 'ultra-message');

        $this->app->singleton('ultra-message.config-resolver', fn() => null);

        $this->app->singleton(UltraMessageClient::class, function ($app) {
            $resolver = $app->make('ultra-message.config-resolver');
            $config   = $resolver ? call_user_func($resolver) : config('ultra-message');

            return new UltraMessageClient($config);
        });

        $this->app->singleton(UltraMessageChannel::class, function ($app) {
            return new UltraMessageChannel($app->make(UltraMessageClient::class));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/ultra-message.php' => config_path('ultra-message.php'),
            ], 'ultra-message-config');
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/webhook.php');
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Facades/UltraMessage.php src/UltraMessageServiceProvider.php
git commit -m "feat: add Facade and ServiceProvider"
```

---

### Task 8: Webhook route + controller + event

**Files:**
- Create: `ultra-message/src/Events/UltraMessageWebhookReceived.php`
- Create: `ultra-message/src/Http/Controllers/WebhookController.php`
- Create: `ultra-message/routes/webhook.php`

- [ ] **Step 1: Create the event**

Create `ultra-message/src/Events/UltraMessageWebhookReceived.php`:

```php
<?php

namespace PromoSeven\UltraMessage\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UltraMessageWebhookReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly array $payload) {}
}
```

- [ ] **Step 2: Create the WebhookController**

Create `ultra-message/src/Http/Controllers/WebhookController.php`:

```php
<?php

namespace PromoSeven\UltraMessage\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PromoSeven\UltraMessage\Events\UltraMessageWebhookReceived;

class WebhookController extends Controller
{
    public function handle(Request $request): \Illuminate\Http\Response
    {
        $secret = config('ultra-message.webhook_secret');

        if ($secret) {
            $signature = $request->header('X-Hub-Signature-256', '');
            $expected  = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

            if (!hash_equals($expected, $signature)) {
                abort(403, 'Invalid webhook signature.');
            }
        }

        event(new UltraMessageWebhookReceived($request->all()));

        return response('OK', 200);
    }
}
```

- [ ] **Step 3: Create the webhook route file**

Create `ultra-message/routes/webhook.php`:

```php
<?php

use Illuminate\Support\Facades\Route;
use PromoSeven\UltraMessage\Http\Controllers\WebhookController;

Route::post(config('ultra-message.webhook_path', 'ultra-message/webhook'), [WebhookController::class, 'handle'])
    ->name('ultra-message.webhook');
```

- [ ] **Step 4: Run the full test suite**

```bash
./vendor/bin/phpunit
```

Expected: All tests pass (8 tests minimum).

- [ ] **Step 5: Commit**

```bash
git add src/Events/ src/Http/ routes/
git commit -m "feat: add webhook route, controller, and event"
```

---

### Task 9: Push package to GitHub

> Do this step when the user provides GitHub access.

- [ ] **Step 1: Create the GitHub repository named `ultra-message` under the Promoseven org**

- [ ] **Step 2: Push**

```bash
git remote add origin git@github.com:promoseven/ultra-message.git
git branch -M main
git push -u origin main
```

- [ ] **Step 3: Note the repo URL** — needed for OperationModule `composer.json`.

---

## PHASE 2 — OperationModule Integration

> All remaining steps are inside `C:\Users\IT Department\Desktop\OperationModule\`.

---

### Task 10: Add package to OperationModule via Composer

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Add VCS repository + require entry to composer.json**

In `composer.json`, add inside `"repositories"` (create the key if missing) and update `"require"`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/promoseven/ultra-message"
        }
    ],
    "require": {
        "php": "^8.2",
        "barryvdh/laravel-dompdf": "^3.1",
        "laravel/framework": "^12.0",
        "laravel/tinker": "^2.10.1",
        "phpoffice/phpspreadsheet": "^5.7",
        "promoseven/ultra-message": "dev-main",
        "spatie/laravel-permission": "^6.25"
    }
}
```

> **Until GitHub is set up:** use a local path repository instead:
> ```json
> {
>     "repositories": [
>         {
>             "type": "path",
>             "url": "../ultra-message"
>         }
>     ],
>     "require": {
>         "promoseven/ultra-message": "*"
>     }
> }
> ```

- [ ] **Step 2: Install**

```bash
composer require promoseven/ultra-message
```

Expected: Package installed, service provider auto-discovered, no errors.

- [ ] **Step 3: Publish config**

```bash
php artisan vendor:publish --tag=ultra-message-config
```

Expected: `config/ultra-message.php` created in OperationModule.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock config/ultra-message.php
git commit -m "feat: install ultra-message package"
```

---

### Task 11: Settings table migration + Setting model

**Files:**
- Create: `database/migrations/xxxx_create_settings_table.php`
- Create: `app/Models/Setting.php`

- [ ] **Step 1: Create the migration**

```bash
php artisan make:migration create_settings_table
```

Edit the generated file in `database/migrations/`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate
```

Expected: `settings` table created, no errors.

- [ ] **Step 3: Create the Setting model**

Create `app/Models/Setting.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/ app/Models/Setting.php
git commit -m "feat: add settings table and Setting model"
```

---

### Task 12: Add whatsapp_number to suppliers, customers, and users

**Files:**
- Create: 3 migration files
- Modify: `app/Models/Supplier.php`
- Modify: `app/Models/Customer.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Create the migrations**

```bash
php artisan make:migration add_whatsapp_number_to_suppliers_table
php artisan make:migration add_whatsapp_number_to_customers_table
php artisan make:migration add_whatsapp_number_to_users_table
```

For each, edit the generated file:

**`add_whatsapp_number_to_suppliers_table`:**
```php
public function up(): void
{
    Schema::table('suppliers', function (Blueprint $table) {
        $table->string('whatsapp_number')->nullable()->after('phone');
    });
}

public function down(): void
{
    Schema::table('suppliers', function (Blueprint $table) {
        $table->dropColumn('whatsapp_number');
    });
}
```

**`add_whatsapp_number_to_customers_table`:**
```php
public function up(): void
{
    Schema::table('customers', function (Blueprint $table) {
        $table->string('whatsapp_number')->nullable()->after('phone');
    });
}

public function down(): void
{
    Schema::table('customers', function (Blueprint $table) {
        $table->dropColumn('whatsapp_number');
    });
}
```

**`add_whatsapp_number_to_users_table`:**
```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('whatsapp_number')->nullable()->after('email');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('whatsapp_number');
    });
}
```

- [ ] **Step 2: Run migrations**

```bash
php artisan migrate
```

Expected: 3 columns added, no errors.

- [ ] **Step 3: Add routeNotificationFor to Supplier**

Read `app/Models/Supplier.php` first. Add this method to the class body:

```php
public function routeNotificationFor(string $channel, mixed $notification = null): ?string
{
    return $this->whatsapp_number;
}
```

Also add `'whatsapp_number'` to the `$fillable` array.

- [ ] **Step 4: Add routeNotificationFor to Customer**

Read `app/Models/Customer.php` first. Add to the class body:

```php
public function routeNotificationFor(string $channel, mixed $notification = null): ?string
{
    return $this->whatsapp_number;
}
```

Also add `'whatsapp_number'` to `$fillable`.

- [ ] **Step 5: Add routeNotificationFor to User**

Read `app/Models/User.php` first. Add to the class body:

```php
public function routeNotificationFor(string $channel, mixed $notification = null): ?string
{
    return $this->whatsapp_number;
}
```

Also add `'whatsapp_number'` to `$fillable`.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/ app/Models/Supplier.php app/Models/Customer.php app/Models/User.php
git commit -m "feat: add whatsapp_number field to suppliers, customers, users"
```

---

### Task 13: Dynamic config + CSRF exclusion in OperationModule

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `bootstrap/app.php`

- [ ] **Step 1: Boot dynamic config resolver in AppServiceProvider**

Read `app/Providers/AppServiceProvider.php`. In the `boot()` method, add:

```php
use App\Models\Setting;
use PromoSeven\UltraMessage\Facades\UltraMessage;

// Inside boot():
UltraMessage::configUsing(function () {
    return [
        'instance_id'    => Setting::get('ultramsg_instance_id', config('ultra-message.instance_id')),
        'token'          => Setting::get('ultramsg_token', config('ultra-message.token')),
        'webhook_secret' => Setting::get('ultramsg_webhook_secret', config('ultra-message.webhook_secret')),
        'webhook_path'   => Setting::get('ultramsg_webhook_path', config('ultra-message.webhook_path', 'ultra-message/webhook')),
        'timeout'        => config('ultra-message.timeout', 30),
        'enabled'        => (bool) Setting::get('ultramsg_enabled', config('ultra-message.enabled', true)),
    ];
});
```

- [ ] **Step 2: Exclude webhook path from CSRF in bootstrap/app.php**

Read `bootstrap/app.php`. Inside the `->withMiddleware(function (Middleware $middleware) {` block, add:

```php
$middleware->validateCsrfTokens(except: [
    config('ultra-message.webhook_path', 'ultra-message/webhook'),
    'ultra-message/*',
]);
```

- [ ] **Step 3: Commit**

```bash
git add app/Providers/AppServiceProvider.php bootstrap/app.php
git commit -m "feat: wire dynamic config resolver and exclude webhook from CSRF"
```

---

### Task 14: SettingsController + routes

**Files:**
- Create: `app/Http/Controllers/SettingsController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create SettingsController**

Create `app/Http/Controllers/SettingsController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PromoSeven\UltraMessage\Facades\UltraMessage;
use PromoSeven\UltraMessage\UltraMessageException;

class SettingsController extends Controller
{
    public function integrations(): View
    {
        $settings = [
            'enabled'         => Setting::get('ultramsg_enabled', false),
            'instance_id'     => Setting::get('ultramsg_instance_id', ''),
            'token'           => Setting::get('ultramsg_token', ''),
            'webhook_secret'  => Setting::get('ultramsg_webhook_secret', ''),
            'webhook_path'    => Setting::get('ultramsg_webhook_path', 'ultra-message/webhook'),
        ];

        return view('settings.integrations', compact('settings'));
    }

    public function updateWhatsapp(Request $request): RedirectResponse
    {
        $request->validate([
            'instance_id'    => ['required', 'string', 'max:100'],
            'token'          => ['required', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'webhook_path'   => ['required', 'string', 'max:100'],
        ]);

        Setting::set('ultramsg_enabled', $request->boolean('enabled') ? '1' : '0');
        Setting::set('ultramsg_instance_id', $request->instance_id);
        Setting::set('ultramsg_token', $request->token);
        Setting::set('ultramsg_webhook_secret', $request->webhook_secret ?? '');
        Setting::set('ultramsg_webhook_path', $request->webhook_path);

        return redirect()->route('settings.integrations')->with('success', 'WhatsApp settings saved.');
    }

    public function testWhatsappConnection(): \Illuminate\Http\JsonResponse
    {
        try {
            $status = UltraMessage::getInstanceStatus();
            return response()->json(['success' => true, 'status' => $status]);
        } catch (UltraMessageException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
```

- [ ] **Step 2: Add routes to routes/web.php**

Read `routes/web.php`. Inside the `auth` + `verified` middleware group, add at the end before the closing brace:

```php
// Settings
Route::middleware('role:Admin')->group(function () {
    Route::get('settings/integrations', [SettingsController::class, 'integrations'])->name('settings.integrations');
    Route::post('settings/integrations/whatsapp', [SettingsController::class, 'updateWhatsapp'])->name('settings.integrations.whatsapp');
    Route::get('settings/integrations/test-whatsapp', [SettingsController::class, 'testWhatsappConnection'])->name('settings.integrations.test-whatsapp');
});
```

Also add the import at the top of the file:
```php
use App\Http\Controllers\SettingsController;
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/SettingsController.php routes/web.php
git commit -m "feat: add SettingsController and settings routes"
```

---

### Task 15: Settings integrations view

**Files:**
- Create: `resources/views/settings/integrations.blade.php`

- [ ] **Step 1: Create the view directory**

```bash
mkdir -p resources/views/settings
```

- [ ] **Step 2: Create the view**

Create `resources/views/settings/integrations.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Settings — Integrations
        </h2>
    </x-slot>

    <div class="py-6 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- WhatsApp / UltraMSG Section --}}
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-3">
                <svg class="w-6 h-6 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
                <h3 class="text-lg font-semibold text-gray-900">WhatsApp (UltraMSG)</h3>
            </div>

            <form method="POST" action="{{ route('settings.integrations.whatsapp') }}" class="px-6 py-5 space-y-5">
                @csrf

                {{-- Enable toggle --}}
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Enable WhatsApp Notifications</p>
                        <p class="text-xs text-gray-500">When disabled, no messages will be sent regardless of other settings.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" name="enabled" value="1" class="sr-only peer"
                            {{ $settings['enabled'] ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-green-400 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                    </label>
                </div>

                <hr class="border-gray-200">

                {{-- Instance ID --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Instance ID</label>
                    <input type="text" name="instance_id" value="{{ old('instance_id', $settings['instance_id']) }}"
                        placeholder="e.g. instance123456"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 @error('instance_id') border-red-400 @enderror">
                    @error('instance_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- API Token --}}
                <div x-data="{ show: false }">
                    <label class="block text-sm font-medium text-gray-700 mb-1">API Token</label>
                    <div class="relative">
                        <input :type="show ? 'text' : 'password'" name="token"
                            value="{{ old('token', $settings['token']) }}"
                            placeholder="Your UltraMSG token"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 @error('token') border-red-400 @enderror">
                        <button type="button" @click="show = !show"
                            class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600">
                            <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    @error('token') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Webhook Secret --}}
                <div x-data="{ show: false }">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Webhook Secret <span class="text-gray-400 font-normal">(optional)</span></label>
                    <div class="relative">
                        <input :type="show ? 'text' : 'password'" name="webhook_secret"
                            value="{{ old('webhook_secret', $settings['webhook_secret']) }}"
                            placeholder="Leave empty to skip HMAC verification"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                        <button type="button" @click="show = !show"
                            class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600">
                            <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Webhook Path --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Webhook Path</label>
                    <div class="flex items-center gap-0">
                        <span class="inline-flex items-center px-3 py-2 text-sm text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-md">
                            {{ url('/') }}/
                        </span>
                        <input type="text" name="webhook_path"
                            value="{{ old('webhook_path', $settings['webhook_path']) }}"
                            class="flex-1 border border-gray-300 rounded-r-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 @error('webhook_path') border-red-400 @enderror">
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        Full URL: <strong>{{ url('/') }}/{{ $settings['webhook_path'] }}</strong> — paste this in your UltraMSG dashboard.
                    </p>
                    @error('webhook_path') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-between pt-2">
                    <button type="button" id="btn-test-connection"
                        class="inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800 underline underline-offset-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Test Connection
                    </button>
                    <div id="connection-status" class="text-sm hidden"></div>
                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-md">
                        Save Settings
                    </button>
                </div>

            </form>
        </div>

    </div>

    <script>
    document.getElementById('btn-test-connection').addEventListener('click', function () {
        var statusEl = document.getElementById('connection-status');
        statusEl.textContent = 'Testing…';
        statusEl.className = 'text-sm text-gray-500';
        statusEl.classList.remove('hidden');

        fetch('{{ route('settings.integrations.test-whatsapp') }}', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                statusEl.textContent = 'Connected';
                statusEl.className = 'text-sm text-green-600 font-medium';
            } else {
                statusEl.textContent = 'Failed: ' + data.message;
                statusEl.className = 'text-sm text-red-600 font-medium';
            }
        })
        .catch(function () {
            statusEl.textContent = 'Request failed.';
            statusEl.className = 'text-sm text-red-600 font-medium';
        });
    });
    </script>
</x-app-layout>
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/settings/
git commit -m "feat: add WhatsApp integration settings view"
```

---

### Task 16: Add Settings to sidebar (Admin only)

**Files:**
- Modify: `resources/views/layouts/navigation.blade.php`

- [ ] **Step 1: Read the current navigation file**

Read `resources/views/layouts/navigation.blade.php` and find where sidebar navigation links are listed.

- [ ] **Step 2: Add Settings link visible to Admin role only**

Find the last navigation `<a>` or nav item group in the sidebar and add after it:

```blade
@role('Admin')
<a href="{{ route('settings.integrations') }}"
   class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('settings.*') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
    </svg>
    Settings
</a>
@endrole
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/layouts/navigation.blade.php
git commit -m "feat: add Settings sidebar link for Admin role"
```

---

### Task 17: Add whatsapp_number field to Supplier and Customer forms

**Files:**
- Modify: `resources/views/purchase/suppliers/create.blade.php`
- Modify: `resources/views/purchase/suppliers/edit.blade.php`
- Modify: `resources/views/sales/customers/create.blade.php`
- Modify: `resources/views/sales/customers/edit.blade.php`
- Modify: `app/Http/Controllers/Purchase/SupplierController.php`
- Modify: `app/Http/Controllers/Sales/CustomerController.php`

- [ ] **Step 1: Read both supplier form views**

Read `resources/views/purchase/suppliers/create.blade.php` and `edit.blade.php`. Find where the `phone` field is rendered. After the phone field, add:

```blade
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Number</label>
    <input type="text" name="whatsapp_number"
        value="{{ old('whatsapp_number', $supplier->whatsapp_number ?? '') }}"
        placeholder="+971501234567"
        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
    <p class="text-xs text-gray-500 mt-1">International format. Used for WhatsApp notifications.</p>
</div>
```

For `create.blade.php`, use `old('whatsapp_number', '')`.

- [ ] **Step 2: Read both customer form views**

Same treatment for `resources/views/sales/customers/create.blade.php` and `edit.blade.php` — add after the phone field.

- [ ] **Step 3: Update SupplierController store/update**

Read `app/Http/Controllers/Purchase/SupplierController.php`. In `store()` and `update()` methods, add `'whatsapp_number'` to the validated fields and the `$supplier->fill()` / `Supplier::create()` call. Add to the validation rules:

```php
'whatsapp_number' => ['nullable', 'string', 'max:20'],
```

- [ ] **Step 4: Update CustomerController store/update**

Read `app/Http/Controllers/Sales/CustomerController.php`. Same as above — add `whatsapp_number` to validation and assignment.

- [ ] **Step 5: Commit**

```bash
git add resources/views/purchase/suppliers/ resources/views/sales/customers/ \
        app/Http/Controllers/Purchase/SupplierController.php \
        app/Http/Controllers/Sales/CustomerController.php
git commit -m "feat: add whatsapp_number field to supplier and customer forms"
```

---

## PHASE 3 — Notifications

> All files in `C:\Users\IT Department\Desktop\OperationModule\`.

---

### Task 18: PurchaseOrderConfirmedNotification

**Files:**
- Create: `app/Notifications/Purchase/PurchaseOrderConfirmedNotification.php`
- Modify: `app/Http/Controllers/Purchase/PurchaseOrderController.php`

- [ ] **Step 1: Create the notification**

```bash
mkdir -p app/Notifications/Purchase
```

Create `app/Notifications/Purchase/PurchaseOrderConfirmedNotification.php`:

```php
<?php

namespace App\Notifications\Purchase;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use PromoSeven\UltraMessage\UltraMessageChannel;
use PromoSeven\UltraMessage\UltraMessageMessage;

class PurchaseOrderConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private PurchaseOrder $order) {}

    public function via(mixed $notifiable): array
    {
        return [UltraMessageChannel::class];
    }

    public function toUltraMessage(mixed $notifiable): UltraMessageMessage
    {
        return UltraMessageMessage::text(
            "Dear {$notifiable->name},\n\nPurchase Order *#{$this->order->number}* has been confirmed.\n\nTotal: {$this->order->currency} {$this->order->total}\nExpected delivery: {$this->order->expected_date}\n\nThank you."
        );
    }
}
```

- [ ] **Step 2: Trigger notification in PurchaseOrderController**

Read `app/Http/Controllers/Purchase/PurchaseOrderController.php`. Find the method that confirms/stores a PO (likely `store()` or a `confirm()` action). After the PO is saved/confirmed, add:

```php
use App\Notifications\Purchase\PurchaseOrderConfirmedNotification;
use Illuminate\Support\Facades\Notification;

// After PO confirmed:
if ($order->supplier && $order->supplier->whatsapp_number) {
    $order->supplier->notify(new PurchaseOrderConfirmedNotification($order));
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Notifications/Purchase/PurchaseOrderConfirmedNotification.php \
        app/Http/Controllers/Purchase/PurchaseOrderController.php
git commit -m "feat: send WhatsApp notification on PO confirmed"
```

---

### Task 19: GoodsReceiptConfirmedNotification

**Files:**
- Create: `app/Notifications/Purchase/GoodsReceiptConfirmedNotification.php`
- Modify: `app/Http/Controllers/Purchase/GoodsReceiptNoteController.php`

- [ ] **Step 1: Create the notification**

Create `app/Notifications/Purchase/GoodsReceiptConfirmedNotification.php`:

```php
<?php

namespace App\Notifications\Purchase;

use App\Models\GoodsReceiptNote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use PromoSeven\UltraMessage\UltraMessageChannel;
use PromoSeven\UltraMessage\UltraMessageMessage;

class GoodsReceiptConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private GoodsReceiptNote $grn) {}

    public function via(mixed $notifiable): array
    {
        return [UltraMessageChannel::class];
    }

    public function toUltraMessage(mixed $notifiable): UltraMessageMessage
    {
        return UltraMessageMessage::text(
            "GRN *#{$this->grn->number}* has been confirmed and goods received.\n\nPO Reference: {$this->grn->purchaseOrder->number}\nDate: {$this->grn->date}"
        );
    }
}
```

- [ ] **Step 2: Trigger in GoodsReceiptNoteController confirm method**

Read `app/Http/Controllers/Purchase/GoodsReceiptNoteController.php`. In the `confirm()` method, after confirming the GRN, add:

```php
use App\Notifications\Purchase\GoodsReceiptConfirmedNotification;

// Notify the store manager(s)
$storeManagers = \App\Models\User::role('Store Manager')->whereNotNull('whatsapp_number')->get();
\Illuminate\Support\Facades\Notification::send($storeManagers, new GoodsReceiptConfirmedNotification($grn));
```

- [ ] **Step 3: Commit**

```bash
git add app/Notifications/Purchase/GoodsReceiptConfirmedNotification.php \
        app/Http/Controllers/Purchase/GoodsReceiptNoteController.php
git commit -m "feat: send WhatsApp notification on GRN confirmed"
```

---

### Task 20: SalesOrderConfirmedNotification

**Files:**
- Create: `app/Notifications/Sales/SalesOrderConfirmedNotification.php`
- Modify: `app/Http/Controllers/Sales/SalesOrderController.php`

- [ ] **Step 1: Create the notification**

```bash
mkdir -p app/Notifications/Sales
```

Create `app/Notifications/Sales/SalesOrderConfirmedNotification.php`:

```php
<?php

namespace App\Notifications\Sales;

use App\Models\SalesOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use PromoSeven\UltraMessage\UltraMessageChannel;
use PromoSeven\UltraMessage\UltraMessageMessage;

class SalesOrderConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private SalesOrder $order) {}

    public function via(mixed $notifiable): array
    {
        return [UltraMessageChannel::class];
    }

    public function toUltraMessage(mixed $notifiable): UltraMessageMessage
    {
        return UltraMessageMessage::text(
            "Dear {$notifiable->name},\n\nYour order *#{$this->order->number}* has been confirmed.\n\nTotal: {$this->order->currency} {$this->order->total}\n\nWe will keep you updated on the delivery status. Thank you for your business."
        );
    }
}
```

- [ ] **Step 2: Trigger in SalesOrderController confirm method**

Read `app/Http/Controllers/Sales/SalesOrderController.php`. In the `confirm()` method, after confirmation, add:

```php
use App\Notifications\Sales\SalesOrderConfirmedNotification;

if ($order->customer && $order->customer->whatsapp_number) {
    $order->customer->notify(new SalesOrderConfirmedNotification($order));
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Notifications/Sales/SalesOrderConfirmedNotification.php \
        app/Http/Controllers/Sales/SalesOrderController.php
git commit -m "feat: send WhatsApp notification on sales order confirmed"
```

---

### Task 21: InvoiceCreatedNotification

**Files:**
- Create: `app/Notifications/Sales/InvoiceCreatedNotification.php`
- Modify: `app/Http/Controllers/Sales/SalesInvoiceController.php`

- [ ] **Step 1: Create the notification**

Create `app/Notifications/Sales/InvoiceCreatedNotification.php`:

```php
<?php

namespace App\Notifications\Sales;

use App\Models\SalesInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use PromoSeven\UltraMessage\UltraMessageChannel;
use PromoSeven\UltraMessage\UltraMessageMessage;

class InvoiceCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private SalesInvoice $invoice) {}

    public function via(mixed $notifiable): array
    {
        return [UltraMessageChannel::class];
    }

    public function toUltraMessage(mixed $notifiable): UltraMessageMessage
    {
        return UltraMessageMessage::text(
            "Dear {$notifiable->name},\n\nInvoice *#{$this->invoice->number}* is ready.\n\nAmount Due: {$this->invoice->currency} {$this->invoice->total}\nDue Date: {$this->invoice->due_date}\n\nPlease arrange payment at your earliest convenience. Thank you."
        );
    }
}
```

- [ ] **Step 2: Trigger in SalesInvoiceController store method**

Read `app/Http/Controllers/Sales/SalesInvoiceController.php`. In `store()`, after the invoice is saved, add:

```php
use App\Notifications\Sales\InvoiceCreatedNotification;

if ($invoice->salesOrder?->customer && $invoice->salesOrder->customer->whatsapp_number) {
    $invoice->salesOrder->customer->notify(new InvoiceCreatedNotification($invoice));
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Notifications/Sales/InvoiceCreatedNotification.php \
        app/Http/Controllers/Sales/SalesInvoiceController.php
git commit -m "feat: send WhatsApp notification on sales invoice created"
```

---

### Task 22: DeliveryDispatchedNotification

**Files:**
- Create: `app/Notifications/Sales/DeliveryDispatchedNotification.php`
- Modify: `app/Http/Controllers/Sales/DeliveryNoteController.php`

- [ ] **Step 1: Create the notification**

Create `app/Notifications/Sales/DeliveryDispatchedNotification.php`:

```php
<?php

namespace App\Notifications\Sales;

use App\Models\DeliveryNote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use PromoSeven\UltraMessage\UltraMessageChannel;
use PromoSeven\UltraMessage\UltraMessageMessage;

class DeliveryDispatchedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private DeliveryNote $delivery) {}

    public function via(mixed $notifiable): array
    {
        return [UltraMessageChannel::class];
    }

    public function toUltraMessage(mixed $notifiable): UltraMessageMessage
    {
        return UltraMessageMessage::text(
            "Dear {$notifiable->name},\n\nYour delivery *#{$this->delivery->number}* has been dispatched and is on its way.\n\nOrder Reference: {$this->delivery->salesOrder->number}\nDispatch Date: {$this->delivery->dispatch_date}\n\nThank you for your business."
        );
    }
}
```

- [ ] **Step 2: Trigger in DeliveryNoteController dispatch method**

Read `app/Http/Controllers/Sales/DeliveryNoteController.php`. In the `dispatch()` method, after dispatch, add:

```php
use App\Notifications\Sales\DeliveryDispatchedNotification;

if ($delivery->salesOrder?->customer && $delivery->salesOrder->customer->whatsapp_number) {
    $delivery->salesOrder->customer->notify(new DeliveryDispatchedNotification($delivery));
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Notifications/Sales/DeliveryDispatchedNotification.php \
        app/Http/Controllers/Sales/DeliveryNoteController.php
git commit -m "feat: send WhatsApp notification on delivery dispatched"
```

---

### Task 23: LowStockAlertNotification

**Files:**
- Create: `app/Notifications/Inventory/LowStockAlertNotification.php`
- Modify: `app/Http/Controllers/Inventory/StockMovementController.php`

- [ ] **Step 1: Create the notification**

```bash
mkdir -p app/Notifications/Inventory
```

Create `app/Notifications/Inventory/LowStockAlertNotification.php`:

```php
<?php

namespace App\Notifications\Inventory;

use App\Models\Item;
use App\Models\StockLevel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use PromoSeven\UltraMessage\UltraMessageChannel;
use PromoSeven\UltraMessage\UltraMessageMessage;

class LowStockAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Item $item, private StockLevel $stockLevel) {}

    public function via(mixed $notifiable): array
    {
        return [UltraMessageChannel::class];
    }

    public function toUltraMessage(mixed $notifiable): UltraMessageMessage
    {
        return UltraMessageMessage::text(
            "⚠️ *Low Stock Alert*\n\nItem: *{$this->item->name}* ({$this->item->code})\nCurrent Stock: {$this->stockLevel->quantity} {$this->item->unit}\nReorder Level: {$this->item->reorder_level} {$this->item->unit}\n\nPlease raise a purchase request."
        );
    }
}
```

- [ ] **Step 2: Trigger in StockMovementController after stock is reduced**

Read `app/Http/Controllers/Inventory/StockMovementController.php`. In the `store()` method, after the stock movement is recorded, add a check:

```php
use App\Notifications\Inventory\LowStockAlertNotification;

// After stock movement saved — check if item is now below reorder level
$stockLevel = \App\Models\StockLevel::where('item_id', $movement->item_id)
    ->where('warehouse_id', $movement->warehouse_id)
    ->first();

if ($stockLevel && $movement->item->reorder_level && $stockLevel->quantity <= $movement->item->reorder_level) {
    $storeManagers = \App\Models\User::role('Store Manager')->whereNotNull('whatsapp_number')->get();
    \Illuminate\Support\Facades\Notification::send(
        $storeManagers,
        new LowStockAlertNotification($movement->item, $stockLevel)
    );
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Notifications/Inventory/LowStockAlertNotification.php \
        app/Http/Controllers/Inventory/StockMovementController.php
git commit -m "feat: send WhatsApp low stock alert to store managers"
```

---

### Task 24: ProductionOrderCompletedNotification

**Files:**
- Create: `app/Notifications/Production/ProductionOrderCompletedNotification.php`
- Modify: `app/Http/Controllers/Production/ProductionOrderController.php`

- [ ] **Step 1: Create the notification**

```bash
mkdir -p app/Notifications/Production
```

Create `app/Notifications/Production/ProductionOrderCompletedNotification.php`:

```php
<?php

namespace App\Notifications\Production;

use App\Models\ProductionOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use PromoSeven\UltraMessage\UltraMessageChannel;
use PromoSeven\UltraMessage\UltraMessageMessage;

class ProductionOrderCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private ProductionOrder $order) {}

    public function via(mixed $notifiable): array
    {
        return [UltraMessageChannel::class];
    }

    public function toUltraMessage(mixed $notifiable): UltraMessageMessage
    {
        return UltraMessageMessage::text(
            "✅ *Production Complete*\n\nProduction Order *#{$this->order->number}* has been completed.\n\nProduct: {$this->order->product->name}\nQuantity: {$this->order->quantity}\nCompleted: " . now()->format('d M Y')
        );
    }
}
```

- [ ] **Step 2: Trigger in ProductionOrderController complete method**

Read `app/Http/Controllers/Production/ProductionOrderController.php`. In the `complete()` method, after completion, add:

```php
use App\Notifications\Production\ProductionOrderCompletedNotification;

$productionManagers = \App\Models\User::role('Production Manager')->whereNotNull('whatsapp_number')->get();
\Illuminate\Support\Facades\Notification::send(
    $productionManagers,
    new ProductionOrderCompletedNotification($order)
);
```

- [ ] **Step 3: Commit**

```bash
git add app/Notifications/Production/ProductionOrderCompletedNotification.php \
        app/Http/Controllers/Production/ProductionOrderController.php
git commit -m "feat: send WhatsApp notification on production order completed"
```

---

## Self-Review Checklist

**Spec coverage:**
- [x] Package structure (Tasks 1–8)
- [x] Config + dynamic config resolver (Tasks 7, 13)
- [x] UltraMessageClient — all 16 send/info methods (Task 3)
- [x] UltraMessageMessage DTO — all types (Task 4)
- [x] UltraMessageChannel (Task 5)
- [x] UltraMessageFake + assertSent/assertNotSent/assertSentCount (Task 6)
- [x] Facade with fake() and configUsing() (Task 7)
- [x] Webhook route + HMAC verification + event fire (Task 8)
- [x] GitHub push (Task 9)
- [x] Settings table + Setting model (Task 11)
- [x] whatsapp_number on Supplier/Customer/User + routeNotificationFor (Task 12)
- [x] CSRF exclusion for webhook (Task 13)
- [x] SettingsController with test connection (Task 14)
- [x] Settings UI view with masked fields + test button (Task 15)
- [x] Sidebar link (Admin only) (Task 16)
- [x] WhatsApp field on Supplier + Customer forms (Task 17)
- [x] All 7 notification classes (Tasks 18–24)
- [x] All 7 notification triggers in controllers (Tasks 18–24)

**No placeholders found.**

**Type consistency:** `UltraMessageMessage::text/image/document/...` constructors defined in Task 4, used identically in Tasks 5 and 18–24. `UltraMessageChannel` + `UltraMessageClient` method names match throughout.
