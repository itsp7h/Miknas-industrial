# Admin-Creates-User Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an Admin create a new employee account (name, email, profile) from the existing Settings → Users page, with the new user receiving an email to set their own password — then close public self-registration entirely.

**Architecture:** A new `UserManagementController::store()` creates the `User` record with an unusable random password and assigns profiles via `syncRoles()`, then calls Laravel's built-in `Password::sendResetLink()` — reusing Breeze's existing password-reset token/email/form infrastructure as a first-time setup link, with zero new frontend for the recipient. The admin-facing "New User" modal on the Users page mirrors the existing "Edit Access" modal's markup, JS, and AJAX conventions exactly. Closing registration means deleting the register routes/controller/view/test — nothing else references them once gone (the welcome page's register link is already conditionally rendered behind `@if (Route::has('register'))` and disappears automatically).

**Tech Stack:** Laravel 12, Spatie roles (existing `syncRoles`), Laravel's `Password` facade / `PasswordBroker` (already configured, `password_reset_tokens` table already migrated), Breeze's existing reset-password flow (untouched).

## Global Constraints

- AJAX-only data entry — the new-user endpoint returns JSON, the frontend uses `fetch()`, no `<form>` POST/page reload for submission.
- No native `alert()`/`confirm()`/`prompt()`.
- The new-user modal and its JS must follow the exact conventions already established in `resources/views/settings/users/index.blade.php`: data passed via `data-*` attributes (never inlined into `onclick` string arguments — this codebase had a stored-XSS incident from doing that, now fixed), `fetch()` with `X-CSRF-TOKEN` header read from the page's `<meta name="csrf-token">`, `.then(r => r.json())` with reject-on-`!r.ok`, `showToast(message, 'success'|'error')` on completion.
- Do not touch `forgot-password`/`reset-password` routes or views — only the `register` routes/controller/view/test are removed.
- `User::casts()` already includes `'password' => 'hashed'` — pass a plain-text random string to the `password` field on create; do not call `Hash::make()` yourself (that would double-hash).

---

### Task 1: Admin-creates-user backend

**Files:**
- Modify: `app/Http/Controllers/Settings/UserManagementController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/Settings/UserManagementControllerTest.php`

**Interfaces:**
- Consumes: `User` model (fillable `name`/`email`/`password`, `HasRoles` trait already in use), `config('purchase_access.permissions')` (unused here, already consumed by `index()`).
- Produces: `POST settings/users` (route name `settings.users.store`, inside the existing `role:Admin` middleware group) → `UserManagementController::store()`, accepting JSON `{name, email, roles: string[]}`, returning `201` with `{message, user: {id, name, email, roles, permissions}}` on success, `422` on validation failure (including duplicate email), `403` for non-Admins. This is what Task 2's frontend calls.

- [ ] **Step 1: Write the failing tests**

Add these three test methods to the end of `tests/Feature/Settings/UserManagementControllerTest.php`, just before its closing `}` (and add `use Illuminate\Support\Facades\Notification;` and `use Illuminate\Auth\Notifications\ResetPassword;` to its existing `use` block at the top):

```php
    public function test_non_admin_cannot_create_a_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('settings.users.store'), [
            'name'  => 'New Person',
            'email' => 'new@example.test',
        ])->assertForbidden();
    }

    public function test_admin_can_create_a_user_and_a_password_setup_email_is_sent(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)->postJson(route('settings.users.store'), [
            'name'  => 'New Person',
            'email' => 'new@example.test',
            'roles' => ['Requester'],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'new@example.test']);

        $newUser = User::where('email', 'new@example.test')->first();
        $this->assertTrue($newUser->hasRole('Requester'));

        Notification::assertSentTo($newUser, ResetPassword::class);
    }

    public function test_creating_a_user_with_a_duplicate_email_fails_validation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        User::factory()->create(['email' => 'taken@example.test']);

        $this->actingAs($admin)->postJson(route('settings.users.store'), [
            'name'  => 'New Person',
            'email' => 'taken@example.test',
        ])->assertStatus(422);
    }
```

