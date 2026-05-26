# Multi Mail Accounts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the single Azure-account Email tab with a full multi-account mail management system supporting Microsoft 365 (Azure AD) and SMTP accounts.

**Architecture:** New `mail_accounts` DB table with encrypted JSON config; `MailAccount` model with `buildTransport()` factory; `MailAccountController` for CRUD + test + toggle; `AppServiceProvider` registers all accounts as named Laravel mailers; Email tab in integrations view rebuilt as account list + AJAX modal.

**Tech Stack:** Laravel 12, Alpine.js v3, `PromoSeven\AzureMailer` (local package), `Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport`, `Setting` model (existing).

---

## File Map

| File | Change |
|------|--------|
| `database/migrations/2026_05_26_000001_create_mail_accounts_table.php` | Create |
| `app/Models/MailAccount.php` | Create |
| `app/Http/Controllers/MailAccountController.php` | Create |
| `app/Providers/AppServiceProvider.php` | Add dynamic mailer registration |
| `routes/web.php` | Add 7 new routes; remove 3 old azure routes |
| `app/Http/Controllers/SettingsController.php` | Remove azure methods + `$azureSettings` |
| `resources/views/settings/integrations.blade.php` | Replace Email tab with account list + modal |

---

### Task 1: Migration and MailAccount model

**Files:**
- Create: `database/migrations/2026_05_26_000001_create_mail_accounts_table.php`
- Create: `app/Models/MailAccount.php`

- [ ] **Step 1: Create the migration**

```php
<?php
// database/migrations/2026_05_26_000001_create_mail_accounts_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('label', 150);
            $table->enum('type', ['azure', 'smtp']);
            $table->string('from_address', 255);
            $table->string('from_name', 150)->nullable();
            $table->text('config');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_accounts');
    }
};
```

- [ ] **Step 2: Run the migration**

```bash
php artisan migrate
```

Expected: `mail_accounts` table created with no errors.

- [ ] **Step 3: Create `app/Models/MailAccount.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use PromoSeven\AzureMailer\Graph\GraphClient;
use PromoSeven\AzureMailer\Graph\TokenManager;
use PromoSeven\AzureMailer\Transport\AzureTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

class MailAccount extends Model
{
    protected $fillable = ['name', 'label', 'type', 'from_address', 'from_name', 'config', 'enabled'];

    protected $casts = [
        'config'  => 'encrypted:array',
        'enabled' => 'boolean',
    ];

    public function buildTransport(): TransportInterface
    {
        if ($this->type === 'azure') {
            $config = array_merge($this->config, [
                'from_address'       => $this->from_address,
                'graph_api_version'  => 'v1.0',
                'timeout'            => 30,
                'save_to_sent_items' => false,
            ]);
            return new AzureTransport(
                new GraphClient(new TokenManager($config), $config),
                $config
            );
        }

        $cfg       = $this->config;
        $tls       = ($cfg['encryption'] ?? 'tls') === 'ssl';
        $transport = new EsmtpTransport($cfg['host'], (int) ($cfg['port'] ?? 587), $tls);
        if (!empty($cfg['username'])) {
            $transport->setUsername($cfg['username']);
            $transport->setPassword($cfg['password'] ?? '');
        }
        return $transport;
    }
}
```

- [ ] **Step 4: Verify model is autoloaded**

```bash
php artisan tinker --execute="echo App\Models\MailAccount::count();"
```

