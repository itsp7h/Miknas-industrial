# Integrations Page — Tabbed UI Design Spec

**Date:** 2026-05-26
**Status:** Approved

---

## Overview

Redesign the Settings → Integrations page to use pill-style tabs — **WhatsApp** and **Email** — so both integrations are accessible from a single page. The Email tab exposes the Microsoft 365 Azure AD credentials (stored via the `Setting` model) and provides test connection + send test email actions via AJAX.

---

## Goals

- Add pill tabs (WhatsApp | Email) at the top of the integrations page
- Keep the WhatsApp tab visually identical to the current design
- Add a new Email tab for Microsoft 365 / Azure AD Mail configuration
- Convert both tabs' save operations to AJAX (rule #11 — no page reloads on settings pages)
- Use the existing design system: `.card`, `.form-input`, `.form-label`, `.btn-primary`, `showToast()`
- All inline styles used for one-off values (Tailwind JIT rule)

---

## UI Design

### Tab bar

Pill-style tabs rendered above the card. Alpine.js `x-data="{ tab: 'whatsapp' }"` switches visibility.

- **Active tab:** `background:#1e293b; color:#fff; border-radius:999px; padding:7px 18px;`
- **Inactive tab:** `background:#fff; color:#374151; border:1px solid #d1d5db; border-radius:999px; padding:7px 18px;`

### WhatsApp tab (unchanged fields, AJAX save)

Same fields as today: Enable toggle, Instance ID, API Token (masked), Webhook Secret (optional, masked), Webhook Path. Save button → AJAX POST. Test Connection and Send Test Message accordion remain unchanged.

### Email tab (new)

Card header: envelope icon + "Microsoft 365 (Azure Mail)" title + subtitle.

Fields:
| Field | Input type | Settings key | Validation |
|-------|-----------|-------------|------------|
| Enable Email Notifications | Toggle | `azure_mail_enabled` | boolean |
| Tenant ID | text | `azure_mail_tenant_id` | required, max:100 |
| Client ID | text | `azure_mail_client_id` | required, max:100 |
| Client Secret | password (show/hide) | `azure_mail_client_secret` | required, max:500 |
| From Address | text | `azure_mail_from_address` | required, email, max:255 |

Actions:
- **Test Connection** link → AJAX POST → calls `TokenManager::getToken()` to verify Azure AD responds
- **Save Settings** button → AJAX POST → stores all 5 keys via `Setting::set()`

Send Test Email accordion (same expand/collapse pattern as WhatsApp):
- **To** field (email address)
- **Subject** field (pre-filled: "Test Email from SteelERP")
- **Send Email** button → AJAX POST → `Mail::mailer('azure')->raw(...)`

---

## Architecture

### Files changed

| File | Change |
|------|--------|
| `resources/views/settings/integrations.blade.php` | Full rewrite — pill tabs, both tab panels, AJAX JS |
| `app/Http/Controllers/SettingsController.php` | Add 3 new methods; update `integrations()` and `updateWhatsapp()` |
| `routes/web.php` | Add 3 new POST routes for azure-mail |

### Controller changes

**`integrations(): View`** — pass both `$whatsappSettings` and `$azureSettings` to view.

**`updateWhatsapp(Request $request): JsonResponse`** — same validation, save logic unchanged, but return `response()->json(['success' => true])` instead of redirect.

**`updateAzureMail(Request $request): JsonResponse`**
```
Validates: tenant_id (required, max:100), client_id (required, max:100),
           client_secret (required, max:500), from_address (required, email, max:255)
Saves: azure_mail_enabled, azure_mail_tenant_id, azure_mail_client_id,
       azure_mail_client_secret, azure_mail_from_address
Returns: JSON {success: true}
```

**`testAzureMailConnection(): JsonResponse`**
```
Reads azure_mail_* settings from DB
Instantiates TokenManager with those settings
Calls getToken() — success means Azure AD is reachable and credentials are valid
Returns: JSON {success: true} or {success: false, message: $e->getMessage()}
```

**`sendTestEmail(Request $request): JsonResponse`**
```
Validates: to (required, email, max:255), subject (required, max:255)
Uses Mail::mailer('azure')->raw('This is a test email from SteelERP.', fn($m) => $m->to($to)->subject($subject))
Returns: JSON {success: true} or {success: false, message: ...}
```

### New routes

```php
Route::post('settings/integrations/azure-mail', [SettingsController::class, 'updateAzureMail'])->name('settings.integrations.azure-mail');
Route::post('settings/integrations/test-azure-mail', [SettingsController::class, 'testAzureMailConnection'])->name('settings.integrations.test-azure-mail');
Route::post('settings/integrations/send-test-email', [SettingsController::class, 'sendTestEmail'])->name('settings.integrations.send-test-email');
```

---

## AJAX Pattern

Both tabs use the global `api()` helper pattern from CLAUDE.md rule #11:

```javascript
var CSRF = document.querySelector('meta[name="csrf-token"]').content;
function api(url, data) {
    return fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' }
        body: JSON.stringify(data)
    }).then(function(r) {
        return r.json().then(function(body) {
            if (!r.ok) return Promise.reject(body);
            return body;
        });
    });
}
```

On success → `showToast('Settings saved.', 'success')`
On error → `showToast(err.message || 'Error', 'error')`

---

## Error Handling

- Laravel validation failure (422) → `Accept: application/json` header ensures JSON error response → `showToast()` with first validation error message
- Azure AD token fetch failure (`AuthenticationException`) → caught in `testAzureMailConnection`, message returned as JSON
- Mail send failure → caught, message returned as JSON

---

## Non-Goals

- No URL changes — integrations page stays at `/settings/integrations`
- No tab state persisted in URL
- No changes to WhatsApp fields or business logic