The file's top should now read:

```php
<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserManagementControllerTest extends TestCase
{
    use RefreshDatabase;
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=UserManagementControllerTest`
Expected: FAIL — `route('settings.users.store')` doesn't exist yet (`RouteNotFoundException`).

- [ ] **Step 3: Add the route**

Edit `routes/web.php` — inside the existing `role:Admin` group, add the new route directly after the `settings.users.index` line:

```php
        Route::get('settings/users', [UserManagementController::class, 'index'])->name('settings.users.index');
        Route::post('settings/users', [UserManagementController::class, 'store'])->name('settings.users.store');
        Route::patch('settings/users/{user}', [UserManagementController::class, 'update'])->name('settings.users.update');
```

(Only the new middle line is added — `index`/`update` are unchanged.)

- [ ] **Step 4: Implement `store()`**

Replace the full content of `app/Http/Controllers/Settings/UserManagementController.php` with:

```php
<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::with(['roles', 'permissions'])->orderBy('name')->get();
        $roles = Role::orderBy('name')->pluck('name');
        $permissions = collect(config('purchase_access.permissions'))
            ->map(fn ($label, $name) => ['name' => $name, 'label' => $label])
            ->values();

        return view('settings.users.index', compact('users', 'roles', 'permissions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'roles'   => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Str::random(40),
        ]);

        $user->syncRoles($validated['roles'] ?? []);

        Password::sendResetLink(['email' => $user->email]);

        return response()->json([
            'message' => $user->name . ' created. A password-setup email has been sent.',
            'user' => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'roles'       => $user->roles->pluck('name'),
                'permissions' => $user->permissions->pluck('name'),
            ],
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'roles'         => ['array'],
            'roles.*'       => ['string', 'exists:roles,name'],
            'permissions'   => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if (
            $request->user()->id === $user->id
            && $user->hasRole('Admin')
            && ! in_array('Admin', $validated['roles'] ?? [], true)
        ) {
            return response()->json(['message' => 'You cannot remove your own Admin role.'], 403);
        }

        $user->syncRoles($validated['roles'] ?? []);
        $user->syncPermissions($validated['permissions'] ?? []);

        return response()->json([
            'message'     => 'Access updated for ' . $user->name . '.',
            'roles'       => $user->roles->pluck('name'),
            'permissions' => $user->permissions->pluck('name'),
        ]);
    }
}
```

(Only `store()` and the two new `use` statements — `Password`, `Str` — are added; `index()` and `update()` are byte-for-byte unchanged from the current file.)

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=UserManagementControllerTest`
Expected: PASS (10 tests — 7 existing + 3 new)

- [ ] **Step 6: Run the full backend suite**

Run: `php artisan test`
Expected: baseline (89 passed, 1 pre-existing unrelated `ExampleTest` failure) + 3 new = 92 passed, same 1 pre-existing failure.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Settings/UserManagementController.php routes/web.php tests/Feature/Settings/UserManagementControllerTest.php
git commit -m "feat: add admin-creates-user endpoint with password-setup email"
```

---

### Task 2: Admin-creates-user frontend

**Files:**
- Modify: `resources/views/settings/users/index.blade.php`