Expected: prints `0` with no errors.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_05_26_000001_create_mail_accounts_table.php app/Models/MailAccount.php
git commit -m "feat: add mail_accounts migration and MailAccount model"
```

---

### Task 2: MailAccountController and routes

**Files:**
- Create: `app/Http/Controllers/MailAccountController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create `app/Http/Controllers/MailAccountController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\MailAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PromoSeven\AzureMailer\Graph\TokenManager;

class MailAccountController extends Controller
{
    public function index(): JsonResponse
    {
        $accounts = MailAccount::orderBy('name')->get()->map(fn($a) => $this->accountData($a));
        return response()->json(['accounts' => $accounts]);
    }

    public function show(MailAccount $mailAccount): JsonResponse
    {
        return response()->json([
            'account' => array_merge($this->accountData($mailAccount), ['config' => $mailAccount->config]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data    = $this->validated($request);
        $account = MailAccount::create($data);
        return response()->json(['success' => true, 'account' => $this->accountData($account)], 201);
    }

    public function update(Request $request, MailAccount $mailAccount): JsonResponse
    {
        $data = $this->validated($request, $mailAccount->id);
        $mailAccount->update($data);
        return response()->json(['success' => true, 'account' => $this->accountData($mailAccount->fresh())]);
    }

    public function destroy(MailAccount $mailAccount): JsonResponse
    {
        $mailAccount->delete();
        return response()->json(['success' => true]);
    }

    public function testConnection(MailAccount $mailAccount): JsonResponse
    {
        try {
            if ($mailAccount->type === 'azure') {
                $config = array_merge($mailAccount->config, ['from_address' => $mailAccount->from_address]);
                (new TokenManager($config))->getToken();
            } else {
                $cfg    = $mailAccount->config;
                $host   = $cfg['host'] ?? '';
                $port   = (int) ($cfg['port'] ?? 587);
                $socket = @fsockopen($host, $port, $errno, $errstr, 5);
                if (! $socket) {
                    throw new \RuntimeException("Cannot connect to {$host}:{$port} — {$errstr}");
                }
                fclose($socket);
            }
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function toggleEnabled(MailAccount $mailAccount): JsonResponse
    {
        $mailAccount->update(['enabled' => ! $mailAccount->enabled]);
        return response()->json(['success' => true, 'enabled' => $mailAccount->fresh()->enabled]);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $nameUnique = 'unique:mail_accounts,name' . ($ignoreId ? ",{$ignoreId}" : '');
        $rules = [
            'name'         => ['required', 'string', 'max:100', 'regex:/^[a-z0-9\-]+$/', $nameUnique],
            'label'        => ['required', 'string', 'max:150'],
            'type'         => ['required', 'in:azure,smtp'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name'    => ['nullable', 'string', 'max:150'],
            'enabled'      => ['boolean'],
        ];

        if ($request->input('type') === 'azure') {
            $rules['config.tenant_id']     = ['required', 'string', 'max:100'];
            $rules['config.client_id']     = ['required', 'string', 'max:100'];
            $rules['config.client_secret'] = ['required', 'string', 'max:500'];
        } else {
            $rules['config.host']       = ['required', 'string', 'max:255'];
            $rules['config.port']       = ['required', 'integer', 'min:1', 'max:65535'];
            $rules['config.encryption'] = ['required', 'in:tls,ssl,none'];
            $rules['config.username']   = ['nullable', 'string', 'max:255'];
            $rules['config.password']   = ['nullable', 'string', 'max:500'];
        }

        $v = $request->validate($rules);
        return [
            'name'         => $v['name'],
            'label'        => $v['label'],
            'type'         => $v['type'],
            'from_address' => $v['from_address'],
            'from_name'    => $v['from_name'] ?? null,
            'config'       => $v['config'],
            'enabled'      => $v['enabled'] ?? true,
        ];
    }

    private function accountData(MailAccount $account): array
    {
        return [
            'id'           => $account->id,
            'name'         => $account->name,
            'label'        => $account->label,
            'type'         => $account->type,
            'from_address' => $account->from_address,
            'from_name'    => $account->from_name,
            'enabled'      => $account->enabled,
        ];
    }
}
```

- [ ] **Step 2: Add the 7 new routes and remove the 3 old azure routes in `routes/web.php`**

