# New User Modal — Optional Manual Password Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an Admin choose, in the existing New User modal on `/settings/users`, between emailing the new user a password-setup link (current behavior, default) or setting the user's password directly at creation (new — no email sent).

**Architecture:** `UserManagementController::store` gains a `mode` input (`'email'` default or `'password'`) that branches its existing logic: the `'email'` branch is unchanged (random password + `Password::sendResetLink`); the new `'password'` branch validates and uses the submitted password, sets `email_verified_at` immediately, and skips the email. The New User modal in `resources/views/settings/users/index.blade.php` gains a radio toggle that shows/hides a Password + Confirm Password pair and sends the right payload.

**Tech Stack:** Laravel 12 / PHP 8.2, Blade + vanilla JS (no build-step frontend framework), PHPUnit 11 feature tests, `Illuminate\Validation\Rules\Password::defaults()`.

## Global Constraints

- Tailwind JIT only compiles classes present in scanned source at build time — any new dynamic show/hide of the password fields must use inline `style="display:none"` toggled by JS, never a Tailwind utility class added at runtime. (CLAUDE.md gotcha #1)
- No `alert()`/`confirm()`/`prompt()` — this modal already uses `showToast(...)`; keep using it. (CLAUDE.md gotcha #7)
- All success/error messages are toasts via session flash or `showToast()`, never inline banner divs — this endpoint already returns JSON consumed by `showToast()`; keep that pattern. (CLAUDE.md gotcha #9)
- This page is AJAX-only: no `<form>` submission, no page reload after data entry, controller returns `response()->json(...)`. (CLAUDE.md gotcha #11) — already true of this modal; do not regress it.

---

### Task 1: Backend — `mode`-aware `store()`

**Files:**
- Modify: `app/Http/Controllers/Settings/UserManagementController.php:26-55` (the `store` method)
- Test: `tests/Feature/Settings/UserManagementControllerTest.php`

**Interfaces:**
- Consumes: nothing new from other tasks.
- Produces: `POST /settings/users` now accepts an optional `mode` field (`'email'|'password'`, default `'email'`). When `mode === 'password'`, also accepts `password` and `password_confirmation`. Response shape is unchanged (`{message, user: {...}}`, HTTP 201) for both modes; the `message` text differs by mode as specified below. This is what Task 2's frontend code will call.

- [ ] **Step 1: Write the failing tests**

Add these three tests to `tests/Feature/Settings/UserManagementControllerTest.php` (after the existing `test_admin_can_create_a_user_and_a_password_setup_email_is_sent` test):

```php
    public function test_admin_can_create_a_user_with_a_manual_password_and_no_email_is_sent(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)->postJson(route('settings.users.store'), [
            'name'                  => 'New Person',
            'email'                 => 'manual@example.test',
            'roles'                 => ['Requester'],
            'mode'                  => 'password',
            'password'              => 'CorrectHorseBattery9!',
            'password_confirmation' => 'CorrectHorseBattery9!',
        ]);

        $response->assertCreated();

        $newUser = User::where('email', 'manual@example.test')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue(Hash::check('CorrectHorseBattery9!', $newUser->password));
        $this->assertNotNull($newUser->email_verified_at);
        $this->assertTrue($newUser->hasRole('Requester'));

        Notification::assertNothingSent();
    }

    public function test_creating_a_user_with_manual_mode_and_mismatched_passwords_fails_validation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->postJson(route('settings.users.store'), [
            'name'                  => 'New Person',
            'email'                 => 'mismatch@example.test',
            'mode'                  => 'password',
            'password'              => 'CorrectHorseBattery9!',
            'password_confirmation' => 'DifferentPassword9!',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_creating_a_user_with_manual_mode_and_no_password_fails_validation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->postJson(route('settings.users.store'), [
            'name'  => 'New Person',
            'email' => 'nopassword@example.test',
            'mode'  => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }
```

Add the `Hash` import at the top of the file alongside the existing `use` statements:

```php
use Illuminate\Support\Facades\Hash;
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=UserManagementControllerTest`
Expected: the three new tests FAIL (manual-password user not found / `mode` silently ignored / no validation error raised), while all pre-existing tests in the file still PASS.

- [ ] **Step 3: Implement `mode`-aware `store()`**

Replace the `store` method in `app/Http/Controllers/Settings/UserManagementController.php` with:

```php
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'roles'    => ['array'],
            'roles.*'  => ['string', 'exists:roles,name'],
            'mode'     => ['nullable', 'in:email,password'],
            'password' => [Rule::requiredIf($request->input('mode') === 'password'), 'confirmed', Rules\Password::defaults()],
        ]);

        $mode = $validated['mode'] ?? 'email';

        $user = User::create([
            'name'              => $validated['name'],
            'email'             => $validated['email'],
            'password'          => $mode === 'password' ? $validated['password'] : Str::random(40),
            'email_verified_at' => $mode === 'password' ? now() : null,
        ]);

        $user->syncRoles($validated['roles'] ?? []);

        $message = $user->name . ' created.';

        if ($mode === 'email') {
            Password::sendResetLink(['email' => $user->email]);
            $message = $user->name . ' created. A password-setup email has been sent.';
        }

        return response()->json([
            'message' => $message,
            'user' => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'roles'       => $user->roles->pluck('name'),
                'permissions' => $user->permissions->pluck('name'),
            ],
        ], 201);
    }
```

Add the two new imports at the top of the file alongside the existing `use` statements:

```php
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
```

(`Rules\Password::defaults()` is the same convention already used in `app/Http/Controllers/Auth/PasswordController.php` and `NewPasswordController.php`.)

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=UserManagementControllerTest`
Expected: PASS — all tests in the file, including the three new ones and every pre-existing test (`test_admin_can_create_a_user_and_a_password_setup_email_is_sent` must still pass unchanged, confirming the default `'email'` mode is untouched).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Settings/UserManagementController.php tests/Feature/Settings/UserManagementControllerTest.php
git commit -m "feat: let admins set a user's password directly instead of emailing a setup link"
```

---

### Task 2: Frontend — radio toggle + password fields in the New User modal

**Files:**
- Modify: `resources/views/settings/users/index.blade.php:99-136` (modal markup) and `:176-217` (`openNewUserModal`, `closeNewUserModal`, `createUser` JS)

**Interfaces:**
- Consumes: `POST /settings/users` accepting `mode`, `password`, `password_confirmation` as built in Task 1. On validation failure, Laravel returns `422` with `{message, errors: {field: [messages]}}` — same shape the existing `name`/`email` error handling in `createUser()` already parses.
- Produces: nothing consumed by a later task — this is the last task in the plan.

- [ ] **Step 1: Add the radio toggle and password fields to the modal markup**

In `resources/views/settings/users/index.blade.php`, replace the block from the `Profiles` label through the closing helper `<p>` (lines 118-129) — i.e. keep the `Profiles` div and roles list as-is, but replace the trailing helper paragraph — with the following, inserted immediately before the existing `<p style="font-size:12px;color:#94a3b8;margin-top:14px;">` line so the roles list stays first:

```blade
            <div style="margin-top:18px;margin-bottom:14px;">
                <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">Password</div>
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#374151;margin-bottom:8px;">
                    <input type="radio" name="new-user-password-mode" id="new-user-mode-email" value="email" checked onchange="toggleNewUserPasswordFields()">
                    Email setup link
                </label>
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#374151;">
                    <input type="radio" name="new-user-password-mode" id="new-user-mode-password" value="password" onchange="toggleNewUserPasswordFields()">
                    Set password now
                </label>
                <div id="new-user-password-fields" style="display:none;margin-top:12px;">
                    <div style="margin-bottom:14px;">
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;">Password</label>
                        <input type="password" id="new-user-password"
                               style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;">
                        <p id="new-user-password-error" style="color:#dc2626;font-size:12px;margin-top:4px;"></p>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;">Confirm Password</label>
                        <input type="password" id="new-user-password-confirmation"
                               style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;">
                    </div>
                </div>
            </div>
            <p id="new-user-mode-help" style="font-size:12px;color:#94a3b8;margin-top:14px;">
                The new user will receive an email with a link to set their own password.
            </p>
```

The existing helper `<p>` at line 127-129 is replaced by the `id="new-user-mode-help"` version above (same text, now with an id so JS can swap it).

- [ ] **Step 2: Add the toggle function and update `openNewUserModal`/`closeNewUserModal`**

Add this new function right after `closeNewUserModal()` in the `<script>` block:

```javascript
function toggleNewUserPasswordFields() {
    var manual = document.getElementById('new-user-mode-password').checked;
    document.getElementById('new-user-password-fields').style.display = manual ? 'block' : 'none';
    document.getElementById('new-user-mode-help').textContent = manual
        ? 'The password below will be set immediately — no email will be sent.'
        : 'The new user will receive an email with a link to set their own password.';
    if (!manual) {
        document.getElementById('new-user-password').value = '';
        document.getElementById('new-user-password-confirmation').value = '';
        document.getElementById('new-user-password-error').textContent = '';
    }
}
```

Replace the existing `openNewUserModal()` function body to also reset the new fields:

```javascript
function openNewUserModal() {
    document.getElementById('new-user-name').value = '';
    document.getElementById('new-user-email').value = '';
    document.getElementById('new-user-name-error').textContent = '';
    document.getElementById('new-user-email-error').textContent = '';
    document.getElementById('new-user-password').value = '';
    document.getElementById('new-user-password-confirmation').value = '';
    document.getElementById('new-user-password-error').textContent = '';
    document.getElementById('new-user-mode-email').checked = true;
    document.querySelectorAll('.new-user-role-checkbox').forEach(function(cb) { cb.checked = false; });
    toggleNewUserPasswordFields();
    document.getElementById('new-user-modal').style.display = 'flex';
}
```

`closeNewUserModal()` needs no change.

- [ ] **Step 3: Update `createUser()` to send `mode`/`password` and render password errors**

Replace the `createUser()` function with:

```javascript
function createUser() {
    var name = document.getElementById('new-user-name').value;
    var email = document.getElementById('new-user-email').value;
    var roles = Array.prototype.slice.call(document.querySelectorAll('.new-user-role-checkbox:checked')).map(function(cb) { return cb.value; });
    var manual = document.getElementById('new-user-mode-password').checked;

    document.getElementById('new-user-name-error').textContent = '';
    document.getElementById('new-user-email-error').textContent = '';
    document.getElementById('new-user-password-error').textContent = '';

    var payload = { name: name, email: email, roles: roles, mode: manual ? 'password' : 'email' };
    if (manual) {
        payload.password = document.getElementById('new-user-password').value;
        payload.password_confirmation = document.getElementById('new-user-password-confirmation').value;
    }

    fetch('/settings/users', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
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
            if (err.errors.password) document.getElementById('new-user-password-error').textContent = err.errors.password[0];
        }
        showToast(err.message || 'Failed to create user.', 'error');
    });
}
```

- [ ] **Step 4: Manually verify in the browser**

Start the dev server if not already running (`php artisan serve` and `npm run dev`), then:
1. Go to `/settings/users`, click "+ New User".
2. Confirm "Email setup link" is selected by default and the password fields are hidden.
3. Fill Name/Email, submit — confirm the existing email-link toast and behavior are unchanged.
4. Reopen the modal, select "Set password now" — confirm the password fields appear and the helper text changes.
5. Fill Name/Email/Password/Confirm with mismatched passwords, submit — confirm a 422 shows an inline error under Password and a toast.
6. Fix the confirmation to match, submit — confirm success toast reads "{name} created." (no "email has been sent" text), the modal closes, and the page reloads showing the new user.
7. Log in as that new user with the password just set — confirm login succeeds and no "verify your email" wall appears (since `email_verified_at` was set).

- [ ] **Step 5: Commit**

```bash
git add resources/views/settings/users/index.blade.php
git commit -m "feat: add manual-password option to the New User modal"
```
