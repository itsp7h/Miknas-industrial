# Ultra Message — Laravel WhatsApp Package Design

**Date:** 2026-05-19
**Project:** OperationModule (SteelERP) + reusable across future Laravel projects
**Package name:** `promoseven/ultra-message`
**API provider:** UltraMSG (https://docs.ultramsg.com/)

---

## Overview

A reusable Laravel package that wraps the UltraMSG WhatsApp API. It ships as a standalone Composer package hosted on a private GitHub repo and is installed via VCS in any Laravel project. It integrates with Laravel's Notification system, supports queuing, exposes a Facade for one-liner sends, and handles incoming webhooks by firing a generic Laravel event. OperationModule adds a database-backed Settings UI so admins can configure credentials without touching `.env`.

---

## 1. Package Repository

- **Repo name:** `ultra-message` (GitHub, under Promoseven org)
- **Composer name:** `promoseven/ultra-message`
- **Required in projects via** `composer.json` VCS entry pointing to the GitHub repo
- **Auto-discovered** via Laravel's package discovery (`extra.laravel.providers`)

---

## 2. Package File Structure

```
ultra-message/
├── src/
│   ├── UltraMessageServiceProvider.php
│   ├── UltraMessageClient.php
│   ├── UltraMessageChannel.php
│   ├── UltraMessageMessage.php
│   ├── UltraMessageException.php
│   ├── Facades/
│   │   └── UltraMessage.php
│   └── Events/
│       └── UltraMessageWebhookReceived.php
├── config/
│   └── ultra-message.php
├── routes/
│   └── webhook.php
├── tests/
│   ├── UltraMessageClientTest.php
│   └── UltraMessageChannelTest.php
├── composer.json
└── README.md
```

---

## 3. Configuration (`config/ultra-message.php`)

```php
return [
    'instance_id'    => env('ULTRAMSG_INSTANCE_ID'),
    'token'          => env('ULTRAMSG_TOKEN'),
    'webhook_secret' => env('ULTRAMSG_WEBHOOK_SECRET', null),
    'webhook_path'   => env('ULTRAMSG_WEBHOOK_PATH', 'ultra-message/webhook'),
    'timeout'        => env('ULTRAMSG_TIMEOUT', 30),
    'enabled'        => env('ULTRAMSG_ENABLED', true),
];
```

**Dynamic config override:** The service provider exposes `UltraMessage::configUsing(callable $resolver)`. When set, the resolver is called at runtime to return an array of config values — used by OperationModule to read credentials from the database instead of `.env`.

```php
// In OperationModule AppServiceProvider::boot()
UltraMessage::configUsing(fn() => [
    'instance_id' => Setting::get('ultramsg_instance_id'),
    'token'       => Setting::get('ultramsg_token'),
    'enabled'     => Setting::get('ultramsg_enabled', true),
]);
```

---

## 4. The Client (`UltraMessageClient`)

Wraps all UltraMSG HTTP calls via Laravel's `Http` facade. Base URL: `https://api.ultramsg.com/{instance_id}/messages/`.

### Outbound methods

```php
sendText(string $to, string $message, ?string $replyId = null): array
sendImage(string $to, string $imageUrl, string $caption = ''): array
sendDocument(string $to, string $fileUrl, string $filename, string $caption = ''): array
sendAudio(string $to, string $audioUrl): array
sendVoice(string $to, string $audioUrl): array
sendVideo(string $to, string $videoUrl, string $caption = ''): array
sendSticker(string $to, string $stickerUrl): array
sendContact(string $to, string $contactId): array
sendLocation(string $to, float $lat, float $lng, string $address = ''): array
sendReaction(string $to, string $messageId, string $emoji): array
deleteMessage(string $messageId): array
```

### Instance / account info

```php
getInstanceStatus(): array
getChats(): array
getContacts(): array
getGroups(): array
```

### Error handling

All methods throw `UltraMessageException` on HTTP failure (non-2xx) or when the UltraMSG response contains an error field. Callers catch one exception type.

If `enabled` is `false` in config, all send methods return early silently (no exception, no HTTP call).

---

## 5. Message DTO (`UltraMessageMessage`)

A fluent DTO used inside Laravel Notifications:

```php
UltraMessageMessage::text('Order confirmed.')
UltraMessageMessage::image($url, 'Caption')
UltraMessageMessage::document($url, 'invoice.pdf', 'Your invoice')
UltraMessageMessage::audio($url)
UltraMessageMessage::video($url, 'Caption')
UltraMessageMessage::location($lat, $lng, 'Address')
UltraMessageMessage::contact($contactId)
```

Each static constructor sets the `type` and relevant properties. The `->to(string $number)` method sets the recipient (overrides the notifiable's route).

---

## 6. Notification Channel (`UltraMessageChannel`)

Implements `Illuminate\Notifications\Channels\Channel`. When a Notification defines `toUltraMessage()`, this channel:

1. Resolves the recipient from `$notifiable->routeNotificationFor('ultra_message')` or from `$message->to`
2. Calls the correct `$client->send*()` method based on `$message->type`
3. Supports `ShouldQueue` — the notification queues normally via Laravel's queue system

### Usage in OperationModule

```php
class PurchaseOrderConfirmed extends Notification implements ShouldQueue
{
    public function via($notifiable): array
    {
        return [UltraMessageChannel::class];
    }

    public function toUltraMessage($notifiable): UltraMessageMessage
    {
        return UltraMessageMessage::text("PO #{$this->order->number} confirmed.")
            ->to($notifiable->whatsapp_number);
    }
}
```

---

## 7. Facade (`UltraMessage`)

Maps to `UltraMessageClient` for one-liner sends outside of the Notification system:

```php
use PromoSeven\UltraMessage\Facades\UltraMessage;

UltraMessage::sendText('+971501234567', 'Your invoice is ready.');
UltraMessage::sendDocument('+971501234567', $pdfUrl, 'invoice.pdf', 'Invoice #123');
```

Also exposes:
```php
UltraMessage::configUsing(callable $resolver);  // dynamic config
UltraMessage::fake();                           // test mode
UltraMessage::assertSent(callable $callback);   // test assertion
```

---

## 8. Webhook Handling

The service provider registers `POST /{webhook_path}` (default: `ultra-message/webhook`) outside the `auth` middleware group, excluded from CSRF via `VerifyCsrfToken`.

On each request:
1. If `webhook_secret` is set — verify HMAC-SHA256 signature from the `X-Hub-Signature-256` header; return `403` on mismatch
2. Fire `UltraMessageWebhookReceived` event with the full raw payload array
3. Return `200 OK`

**Consuming in OperationModule:**
```php
// In EventServiceProvider
protected $listen = [
    UltraMessageWebhookReceived::class => [
        HandleIncomingWhatsApp::class,
    ],
];
```

---

## 9. Testing Support

```php
UltraMessage::fake();
// ... trigger code that sends ...
UltraMessage::assertSent(fn($msg) => $msg->to === '+971501234567' && $msg->type === 'text');
UltraMessage::assertNotSent();
UltraMessage::assertSentCount(3);
```

In fake mode, no HTTP calls are made. All sends are recorded in memory for assertion.

---

## 10. Settings UI in OperationModule

### Database

A new `settings` table: `id, key (unique), value, created_at, updated_at`.
A `Setting` model with static helpers: `Setting::get($key, $default)` and `Setting::set($key, $value)`.

### Route & Controller

```
GET  /settings/integrations         → SettingsController@integrations
POST /settings/integrations/whatsapp → SettingsController@updateWhatsapp
```

Protected by `auth`, `verified`, and `role:Admin`.

### View

A new **Settings** entry in the sidebar (Admin only) leading to an Integrations page with a WhatsApp section:

| Field | Input type |
|---|---|
| Enable WhatsApp | Toggle switch |
| Instance ID | Text input |
| API Token | Password input (masked, show/hide toggle) |
| Webhook Secret | Password input (masked, show/hide toggle) |
| Webhook URL | Read-only display (auto-generated from `webhook_path`) |
| Connection status | Badge — shows live status via `getInstanceStatus()` |

On save → toast success/error. "Test Connection" button calls `getInstanceStatus()` and shows result inline.

---

## 11. Integration Points in OperationModule

Once the package and settings UI are in place, notifications can be wired to these events:

| Trigger | Message |
|---|---|
| PO confirmed | Supplier: "Your PO #{number} has been confirmed." |
| GRN confirmed | Store: "GRN #{number} received and confirmed." |
| Sales order confirmed | Customer: "Your order #{number} is confirmed." |
| Invoice created | Customer: "Invoice #{number} is ready. Amount: {total}" |
| Delivery dispatched | Customer: "Your delivery is on the way." |
| Low stock alert | Store Manager: "Item {name} is below reorder level." |
| Production order completed | Production Manager: "PO #{number} completed." |

Each notification is a separate `Notification` class using `UltraMessageChannel`. This wiring is done in OperationModule, not in the package.

---

## 12. Implementation Phases

1. **Phase 1 — Package** (`ultra-message` repo): ServiceProvider, Client, Channel, Message DTO, Facade, Webhook handler, Fake/test support
2. **Phase 2 — OperationModule integration**: `settings` table + `Setting` model, Settings UI (sidebar + form), dynamic config boot, CSRF exclusion for webhook route
3. **Phase 3 — Notifications**: Wire individual notification classes for each ERP event listed above
