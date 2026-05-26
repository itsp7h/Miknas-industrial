# azure-mailer — Design Spec

**Package:** `promoseven/azure-mailer`
**Date:** 2026-05-26
**Status:** Approved

---

## Overview

A Laravel package that provides a custom Symfony Mailer transport backed by the Microsoft Graph API. Enables Laravel applications to send email via Microsoft 365 / Exchange Online using Azure AD Client Credentials — a drop-in replacement for SMTP that requires zero changes to existing `Mail::to()->send()` calls.

---

## Goals

- Send email via `POST /v1.0/users/{from}/sendMail` on the Microsoft Graph API
- Authenticate using Azure AD OAuth2 Client Credentials flow
- Cache access tokens via Laravel Cache to avoid redundant Azure AD requests
- Support HTML/plain-text bodies, CC, BCC, Reply-To, and file attachments
- Integrate as a first-class Laravel mail transport driver (configured in `config/mail.php`)
- Auto-discovered via Laravel package discovery — zero manual registration

## Non-Goals (v1)

- Delegated (user OAuth) authentication
- Certificate-based auth
- Calendar invites or other Graph API mail features
- Standalone facade / direct API access
- Multi-from-address support

---

## Package Structure

```
promoseven/azure-mailer/
├── src/
│   ├── AzureMailerServiceProvider.php
│   ├── Transport/
│   │   └── AzureTransport.php
│   ├── Graph/
│   │   ├── TokenManager.php
│   │   └── GraphClient.php
│   └── Exceptions/
│       ├── AuthenticationException.php
│       └── GraphApiException.php
├── config/
│   └── azure-mailer.php
├── tests/
├── composer.json
└── README.md
```

---

## Architecture

### Layered design — three focused classes

| Class | Responsibility |
|-------|---------------|
| `AzureTransport` | Symfony `AbstractTransport` adapter — extracts data from `Email` object, assembles payload, delegates to `GraphClient` |
| `GraphClient` | HTTP calls to Graph API — calls `TokenManager` for Bearer token, POSTs payload, handles Graph error responses |
| `TokenManager` | OAuth2 Client Credentials token fetch + Laravel Cache — single source of truth for the access token |

Dependencies flow one way: `AzureTransport → GraphClient → TokenManager`. No circular dependencies.

---

## Authentication

**Flow:** OAuth2 Client Credentials

1. `TokenManager::getToken()` checks `Cache::get("azure_mailer_token_{client_id}")`
2. On cache miss: POST to `https://login.microsoftonline.com/{tenant_id}/oauth2/v2.0/token`
   - `grant_type=client_credentials`
   - `scope=https://graph.microsoft.com/.default`
   - `client_id`, `client_secret`
3. Cache the returned `access_token` with TTL = `expires_in - 60` seconds
4. On Azure AD error: throw `AuthenticationException` with error description from response

**401 retry logic in `GraphClient`:**
- On first 401 from Graph API: `Cache::forget(...)`, call `TokenManager::getToken()` to force fresh fetch, retry the request once
- Log a warning via `Log::warning('azure-mailer: 401 received, retrying with fresh token')`
- On second 401: throw `GraphApiException` — do not retry again

**HTTP client:** Laravel `Http` facade (no extra Guzzle dependency).

---

## Graph API Payload

**Endpoint:** `POST https://graph.microsoft.com/v1.0/users/{from_address}/sendMail`

```json
{
    "message": {
        "subject": "string",
        "body": {
            "contentType": "HTML",
            "content": "string"
        },
        "toRecipients":  [{ "emailAddress": { "address": "string", "name": "string" } }],
        "ccRecipients":  [{ "emailAddress": { "address": "string", "name": "string" } }],
        "bccRecipients": [{ "emailAddress": { "address": "string", "name": "string" } }],
        "replyTo":       [{ "emailAddress": { "address": "string", "name": "string" } }],
        "attachments": [
            {
                "@odata.type": "#microsoft.graph.fileAttachment",
                "name": "filename.pdf",
                "contentType": "application/pdf",
                "contentBytes": "<base64-encoded bytes>"
            }
        ]
    },
    "saveToSentItems": false
}
```

**Body content type:** HTML if the `Email` object has an HTML part; falls back to `Text`.

**Attachments:** Each `DataPart` in the Symfony `Email` object is base64-encoded and mapped to a `fileAttachment` entry.

**`saveToSentItems`:** Defaults to `false`. Configurable via published config to avoid cluttering the sending mailbox.

---

## Configuration

### Consumer's `config/mail.php`

```php
'azure' => [
    'transport'     => 'azure',
    'tenant_id'     => env('AZURE_TENANT_ID'),
    'client_id'     => env('AZURE_CLIENT_ID'),
    'client_secret' => env('AZURE_CLIENT_SECRET'),
    'from_address'  => env('AZURE_MAIL_FROM_ADDRESS'),
],
```

### Environment variables

```env
AZURE_TENANT_ID=
AZURE_CLIENT_ID=
AZURE_CLIENT_SECRET=
AZURE_MAIL_FROM_ADDRESS=
```

### Published config (`config/azure-mailer.php`)

Exposes advanced overrides published via `php artisan vendor:publish --tag=azure-mailer-config`:

```php
return [
    'save_to_sent_items' => false,
    'timeout'            => 30,
    'graph_api_version'  => 'v1.0',
];
```

---

## Service Provider

`AzureMailerServiceProvider` does three things:

1. **Extends Laravel's `MailManager`** with the `azure` transport factory:
```php
$this->app->make(MailManager::class)->extend('azure', function (array $config) {
    return new AzureTransport(
        new GraphClient(new TokenManager($config), $config),
        $config
    );
});
```

2. **Registers config publishing** — `vendor:publish --tag=azure-mailer-config`

3. **Auto-discovered** via `extra.laravel.providers` in `composer.json` — no manual registration needed

---

## Error Handling

| Scenario | Behaviour |
|----------|-----------|
| Azure AD token fetch fails | Throw `AuthenticationException` with error description |
| Graph API returns 401 | Invalidate cache, retry once with fresh token, log warning |
| Graph API returns 401 on retry | Throw `GraphApiException` |
| Graph API returns other 4xx/5xx | Throw `GraphApiException` with `error.code` + `error.message` from response body |
| Network timeout | Laravel `Http` facade throws `ConnectionException` — let it propagate |

---

## Dependencies

```json
{
    "require": {
        "php": "^8.2",
        "illuminate/support": "^11.0|^12.0",
        "illuminate/http": "^11.0|^12.0",
        "illuminate/mail": "^11.0|^12.0",
        "symfony/mailer": "^7.0"
    }
}
```

No explicit Guzzle dependency — Laravel's `Http` facade is used throughout.

---

## Azure AD App Registration Requirements

The consuming application must grant the registered Azure AD app the following **application permission** (not delegated):

- `Mail.Send` — allows sending mail as any user in the tenant

This is set in Azure Portal → App Registrations → API Permissions → Microsoft Graph → Application permissions.

---

## Testing Strategy

- `TokenManager` — mock `Http` facade, assert correct POST body, assert caching behaviour, assert cache invalidation on retry
- `GraphClient` — mock `Http` facade, assert Authorization header, assert payload structure, assert 401 retry logic
- `AzureTransport` — use a real Symfony `Email` object, assert `GraphClient::send()` is called with correct payload shape
- Integration test — mock the full HTTP stack end-to-end through `Mail::fake()` + transport swap