Find the block:
```php
Route::post('settings/integrations/azure-mail', [SettingsController::class, 'updateAzureMail'])->name('settings.integrations.azure-mail');
Route::post('settings/integrations/test-azure-mail', [SettingsController::class, 'testAzureMailConnection'])->name('settings.integrations.test-azure-mail');
Route::post('settings/integrations/send-test-email', [SettingsController::class, 'sendTestEmail'])->name('settings.integrations.send-test-email');
```

Replace it with:
```php
Route::get('settings/integrations/mail-accounts', [MailAccountController::class, 'index'])->name('settings.mail-accounts.index');
Route::post('settings/integrations/mail-accounts', [MailAccountController::class, 'store'])->name('settings.mail-accounts.store');
Route::get('settings/integrations/mail-accounts/{mailAccount}', [MailAccountController::class, 'show'])->name('settings.mail-accounts.show');
Route::put('settings/integrations/mail-accounts/{mailAccount}', [MailAccountController::class, 'update'])->name('settings.mail-accounts.update');
Route::delete('settings/integrations/mail-accounts/{mailAccount}', [MailAccountController::class, 'destroy'])->name('settings.mail-accounts.destroy');
Route::post('settings/integrations/mail-accounts/{mailAccount}/test', [MailAccountController::class, 'testConnection'])->name('settings.mail-accounts.test');
Route::patch('settings/integrations/mail-accounts/{mailAccount}/toggle', [MailAccountController::class, 'toggleEnabled'])->name('settings.mail-accounts.toggle');
```

Also add the `MailAccountController` use statement at the top of `routes/web.php` alongside the existing controller use statements:
```php
use App\Http\Controllers\MailAccountController;
```

- [ ] **Step 3: Verify routes**

```bash
php artisan route:list --name=settings.mail-accounts
```

Expected: 7 rows — index, store, show, update, destroy, test, toggle.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/MailAccountController.php routes/web.php
git commit -m "feat: add MailAccountController with CRUD + test + toggle routes"
```

---

### Task 3: AppServiceProvider + SettingsController cleanup

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Http/Controllers/SettingsController.php`

- [ ] **Step 1: Update `app/Providers/AppServiceProvider.php`**

Replace the entire file content with:

```php
<?php

namespace App\Providers;

use App\Models\MailAccount;
use App\Models\Setting;
use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;
use PromoSeven\UltraMessage\Facades\UltraMessage;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
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

        $this->callAfterResolving(MailManager::class, function (MailManager $manager) {
            try {
                foreach (MailAccount::all() as $account) {
                    $manager->extend($account->name, fn () => $account->buildTransport());
                }
            } catch (\Exception) {
                // DB not ready on fresh install — skip silently
            }
        });
    }
}
```

- [ ] **Step 2: Clean up `app/Http/Controllers/SettingsController.php`**

Remove the `use Illuminate\Support\Facades\Mail;` and `use PromoSeven\AzureMailer\Graph\TokenManager;` use statements.

Change `integrations()` to only pass `$whatsappSettings` (remove `$azureSettings`):

```php
public function integrations(): View
{
    $whatsappSettings = [
        'enabled'        => Setting::get('ultramsg_enabled', false),
        'instance_id'    => Setting::get('ultramsg_instance_id', ''),
        'token'          => Setting::get('ultramsg_token', ''),
        'webhook_secret' => Setting::get('ultramsg_webhook_secret', ''),
        'webhook_path'   => Setting::get('ultramsg_webhook_path', 'ultra-message/webhook'),
    ];

    return view('settings.integrations', compact('whatsappSettings'));
}
```

Delete the three azure methods entirely: `updateAzureMail()`, `testAzureMailConnection()`, `sendTestEmail()`.

- [ ] **Step 3: Verify no parse errors**

```bash
php artisan route:list --name=settings.integrations 2>&1 | tail -3
```

Expected: shows 4 rows (GET + whatsapp POST + test-whatsapp POST + send-test-message POST), no errors.

