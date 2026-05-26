# Multi Mail Accounts — Design Spec

**Date:** 2026-05-26
**Status:** Approved

---

## Overview

Replace the single-Azure-account Email tab with a full multi-account mail management system. Admins can add any number of named mail accounts (Microsoft 365 or SMTP), enable/disable them individually, and reference them in code by name via `Mail::mailer('account-name')`.

---

## Goals

- Support unlimited named mail accounts of two types: **Microsoft 365 (Azure AD)** and **SMTP**
- Each account has a slug name used in code (`Mail::mailer('invoices')`)
- Enable/disable toggle per account (used for vacation substitution: disable A, enable B)
- Add / Edit / Delete accounts via AJAX modal on the Settings → Integrations → Email tab
- Test Connection action per account
- Dynamic mailer registration: all enabled accounts available as named Laravel mailers
- Abandon the old single `azure_mail_*` Setting keys; admin re-enters via new UI

---

## Data Model

### Table: `mail_accounts`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `name` | varchar(100) unique | slug, lowercase + hyphens, used as mailer name |
| `label` | varchar(150) | human-readable display name |
| `type` | enum(`azure`, `smtp`) | |
| `from_address` | varchar(255) | sender email address |
| `from_name` | varchar(150) nullable | sender display name |
| `config` | JSON | type-specific credentials (encrypted at rest) |
| `enabled` | boolean default true | |
| `timestamps` | | |

**Azure config keys:** `tenant_id`, `client_id`, `client_secret`

**SMTP config keys:** `host`, `port` (int), `encryption` (enum: `tls`/`ssl`/`none`), `username`, `password`

### Model: `App\Models\MailAccount`

- Cast `config` as encrypted JSON (`'config' => 'encrypted:array'`)
- Method `buildTransport(): \Symfony\Component\Mailer\Transport\TransportInterface`
  - `azure` → `new AzureTransport(new GraphClient(new TokenManager([...config + from_address]), [...]), [...])`
  - `smtp` → `new EsmtpTransport(host, port, encryption === 'ssl')` with username/password

---

## Architecture

### Files changed

| File | Change |
|------|--------|
| `database/migrations/…_create_mail_accounts_table.php` | New migration |
| `app/Models/MailAccount.php` | New model with `buildTransport()` |
| `app/Providers/AppServiceProvider.php` | Register dynamic mailers in `boot()` |
| `app/Http/Controllers/MailAccountController.php` | New controller: index, store, update, destroy, testConnection |
| `routes/web.php` | Add 5 new routes inside `role:Admin` group |
| `app/Http/Controllers/SettingsController.php` | Remove Azure methods + `$azureSettings` from `integrations()` |
| `resources/views/settings/integrations.blade.php` | Replace Email tab content with account list + JS modal |

---

## Controller: `MailAccountController`

**`index(): JsonResponse`** — return all accounts ordered by name, without `config` values (for security)

**`store(Request $request): JsonResponse`**
- Validate: `name` (required, unique, regex:/^[a-z0-9\-]+$/), `label` (required, max:150), `type` (required, in:azure,smtp), `from_address` (required, email), `from_name` (nullable), type-specific config fields
- Create `MailAccount`, return `{success: true, account: {...}}`

**`update(Request $request, MailAccount $mailAccount): JsonResponse`**
- Same validation (name unique ignoring self), update, return `{success: true, account: {...}}`

**`destroy(MailAccount $mailAccount): JsonResponse`**
- Delete, return `{success: true}`

**`testConnection(MailAccount $mailAccount): JsonResponse`**
- Azure: instantiate `TokenManager`, call `getToken()`, return `{success: true/false, message}`
- SMTP: open TCP socket to host:port (or attempt SMTP EHLO), return `{success: true/false, message}`

**`toggleEnabled(MailAccount $mailAccount): JsonResponse`**
- Flip `enabled`, return `{success: true, enabled: bool}`

---

## Routes (inside `role:Admin` group)

```php
Route::get('settings/integrations/mail-accounts', [MailAccountController::class, 'index'])->name('settings.mail-accounts.index');
Route::post('settings/integrations/mail-accounts', [MailAccountController::class, 'store'])->name('settings.mail-accounts.store');
Route::put('settings/integrations/mail-accounts/{mailAccount}', [MailAccountController::class, 'update'])->name('settings.mail-accounts.update');
Route::delete('settings/integrations/mail-accounts/{mailAccount}', [MailAccountController::class, 'destroy'])->name('settings.mail-accounts.destroy');
Route::post('settings/integrations/mail-accounts/{mailAccount}/test', [MailAccountController::class, 'testConnection'])->name('settings.mail-accounts.test');
Route::patch('settings/integrations/mail-accounts/{mailAccount}/toggle', [MailAccountController::class, 'toggleEnabled'])->name('settings.mail-accounts.toggle');
```

---

## Dynamic Mailer Registration (AppServiceProvider)

```php
$this->callAfterResolving(MailManager::class, function (MailManager $manager) {
    try {
        foreach (MailAccount::all() as $account) {
            $manager->extend($account->name, fn() => $account->buildTransport());
        }
    } catch (\Exception) {
        // DB not ready (fresh install) — skip silently
    }
});
```

All accounts are registered (not just enabled ones), so `Mail::mailer('name')` always resolves. Whether the account is used is the caller's responsibility.

---

## Email Tab UI

### Account list

- Header: "Mail Accounts" + count + "Add Account" button (dark pill)
- Each row: type icon, account name (bold) + type badge, from address, enable toggle, Edit + Delete buttons
- Empty state: "No mail accounts configured. Click Add Account to get started."

### Add/Edit Modal

Fields:
1. **Account Name** (slug) — text, validated `^[a-z0-9\-]+$`, unique
2. **Label** — text, display name
3. **Type** — dropdown: Microsoft 365 / SMTP
4. **Type-specific section** (shown/hidden based on type):
   - Azure: Tenant ID, Client ID, Client Secret (masked)
   - SMTP: Host, Port (default 587), Encryption (TLS/SSL/None), Username, Password (masked)
5. **From Address** — email
6. **From Name** — text, optional
7. Footer: Test Connection link | Cancel + Save Account buttons

### Test Connection

Links to `testConnection` route for that account. Returns toast + inline status text.

### Delete

Uses `confirmAction()` modal before calling destroy route. On success, removes row from DOM.

---

## AJAX Pattern

All operations use the `api()` helper from CLAUDE.md rule #11. The account list is loaded via `fetch GET` on page load (Email tab visible) and updated in-place after each mutation — no page reloads.

---

## Removed

- `SettingsController::updateAzureMail()`
- `SettingsController::testAzureMailConnection()`
- `SettingsController::sendTestEmail()`
- Routes: `settings.integrations.azure-mail`, `settings.integrations.test-azure-mail`, `settings.integrations.send-test-email`
- `$azureSettings` from `SettingsController::integrations()`
- The single-account Email tab form in `integrations.blade.php`
- The "Send Test Email" accordion from the Email tab (test is now per-account via Test Connection)