**Interfaces:**
- Consumes: `POST settings/users` (Task 1) accepting `{name, email, roles}`, returning `{message, user}` on success or `{message, errors}` (Laravel's default 422 validation shape) on failure. `$roles` (Collection of role name strings, already passed to this view by `index()`).
- Produces: nothing consumed by later tasks — this is a leaf UI addition.

- [ ] **Step 1: Add the "New User" button**

Edit `resources/views/settings/users/index.blade.php` — replace the header/search row:

```blade
<div style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;">
    <input type="text" id="user-search" placeholder="Search users…"
           style="width:100%;max-width:320px;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;"
           onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#e2e8f0'">
    <span id="search-count" style="font-size:12px;color:#94a3b8;font-weight:500;">{{ $users->count() }}</span>
</div>
```

with:

```blade
<div style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;">
    <input type="text" id="user-search" placeholder="Search users…"
           style="width:100%;max-width:320px;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;"
           onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#e2e8f0'">
    <div style="display:flex;align-items:center;gap:16px;">
        <span id="search-count" style="font-size:12px;color:#94a3b8;font-weight:500;">{{ $users->count() }}</span>
        <button class="btn-primary btn-sm" onclick="openNewUserModal()">+ New User</button>
    </div>
</div>
```

- [ ] **Step 2: Add the "New User" modal**

Add this new modal block immediately after the existing `</div>` that closes the `#access-modal` div (i.e., right before the `<style>` block):

```blade
{{-- ── New user modal ── --}}
<div id="new-user-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:100;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:12px;width:100%;max-width:480px;max-height:85vh;overflow-y:auto;">
        <div style="padding:16px 24px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
            <h2 style="font-size:15px;font-weight:700;color:#0f172a;">New User</h2>
            <button onclick="closeNewUserModal()" style="background:none;border:none;font-size:20px;color:#94a3b8;cursor:pointer;">&times;</button>
        </div>
        <div style="padding:20px 24px;">
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;">Name</label>
                <input type="text" id="new-user-name"
                       style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;">
                <p id="new-user-name-error" class="hidden-by-search" style="color:#dc2626;font-size:12px;margin-top:4px;"></p>
            </div>
            <div style="margin-bottom:18px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;">Email</label>
                <input type="email" id="new-user-email"
                       style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;">
                <p id="new-user-email-error" class="hidden-by-search" style="color:#dc2626;font-size:12px;margin-top:4px;"></p>
            </div>
            <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">Profiles</div>
            <div id="new-user-roles-list" style="display:flex;flex-direction:column;gap:8px;">
                @foreach($roles as $role)
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#374151;">
                    <input type="checkbox" class="new-user-role-checkbox" value="{{ $role }}">
                    {{ $role }}
                </label>
                @endforeach
            </div>
            <p style="font-size:12px;color:#94a3b8;margin-top:14px;">
                The new user will receive an email with a link to set their own password.
            </p>
        </div>
        <div style="padding:16px 24px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;">
            <button class="btn-secondary" onclick="closeNewUserModal()">Cancel</button>
            <button class="btn-primary" onclick="createUser()">Create User</button>
        </div>
    </div>
</div>
```

- [ ] **Step 3: Add the JS**

Add these functions to the existing `<script>` block, right after the closing brace of `closeAccessModal()` and before `function saveAccess()`:

```javascript
function openNewUserModal() {
    document.getElementById('new-user-name').value = '';
    document.getElementById('new-user-email').value = '';
    document.getElementById('new-user-name-error').textContent = '';
    document.getElementById('new-user-email-error').textContent = '';
    document.querySelectorAll('.new-user-role-checkbox').forEach(function(cb) { cb.checked = false; });
    document.getElementById('new-user-modal').style.display = 'flex';
}

function closeNewUserModal() {
    document.getElementById('new-user-modal').style.display = 'none';
}

function createUser() {
    var name = document.getElementById('new-user-name').value;
    var email = document.getElementById('new-user-email').value;
    var roles = Array.prototype.slice.call(document.querySelectorAll('.new-user-role-checkbox:checked')).map(function(cb) { return cb.value; });

    document.getElementById('new-user-name-error').textContent = '';
    document.getElementById('new-user-email-error').textContent = '';

    fetch('/settings/users', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: name, email: email, roles: roles })
    }).then(function(r) {
        return r.json().then(function(body) {
            if (!r.ok) return Promise.reject(body);
            return body;
        });
    }).then(function(body) {
        closeNewUserModal();
        showToast(body.message, 'success');
        setTimeout(function() { window.location.reload(); }, 600);
    }).catch(function(err) {
        if (err.errors) {
            if (err.errors.name) document.getElementById('new-user-name-error').textContent = err.errors.name[0];
            if (err.errors.email) document.getElementById('new-user-email-error').textContent = err.errors.email[0];
        }
        showToast(err.message || 'Failed to create user.', 'error');
    });
}
```

- [ ] **Step 4: Verify**

Run: `php artisan test` — confirm no regressions (this task changes Blade/JS only, no PHP logic covered by existing tests).
Run: `php artisan route:list --path=settings/users` — confirm `settings.users.store` appears alongside `index`/`update`.

- [ ] **Step 5: Commit**

```bash
git add resources/views/settings/users/index.blade.php
git commit -m "feat: add New User modal to the Users page"
```

---

### Task 3: Close public registration

**Files:**
- Modify: `routes/auth.php`
- Delete: `app/Http/Controllers/Auth/RegisteredUserController.php`
- Delete: `resources/views/auth/register.blade.php`
- Delete: `tests/Feature/Auth/RegistrationTest.php`

**Interfaces:**
- Consumes: nothing from Tasks 1-2.
- Produces: nothing — this is a standalone removal. `resources/views/welcome.blade.php`'s existing `@if (Route::has('register'))` guard means its "Register" link disappears automatically once the route is gone — no edit needed there.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Auth/RegistrationClosedTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class RegistrationClosedTest extends TestCase
{
    public function test_registration_page_is_not_reachable(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_registration_submission_is_not_reachable(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=RegistrationClosedTest`
Expected: FAIL — both requests currently succeed (200/302), not 404.

- [ ] **Step 3: Remove the registration routes**

Replace the full content of `routes/auth.php` with:

```php
<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
```

(The only changes: the two `register` routes are removed, and the now-unused `use App\Http\Controllers\Auth\RegisteredUserController;` import is removed. Every other route is untouched — `forgot-password`/`reset-password`/`verify-email`/`password.*`/`logout` are byte-for-byte the same.)

- [ ] **Step 4: Delete the now-orphaned controller, view, and test**

```bash
rm app/Http/Controllers/Auth/RegisteredUserController.php
rm resources/views/auth/register.blade.php
rm tests/Feature/Auth/RegistrationTest.php
```

(`RegistrationTest.php` tested the now-removed `/register` route with `assertStatus(200)`/successful registration — keeping it would fail against the intentionally-removed route, and `RegistrationClosedTest.php` from Step 1 is its replacement.)

- [ ] **Step 5: Run the new test to verify it passes**

Run: `php artisan test --filter=RegistrationClosedTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Run the full backend suite**

Run: `php artisan test`
Expected: baseline 92 (after Task 1) minus the 2 deleted `RegistrationTest` tests, plus the 2 new `RegistrationClosedTest` tests = 92 passed, same 1 pre-existing unrelated `ExampleTest` failure.

- [ ] **Step 7: Manually verify the welcome page**

Run: `php artisan route:list --path=register` — confirm no routes are listed.
Load `/` in a browser (or `curl https://steelerp.p7h.me/ -k | grep -i register`) while logged out — confirm no "Register" link/button appears (the `@if (Route::has('register'))` guard in `resources/views/welcome.blade.php` should now evaluate false automatically, requiring no edit to that file).

- [ ] **Step 8: Commit**

```bash
git add routes/auth.php tests/Feature/Auth/RegistrationClosedTest.php
git rm tests/Feature/Auth/RegistrationTest.php app/Http/Controllers/Auth/RegisteredUserController.php resources/views/auth/register.blade.php
git commit -m "feat: close public registration"
```

## Definition of Done for this plan

- `php artisan test` passes in full (92 passed, 1 pre-existing unrelated failure).
- An Admin can open Settings → Users, click "New User", fill in name/email/profile, and see the new user appear in the list with a success toast — no page reload except the deliberate one after save.
- The new user's email inbox (verify via `Notification::fake()` in tests, or manually via mail logs) receives Laravel's standard password-reset email, and clicking through lets them set a password and log in.
- Visiting `/register` (GET or POST) returns 404. The welcome page's "Register" link is gone.