- [ ] **Step 4: Commit**

```bash
git add app/Providers/AppServiceProvider.php app/Http/Controllers/SettingsController.php
git commit -m "feat: register dynamic mailers in AppServiceProvider, remove single-azure methods from SettingsController"
```

---

### Task 4: Rewrite Email tab in integrations.blade.php

**Files:**
- Modify: `resources/views/settings/integrations.blade.php`

Replace the entire `{{-- ===== Email tab ===== --}}` section (and the old Email modal/accordion) with the following.

- [ ] **Step 1: Replace the Email tab panel and add the modal**

The complete replacement for everything from `{{-- ===== Email tab ===== --}}` to `{{-- end Email tab --}}` (inclusive), plus the new modal added just before the closing `</div>` of the outer wrapper, plus the new JS block replacing the old azure JS functions:

**Email tab panel** (replaces old email tab div):

```blade
{{-- ===== Email tab ===== --}}
<div x-show="tab==='email'" style="display:none;" id="email-tab-panel">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <div>
            <div style="font-size:15px;font-weight:600;color:#111827;">Mail Accounts</div>
            <div id="ma-count" style="font-size:12px;color:#6b7280;">Loading…</div>
        </div>
        <button type="button" onclick="maOpen()" class="btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
            <span style="font-size:16px;line-height:1;">+</span> Add Account
        </button>
    </div>

    <div id="ma-list" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;display:none;"></div>

    <div id="ma-empty" style="display:none;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:40px 24px;text-align:center;">
        <div style="font-size:32px;margin-bottom:12px;">✉️</div>
        <div style="font-size:14px;font-weight:600;color:#374151;margin-bottom:4px;">No mail accounts configured</div>
        <div style="font-size:13px;color:#6b7280;">Click <strong>Add Account</strong> to get started.</div>
    </div>

</div>{{-- end Email tab --}}
```

**Modal** (add just before the closing `</div>` of the outer Alpine wrapper, after the email tab div):

