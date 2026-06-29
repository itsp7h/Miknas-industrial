# Design: promoseven/connect — Merged Package

**Date:** 2026-06-29
**Status:** Approved

---

## Goal

Merge `packages/ultra-message` (`promoseven/ultra-message`) and `packages/azure-mailer` (`promoseven/azure-mailer`) into a single package at `packages/connect` (`promoseven/connect`). Update all host app references to the new namespace.

---

## Package Structure

```
packages/connect/
├── composer.json
├── config/
│   ├── ultra-message.php       (unchanged content)
│   └── azure-mailer.php        (unchanged content)
├── routes/
│   └── webhook.php             (unchanged content)
├── src/
│   ├── ConnectServiceProvider.php          ← new root provider, registers both sub-providers
│   ├── UltraMessage/
│   │   ├── UltraMessageServiceProvider.php
│   │   ├── UltraMessageClient.php
│   │   ├── UltraMessageChannel.php
│   │   ├── UltraMessageMessage.php
│   │   ├── UltraMessageException.php
│   │   ├── UltraMessageFake.php
│   │   ├── Facades/
│   │   │   └── UltraMessage.php
│   │   ├── Events/
│   │   │   └── UltraMessageWebhookReceived.php
│   │   └── Http/Controllers/
│   │       └── WebhookController.php
│   └── AzureMailer/
│       ├── AzureMailerServiceProvider.php
│       ├── Exceptions/
│       │   ├── AuthenticationException.php
│       │   └── GraphApiException.php
│       ├── Graph/
│       │   ├── GraphClient.php
│       │   └── TokenManager.php
│       └── Transport/
│           └── AzureTransport.php
└── tests/
    ├── TestCase.php
    ├── UltraMessage/
    │   ├── UltraMessageChannelTest.php
    │   └── UltraMessageClientTest.php
    └── AzureMailer/
        ├── ExceptionsTest.php
        ├── ServiceProviderTest.php
        ├── Graph/
        │   ├── GraphClientTest.php
        │   └── TokenManagerTest.php
        └── Transport/
            └── AzureTransportTest.php
```

---

## Namespaces

| Old | New |
|-----|-----|
| `PromoSeven\UltraMessage\` | `PromoSeven\Connect\UltraMessage\` |
| `PromoSeven\AzureMailer\` | `PromoSeven\Connect\AzureMailer\` |

All internal cross-references within the package are updated to match.

---

## composer.json (packages/connect)

- `name`: `promoseven/connect`
- `description`: `Laravel integrations — WhatsApp (UltraMSG) and Microsoft 365 mail (Azure AD)`
- PSR-4 autoload: `PromoSeven\\Connect\\` → `src/`
- PSR-4 autoload-dev: `PromoSeven\\Connect\\Tests\\` → `tests/`
- `extra.laravel.providers`: `[ConnectServiceProvider]`
- `extra.laravel.aliases`: `{ UltraMessage: PromoSeven\Connect\UltraMessage\Facades\UltraMessage }`
- Dependencies: union of both old packages (`illuminate/support`, `illuminate/http`, `illuminate/notifications`, `illuminate/routing`, `illuminate/cache`, `illuminate/mail`, `symfony/mailer`)

---

## ConnectServiceProvider

Minimal root provider — delegates everything to the two sub-providers:

```php
public function register(): void
{
    $this->app->register(UltraMessageServiceProvider::class);
    $this->app->register(AzureMailerServiceProvider::class);
}
```

No boot logic of its own. Each sub-provider handles its own config merging, publishing, route loading, and mail transport registration.

---

## Config Files

Kept as two separate files — config key paths are unchanged:
- `config('ultra-message.*')` — no change
- `config('azure-mailer.*')` — no change

Both are published via their respective sub-providers under the same existing tags (`ultra-message-config`, `azure-mailer-config`).

---

## Host App Changes (SteelERP)

### composer.json
- Remove repositories: `./packages/ultra-message`, `./packages/azure-mailer`
- Add repository: `./packages/connect`
- Remove requires: `promoseven/ultra-message`, `promoseven/azure-mailer`
- Add require: `promoseven/connect`

### Namespace find-and-replace (17 files)

**`PromoSeven\UltraMessage\` → `PromoSeven\Connect\UltraMessage\`** (15 use statements across 10 files):
- `app/Providers/AppServiceProvider.php`
- `app/Http/Controllers/SettingsController.php`
- `app/Notifications/Purchase/GoodsReceiptConfirmedNotification.php`
- `app/Notifications/Purchase/PurchaseOrderConfirmedNotification.php`
- `app/Notifications/Production/ProductionOrderCompletedNotification.php`
- `app/Notifications/Sales/DeliveryDispatchedNotification.php`
- `app/Notifications/Sales/InvoiceCreatedNotification.php`
- `app/Notifications/Sales/SalesOrderConfirmedNotification.php`
- `app/Notifications/Inventory/LowStockAlertNotification.php`

**`PromoSeven\AzureMailer\` → `PromoSeven\Connect\AzureMailer\`** (4 use statements across 2 files):
- `app/Models/MailAccount.php`
- `app/Http/Controllers/MailAccountController.php`

---

## Old Packages

`packages/ultra-message/` and `packages/azure-mailer/` are deleted after the new package is verified.

---

## Testing

- Run `composer dump-autoload` in `packages/connect/` and in the host app root
- Run `php artisan config:clear && php artisan cache:clear`
- Verify `php artisan serve` starts without errors
- Spot-check one WhatsApp notification and one mail account send