```blade
{{-- Mail Accounts Modal --}}
<div id="ma-modal-overlay"
    onclick="if(event.target===this) maClose()"
    style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;padding:20px;background:rgba(15,23,42,0.55);backdrop-filter:blur(3px);">
    <div style="width:100%;max-width:540px;max-height:88vh;overflow-y:auto;background:#fff;border-radius:16px;box-shadow:0 25px 60px -10px rgba(0,0,0,0.3);">

        {{-- Header --}}
        <div style="padding:20px 24px 16px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
            <h3 id="ma-modal-title" style="font-size:16px;font-weight:700;color:#111827;margin:0;">Add Mail Account</h3>
            <button type="button" onclick="maClose()" style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:20px;line-height:1;padding:0 4px;">&times;</button>
        </div>

        {{-- Body --}}
        <div style="padding:24px;">

            {{-- Account Name --}}
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">
                    Account Name <span style="color:#9ca3af;font-weight:400;">(used in code)</span>
                </label>
                <input type="text" id="ma-form-name" placeholder="e.g. customer-support" class="form-input">
                <div style="font-size:11px;color:#9ca3af;margin-top:3px;">Lowercase letters, numbers and hyphens only.</div>
            </div>

            {{-- Label --}}
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Label</label>
                <input type="text" id="ma-form-label" placeholder="e.g. Customer Support" class="form-input">
            </div>

            {{-- Type --}}
            <div style="margin-bottom:20px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Type</label>
                <select id="ma-form-type" class="form-input" onchange="maTypeChange()">
                    <option value="smtp">📧 SMTP</option>
                    <option value="azure">✉️ Microsoft 365 (Azure AD)</option>
                </select>
            </div>

            {{-- Azure section --}}
            <div id="ma-azure-section" style="display:none;border-top:1px solid #f3f4f6;padding-top:16px;margin-bottom:16px;">
                <div style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px;">Azure AD Credentials</div>
                <div style="margin-bottom:12px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Tenant ID</label>
                    <input type="text" id="ma-form-azure-tenant" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" class="form-input">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Client ID</label>
                    <input type="text" id="ma-form-azure-client" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" class="form-input">
                </div>
                <div style="margin-bottom:0;" x-data="{ showAzureSecret: false }">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Client Secret</label>
                    <div style="position:relative;">
                        <input :type="showAzureSecret ? 'text' : 'password'" id="ma-form-azure-secret" placeholder="Your Azure AD client secret" class="form-input" style="padding-right:40px;">
                        <button type="button" @click="showAzureSecret=!showAzureSecret" style="position:absolute;inset-y:0;right:0;padding:0 10px;background:none;border:none;cursor:pointer;color:#9ca3af;" onmouseover="this.style.color='#4b5563'" onmouseout="this.style.color='#9ca3af'">
                            <svg x-show="!showAzureSecret" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showAzureSecret" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- SMTP section --}}
            <div id="ma-smtp-section" style="border-top:1px solid #f3f4f6;padding-top:16px;margin-bottom:16px;">
                <div style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px;">SMTP Server</div>
                <div style="display:flex;gap:10px;margin-bottom:12px;">
                    <div style="flex:1;">
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Host</label>
                        <input type="text" id="ma-form-smtp-host" placeholder="smtp.gmail.com" class="form-input">
                    </div>
                    <div style="width:80px;">
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Port</label>
                        <input type="number" id="ma-form-smtp-port" value="587" class="form-input">
                    </div>
                    <div style="width:100px;">
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Encryption</label>
                        <select id="ma-form-smtp-enc" class="form-input">
                            <option value="tls">TLS</option>
                            <option value="ssl">SSL</option>
                            <option value="none">None</option>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Username</label>
                    <input type="text" id="ma-form-smtp-username" placeholder="user@example.com" class="form-input">
                </div>
                <div style="margin-bottom:0;" x-data="{ showSmtpPass: false }">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Password</label>
                    <div style="position:relative;">
                        <input :type="showSmtpPass ? 'text' : 'password'" id="ma-form-smtp-password" placeholder="SMTP password or app password" class="form-input" style="padding-right:40px;">
                        <button type="button" @click="showSmtpPass=!showSmtpPass" style="position:absolute;inset-y:0;right:0;padding:0 10px;background:none;border:none;cursor:pointer;color:#9ca3af;" onmouseover="this.style.color='#4b5563'" onmouseout="this.style.color='#9ca3af'">
                            <svg x-show="!showSmtpPass" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showSmtpPass" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Sender --}}
            <div style="border-top:1px solid #f3f4f6;padding-top:16px;margin-bottom:16px;">
                <div style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px;">Sender</div>
                <div style="margin-bottom:12px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">From Address</label>
                    <input type="text" id="ma-form-from-address" placeholder="noreply@yourdomain.com" class="form-input">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">From Name <span style="color:#9ca3af;font-weight:400;">(optional)</span></label>
                    <input type="text" id="ma-form-from-name" placeholder="SteelERP" class="form-input">
                </div>
            </div>

        </div>

        {{-- Footer --}}
        <div style="padding:16px 24px;border-top:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:8px;">
                <button type="button" id="ma-test-btn" onclick="maTest()"
                    style="font-size:13px;color:#2563eb;background:none;border:none;cursor:pointer;text-decoration:underline;padding:0;"
                    onmouseover="this.style.color='#1d4ed8'" onmouseout="this.style.color='#2563eb'">
                    🔗 Test Connection
                </button>
                <span id="ma-test-status" style="font-size:12px;display:none;"></span>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="button" onclick="maClose()"
                    style="padding:8px 16px;background:#f9fafb;color:#374151;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;">
                    Cancel
                </button>
                <button type="button" id="ma-save-btn" onclick="maSave()" class="btn-primary">
                    Save Account
                </button>
            </div>
        </div>

    </div>
</div>
```

- [ ] **Step 2: Update the Email tab button to also load accounts**

In the pill tab row, change the Email tab button's `@click` from:
```blade
<button type="button" @click="tab='email'"
```
to:
```blade
<button type="button" @click="tab='email'; loadMailAccounts()"
```

- [ ] **Step 3: Replace all Azure JS functions with the new mail accounts JS**

In the `<script>` block at the bottom, remove the three functions `saveAzureMail()`, `testAzureConnection()`, and `sendTestEmail()`, and add the following in their place:

```javascript
var MA_BASE = '{{ url("settings/integrations/mail-accounts") }}';
var _maEditId = null;
var _maAccounts = [];

function loadMailAccounts() {
    fetch(MA_BASE, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            _maAccounts = data.accounts;
            renderMailAccounts();
        });
}

function renderMailAccounts() {
    var list  = document.getElementById('ma-list');
    var empty = document.getElementById('ma-empty');
    var count = document.getElementById('ma-count');
    count.textContent = _maAccounts.length + ' account' + (_maAccounts.length !== 1 ? 's' : '') + ' configured';
    if (_maAccounts.length === 0) {
        list.style.display  = 'none';
        empty.style.display = '';
        return;
    }
    list.style.display  = '';
    empty.style.display = 'none';
    list.innerHTML = _maAccounts.map(renderAccountRow).join('');
}

function renderAccountRow(a) {
    var isAzure   = a.type === 'azure';
    var typeLabel = isAzure ? 'Microsoft 365' : 'SMTP';
    var typeBg    = isAzure ? '#eff6ff' : '#f0fdf4';
    var typeColor = isAzure ? '#2563eb' : '#16a34a';
    var typeIcon  = isAzure ? '✉️' : '📧';
    var trackBg   = a.enabled ? '#22c55e' : '#d1d5db';
    var thumbLeft = a.enabled ? '22px' : '2px';
    return '<div style="padding:16px 20px;display:flex;align-items:center;gap:14px;border-bottom:1px solid #f3f4f6;" data-id="' + a.id + '">' +
        '<div style="width:36px;height:36px;background:' + typeBg + ';border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:17px;">' + typeIcon + '</div>' +
        '<div style="flex:1;min-width:0;">' +
            '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:2px;">' +
                '<span style="font-size:14px;font-weight:600;color:#111827;">' + escHtml(a.label) + '</span>' +
                '<code style="font-size:11px;color:#6b7280;background:#f3f4f6;padding:1px 6px;border-radius:4px;">' + escHtml(a.name) + '</code>' +
                '<span style="font-size:11px;font-weight:600;color:' + typeColor + ';background:' + typeBg + ';padding:2px 8px;border-radius:999px;">' + typeLabel + '</span>' +
            '</div>' +
            '<div style="font-size:12px;color:#6b7280;">' + escHtml(a.from_address) + '</div>' +
        '</div>' +
        '<div onclick="maToggle(' + a.id + ')" id="ma-toggle-' + a.id + '" style="width:44px;height:24px;border-radius:12px;background:' + trackBg + ';position:relative;cursor:pointer;flex-shrink:0;transition:background .2s;">' +
            '<div id="ma-thumb-' + a.id + '" style="position:absolute;top:2px;left:' + thumbLeft + ';width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:left .2s;"></div>' +
        '</div>' +
        '<div style="display:flex;gap:8px;flex-shrink:0;">' +
            '<button onclick="maOpen(' + a.id + ')" style="font-size:12px;color:#6b7280;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:5px 10px;cursor:pointer;">Edit</button>' +
            '<button onclick="maDelete(' + a.id + ',\'' + escJs(a.label) + '\')" style="font-size:12px;color:#dc2626;background:#fff5f5;border:1px solid #fecaca;border-radius:6px;padding:5px 10px;cursor:pointer;">Delete</button>' +
        '</div>' +
    '</div>';
}

function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escJs(s)   { return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'"); }

function maTypeChange() {
    var type = document.getElementById('ma-form-type').value;
    document.getElementById('ma-azure-section').style.display = type === 'azure' ? 'block' : 'none';
    document.getElementById('ma-smtp-section').style.display  = type === 'smtp'  ? 'block' : 'none';
}

function maOpen(id) {
    _maEditId = id || null;
    document.getElementById('ma-modal-title').textContent = id ? 'Edit Mail Account' : 'Add Mail Account';
    document.getElementById('ma-form-name').value         = '';
    document.getElementById('ma-form-name').disabled      = !!id;
    document.getElementById('ma-form-label').value        = '';
    document.getElementById('ma-form-type').value         = 'smtp';
    document.getElementById('ma-form-from-address').value = '';
    document.getElementById('ma-form-from-name').value    = '';
    ['ma-form-azure-tenant','ma-form-azure-client','ma-form-azure-secret',
     'ma-form-smtp-host','ma-form-smtp-username','ma-form-smtp-password'].forEach(function(id) {
        document.getElementById(id).value = '';
    });
    document.getElementById('ma-form-smtp-port').value = '587';
    document.getElementById('ma-form-smtp-enc').value  = 'tls';
    document.getElementById('ma-test-status').style.display = 'none';
    maTypeChange();

    if (id) {
        fetch(MA_BASE + '/' + id, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var a = data.account, cfg = a.config || {};
                document.getElementById('ma-form-name').value         = a.name;
                document.getElementById('ma-form-label').value        = a.label;
                document.getElementById('ma-form-type').value         = a.type;
                document.getElementById('ma-form-from-address').value = a.from_address;
                document.getElementById('ma-form-from-name').value    = a.from_name || '';
                if (a.type === 'azure') {
                    document.getElementById('ma-form-azure-tenant').value = cfg.tenant_id     || '';
                    document.getElementById('ma-form-azure-client').value = cfg.client_id     || '';
                    document.getElementById('ma-form-azure-secret').value = cfg.client_secret || '';
                } else {
                    document.getElementById('ma-form-smtp-host').value     = cfg.host       || '';
                    document.getElementById('ma-form-smtp-port').value     = cfg.port       || 587;
                    document.getElementById('ma-form-smtp-enc').value      = cfg.encryption || 'tls';
                    document.getElementById('ma-form-smtp-username').value = cfg.username   || '';
                    document.getElementById('ma-form-smtp-password').value = cfg.password   || '';
                }
                maTypeChange();
            });
    }
    document.getElementById('ma-modal-overlay').style.display = 'flex';
}

function maClose() {
    document.getElementById('ma-modal-overlay').style.display = 'none';
}

function maSave() {
    var type = document.getElementById('ma-form-type').value;
    var config = type === 'azure' ? {
        tenant_id:     document.getElementById('ma-form-azure-tenant').value.trim(),
        client_id:     document.getElementById('ma-form-azure-client').value.trim(),
        client_secret: document.getElementById('ma-form-azure-secret').value.trim(),
    } : {
        host:       document.getElementById('ma-form-smtp-host').value.trim(),
        port:       parseInt(document.getElementById('ma-form-smtp-port').value) || 587,
        encryption: document.getElementById('ma-form-smtp-enc').value,
        username:   document.getElementById('ma-form-smtp-username').value.trim(),
        password:   document.getElementById('ma-form-smtp-password').value,
    };

    var data = {
        name:         document.getElementById('ma-form-name').value.trim(),
        label:        document.getElementById('ma-form-label').value.trim(),
        type:         type,
        from_address: document.getElementById('ma-form-from-address').value.trim(),
        from_name:    document.getElementById('ma-form-from-name').value.trim() || null,
        config:       config,
    };

    var btn    = document.getElementById('ma-save-btn');
    var url    = _maEditId ? MA_BASE + '/' + _maEditId : MA_BASE;
    var method = _maEditId ? 'PUT' : 'POST';
    btn.disabled = true; btn.style.opacity = '.6';

    fetch(url, {
        method: method,
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(function(r) {
        return r.json().then(function(body) { if (!r.ok) return Promise.reject(body); return body; });
    }).then(function() {
        maClose();
        showToast(_maEditId ? 'Account updated.' : 'Account added.', 'success');
        loadMailAccounts();
    }).catch(function(err) {
        var msg = err.errors ? Object.values(err.errors)[0][0] : (err.message || 'Error saving account.');
        showToast(msg, 'error');
    }).finally(function() { btn.disabled = false; btn.style.opacity = '1'; });
}

function maDelete(id, label) {
    confirmAction('Delete Mail Account', 'Delete "' + label + '"? This cannot be undone.', function() {
        fetch(MA_BASE + '/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        }).then(function(r) { return r.json(); }).then(function(res) {
            if (res.success) { showToast('Account deleted.', 'success'); loadMailAccounts(); }
        });
    });
}

function maToggle(id) {
    fetch(MA_BASE + '/' + id + '/toggle', {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
    }).then(function(r) { return r.json(); }).then(function(res) {
        if (!res.success) return;
        var track = document.getElementById('ma-toggle-' + id);
        var thumb = document.getElementById('ma-thumb-' + id);
        if (track && thumb) {
            track.style.background = res.enabled ? '#22c55e' : '#d1d5db';
            thumb.style.left       = res.enabled ? '22px'   : '2px';
        }
        var acc = _maAccounts.find(function(a) { return a.id === id; });
        if (acc) acc.enabled = res.enabled;
    });
}

function maTest() {
    if (!_maEditId) { showToast('Save the account first, then test it.', 'warn'); return; }
    var statusEl = document.getElementById('ma-test-status');
    statusEl.textContent = 'Testing…'; statusEl.style.display = ''; statusEl.style.color = '#6b7280';
    fetch(MA_BASE + '/' + _maEditId + '/test', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) {
            statusEl.textContent = 'Connected ✓'; statusEl.style.color = '#16a34a';
            showToast('Connection successful.', 'success');
        } else {
            statusEl.textContent = 'Failed.'; statusEl.style.color = '#dc2626';
            showToast(data.message || 'Connection failed.', 'error');
        }
    }).catch(function() { statusEl.textContent = 'Request failed.'; statusEl.style.color = '#dc2626'; });
}
```

- [ ] **Step 4: Verify page loads**

Visit `http://localhost:8000/settings/integrations` as Admin. 
- WhatsApp tab loads normally
- Clicking Email tab shows "Mail Accounts" header + "Add Account" button + empty state
- Clicking "Add Account" opens the modal with SMTP fields visible by default
- Switching type to "Microsoft 365" shows Azure fields, hides SMTP fields

- [ ] **Step 5: Verify Add Account (SMTP)**

In the modal: fill in name `test-smtp`, label `Test SMTP`, type SMTP, host `smtp.gmail.com`, port `587`, encryption `TLS`, username `test@test.com`, password `secret`, from address `test@test.com`. Click Save Account.
Expected: toast "Account added.", modal closes, account row appears in list.

- [ ] **Step 6: Verify Edit and Delete**

Click Edit on the row just created — modal opens with pre-filled values. Change the label to `Test SMTP Updated`, Save. Expected: toast "Account updated.", row updates.

Click Delete — confirmation modal appears. Confirm. Expected: toast "Account deleted.", row disappears.

- [ ] **Step 7: Commit**

```bash
git add resources/views/settings/integrations.blade.php
git commit -m "feat: rewrite Email tab as multi-account list with Add/Edit/Delete modal"
```
