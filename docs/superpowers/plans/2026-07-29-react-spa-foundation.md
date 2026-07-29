# React SPA Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the foundation for the React SPA migration — Sanctum SPA auth, a versioned JSON API, Laravel Reverb live broadcasting, a React build alongside the existing Blade/Alpine stack, a shared UI component library, and one fully working vertical slice (Dashboard) plus one fully working domain-entity slice (Supplier) proving the pattern every later module will copy.

**Architecture:** Laravel stays the single backend. `routes/api.php` (new) exposes JSON endpoints under `/api/v1`, authenticated via Sanctum's SPA cookie mode (same domain as the existing app, `https://steelerp.p7h.me`). Reverb runs as a new WebSocket daemon; Laravel broadcasts domain events, the React app subscribes via Laravel Echo. React is built as a second Vite entry point (`resources/js-app/`), mounted into the existing app at a catch-all `/app/{any?}` route, and coexists with the untouched Blade views for every module not yet migrated.

**Tech Stack:** Laravel 12 / PHP 8.2 (existing), Laravel Sanctum, Laravel Reverb, Laravel Echo + pusher-js protocol client, React 18, React Router 6, Vite (existing, extended), Vitest + React Testing Library, PHPUnit (existing).

## Global Constraints

- SQLite stays the database driver (`database/database.sqlite`) — no driver change in this phase.
- APP_URL is `https://steelerp.p7h.me` — Sanctum stateful domain and Echo host config must match this, not `localhost`.
- Existing Blade routes/views/controllers are never modified or deleted in this plan — this phase only adds new files (`routes/api.php`, `app/Http/Controllers/Api/**`, `resources/js-app/**`) and makes additive config/bootstrap changes.
- All new API responses are JSON (`response()->json(...)`), no Blade views, no session-flash redirects — matches CLAUDE.md rule 11 (AJAX-only data entry), extended to the whole API surface.
- No native `alert()`/`confirm()`/`prompt()` in any new React code — use the shared `ConfirmModal` and `ToastProvider` built in this plan (CLAUDE.md rule 7).
- Search/filter UI must be instant client-side, no `?search=` params, no server round-trip per keystroke (CLAUDE.md rule 6) — the shared `Table` component's search box loads all rows once and filters in-browser.
- Frontend tests use Vitest + React Testing Library (per approved design). No Playwright/Cypress in this phase.

---

### Task 1: Install and configure Sanctum SPA auth + API routing skeleton

**Files:**
- Modify: `composer.json` (add `laravel/sanctum`)
- Create: `config/sanctum.php` (published)
- Modify: `bootstrap/app.php`
- Create: `routes/api.php`
- Create: `app/Http/Controllers/Api/AuthController.php`
- Create: `app/Http/Middleware/EnsureFrontendRequestsAreStateful.php` — NOT created manually, comes from Sanctum's service provider; do not hand-write this file.
- Test: `tests/Feature/Api/AuthControllerTest.php`

**Interfaces:**
- Produces: `POST /api/v1/login` (body: `email`, `password` → 200 `{user: {...}, roles: [...]}` or 422 on invalid credentials), `POST /api/v1/logout` (204), `GET /api/v1/me` (200 `{user, roles}` or 401 if unauthenticated). These three endpoints are what every later API task assumes exists for auth-gated testing.

- [ ] **Step 1: Install Sanctum and publish its config**

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

- [ ] **Step 2: Configure Sanctum stateful domains and session for the app's real domain**

Edit `.env` (add these lines, do not remove existing ones):

```
SANCTUM_STATEFUL_DOMAINS=steelerp.p7h.me
SESSION_DOMAIN=steelerp.p7h.me
```

Edit `config/sanctum.php` — confirm the `stateful` key reads from env with a sensible local-dev fallback:

```php
'stateful' => explode(',', (string) env(
    'SANCTUM_STATEFUL_DOMAINS',
    'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1'
)),
```

- [ ] **Step 3: Wire Sanctum's stateful middleware and the API route file into the bootstrap**

Edit `bootstrap/app.php`:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->statefulApi();

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'ultra-message/webhook',
            'ultra-message/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

`$middleware->statefulApi()` is Sanctum's Laravel 12 helper — it applies `EnsureFrontendRequestsAreStateful` to the `api` middleware group automatically, no manual middleware group array needed.

- [ ] **Step 4: Create the versioned API route file**

Create `routes/api.php`:

```php
<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});
```

- [ ] **Step 5: Write the failing auth controller test**

Create `tests/Feature/Api/AuthControllerTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials_returns_user_and_roles(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);
        $user->assignRole('Admin');

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('roles.0', 'Admin');
    }

    public function test_login_with_invalid_credentials_returns_422(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_me_returns_authenticated_user_and_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Accounts');

        $response = $this->actingAs($user)->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('roles.0', 'Accounts');
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/logout')->assertNoContent();

        $this->getJson('/api/v1/me')->assertStatus(401);
    }
}
```

- [ ] **Step 6: Run the tests to verify they fail**

Run: `php artisan test --filter=AuthControllerTest`
Expected: FAIL — `Target class [App\Http\Controllers\Api\AuthController] does not exist.`

- [ ] **Step 7: Implement the AuthController**

Create `app/Http/Controllers/Api/AuthController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        $request->session()->regenerate();

        return $this->userPayload($request);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return $this->userPayload($request);
    }

    private function userPayload(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'whatsapp_number']),
            'roles' => $user->getRoleNames(),
        ]);
    }
}
```

- [ ] **Step 8: Run the tests to verify they pass**

Run: `php artisan test --filter=AuthControllerTest`
Expected: PASS (5 tests)

- [ ] **Step 9: Commit**

```bash
git add composer.json composer.lock config/sanctum.php bootstrap/app.php routes/api.php app/Http/Controllers/Api/AuthController.php tests/Feature/Api/AuthControllerTest.php .env.example
git commit -m "feat: add Sanctum SPA auth and versioned API skeleton"
```

Note: also add the two new env keys to `.env.example` (not just `.env`) so the pattern is documented for other environments:
```
SANCTUM_STATEFUL_DOMAINS=steelerp.p7h.me
SESSION_DOMAIN=steelerp.p7h.me
```

---

### Task 2: Install and configure Laravel Reverb + a demo broadcast event

**Files:**
- Modify: `composer.json` (add `laravel/reverb`)
- Create/Modify: `config/broadcasting.php`, `config/reverb.php` (published by Reverb installer)
- Modify: `.env`, `.env.example`
- Create: `app/Events/DashboardPinged.php`
- Create: `routes/channels.php`
- Test: `tests/Feature/Api/DashboardPingTest.php`

**Interfaces:**
- Consumes: `AuthController` from Task 1 (uses `actingAs` pattern for authenticated broadcast-auth test).
- Produces: `App\Events\DashboardPinged` (implements `ShouldBroadcastNow`, broadcasts on private channel `App.Models.User.{id}`, payload `{message: string, at: string}`) — this is the pattern Task 6 (Dashboard vertical slice) and every later live-update event copies.

- [ ] **Step 1: Install and initialize Reverb**

```bash
composer require laravel/reverb
php artisan reverb:install
```

This publishes `config/reverb.php`, adds broadcasting env vars to `.env`, and sets `BROADCAST_CONNECTION=reverb` (installer does this automatically — verify it, don't assume).

- [ ] **Step 2: Point Reverb's public env vars at the real app host**

Edit `.env` — confirm/set (installer adds most of these, adjust the host values):

```
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=steelerp
REVERB_APP_KEY=steelerp-key
REVERB_APP_SECRET=steelerp-secret
REVERB_HOST="steelerp.p7h.me"
REVERB_PORT=443
REVERB_SCHEME=https

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Mirror the same keys (with placeholder-safe values, not real secrets) into `.env.example`.

- [ ] **Step 3: Define private channel authorization**

Create `routes/channels.php`:

```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === $id;
});
```

Register it in `bootstrap/app.php` — no change needed for `routes/channels.php` specifically in Laravel 12's new bootstrap style; Reverb's install step wires broadcasting route registration into the `withRouting` call automatically via the `channels` closure. Verify by checking `bootstrap/app.php` after `reverb:install` — if `channels: __DIR__.'/../routes/channels.php'` was not added to `withRouting(...)`, add it manually:

```php
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
```

- [ ] **Step 4: Write the failing test for the demo broadcast event**

Create `tests/Feature/Api/DashboardPingTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Events\DashboardPinged;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DashboardPingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ping_endpoint_broadcasts_dashboard_pinged_event(): void
    {
        Event::fake([DashboardPinged::class]);

        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/dashboard/ping')->assertNoContent();

        Event::assertDispatched(DashboardPinged::class, function (DashboardPinged $event) use ($user) {
            return $event->userId === $user->id;
        });
    }
}
```

- [ ] **Step 5: Run the test to verify it fails**

Run: `php artisan test --filter=DashboardPingTest`
Expected: FAIL — `Class "App\Events\DashboardPinged" not found` / route not defined.

- [ ] **Step 6: Implement the event**

Create `app/Events/DashboardPinged.php`:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class DashboardPinged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $userId,
        public string $message,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.' . $this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'dashboard.pinged';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'at' => now()->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 7: Add the ping route and a minimal controller method**

Add to `routes/api.php` inside the `auth:sanctum` group from Task 1:

```php
        Route::post('dashboard/ping', [\App\Http\Controllers\Api\DashboardController::class, 'ping']);
```

Create `app/Http/Controllers/Api/DashboardController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Events\DashboardPinged;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function ping(Request $request)
    {
        event(new DashboardPinged($request->user()->id, 'Live update check'));

        return response()->noContent();
    }
}
```

- [ ] **Step 8: Run the test to verify it passes**

Run: `php artisan test --filter=DashboardPingTest`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add composer.json composer.lock config/broadcasting.php config/reverb.php bootstrap/app.php routes/channels.php routes/api.php app/Events/DashboardPinged.php app/Http/Controllers/Api/DashboardController.php tests/Feature/Api/DashboardPingTest.php .env.example
git commit -m "feat: install Reverb and add a demo broadcast event"
```

---

### Task 3: Scaffold the React build alongside the existing Vite setup

**Files:**
- Modify: `package.json`
- Modify: `vite.config.js`
- Create: `resources/js-app/main.jsx`
- Create: `resources/js-app/App.jsx`
- Create: `resources/js-app/echo.js`
- Create: `resources/views/app-shell.blade.php`
- Modify: `routes/web.php` (add catch-all mount route)
- Create: `vitest.config.js`
- Create: `resources/js-app/App.test.jsx`

**Interfaces:**
- Consumes: nothing from prior tasks directly (build-time only), but `echo.js`'s config values consume the `VITE_REVERB_*` env vars from Task 2.
- Produces: `resources/js-app/echo.js` exports a singleton `echo` (Laravel Echo instance) — every later component that needs live updates imports `{ echo }` from this exact path. `App.jsx` exports default `App` component that Task 6+ nest routes into via `<Route>` children.

- [ ] **Step 1: Install frontend dependencies**

```bash
npm install react react-dom react-router-dom laravel-echo pusher-js
npm install -D @vitejs/plugin-react vitest @testing-library/react @testing-library/jest-dom jsdom
```

- [ ] **Step 2: Add the React entry to Vite config**

Edit `vite.config.js`:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js-app/main.jsx',
            ],
            refresh: true,
        }),
        react(),
    ],
});
```

- [ ] **Step 3: Create the Echo singleton**

Create `resources/js-app/echo.js`:

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

export const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

- [ ] **Step 4: Write the failing test for the App shell**

Create `resources/js-app/App.test.jsx`:

```jsx
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import App from './App';

describe('App', () => {
    it('renders the SteelERP app shell', () => {
        render(
            <MemoryRouter initialEntries={['/app']}>
                <App />
            </MemoryRouter>
        );

        expect(screen.getByText('SteelERP')).toBeInTheDocument();
    });
});
```

- [ ] **Step 5: Configure Vitest**

Create `vitest.config.js`:

```js
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [react()],
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./resources/js-app/test-setup.js'],
    },
});
```

Create `resources/js-app/test-setup.js`:

```js
import '@testing-library/jest-dom';
```

Add to `package.json` `"scripts"`:

```json
        "test": "vitest run"
```

- [ ] **Step 6: Run the test to verify it fails**

Run: `npm run test`
Expected: FAIL — `Failed to resolve import "./App"` (file doesn't exist yet).

- [ ] **Step 7: Implement the App shell**

Create `resources/js-app/App.jsx`:

```jsx
import { Routes, Route } from 'react-router-dom';

export default function App() {
    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white border-b border-gray-100 px-6 py-4">
                <span className="font-bold text-gray-800">SteelERP</span>
            </header>
            <main className="p-6">
                <Routes>
                    <Route path="/app" element={<div>Welcome to the new SteelERP app.</div>} />
                </Routes>
            </main>
        </div>
    );
}
```

Create `resources/js-app/main.jsx`:

```jsx
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App';

const container = document.getElementById('react-app');

createRoot(container).render(
    <StrictMode>
        <BrowserRouter>
            <App />
        </BrowserRouter>
    </StrictMode>
);
```

- [ ] **Step 8: Run the test to verify it passes**

Run: `npm run test`
Expected: PASS (1 test)

- [ ] **Step 9: Mount the SPA behind a catch-all Blade shell**

Create `resources/views/app-shell.blade.php`:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SteelERP</title>
    @vite(['resources/css/app.css', 'resources/js-app/main.jsx'])
</head>
<body>
    <div id="react-app"></div>
</body>
</html>
```

Add to `routes/web.php`, inside the existing `Route::middleware(['auth', 'verified'])->group(...)` block (do not create a separate middleware group — reuse the existing one so login/verification protection is identical to today's Blade app):

```php
    Route::get('/app/{any?}', fn () => view('app-shell'))
        ->where('any', '.*')
        ->name('app.shell');
```

Place this route registration **after** all existing purchase/inventory/production/sales route groups in the file, so it never shadows a more specific existing route.

- [ ] **Step 10: Commit**

```bash
git add package.json package-lock.json vite.config.js vitest.config.js resources/js-app resources/views/app-shell.blade.php routes/web.php
git commit -m "feat: scaffold React SPA build alongside existing Blade/Alpine app"
```

---

### Task 4: Shared UI library — Button, Toast, Modal, ConfirmModal

**Files:**
- Create: `resources/js-app/components/ui/Button.jsx`
- Create: `resources/js-app/components/ui/Button.test.jsx`
- Create: `resources/js-app/components/ui/Toast.jsx` (exports `ToastProvider`, `useToast`)
- Create: `resources/js-app/components/ui/Toast.test.jsx`
- Create: `resources/js-app/components/ui/Modal.jsx`
- Create: `resources/js-app/components/ui/Modal.test.jsx`
- Create: `resources/js-app/components/ui/ConfirmModal.jsx`
- Create: `resources/js-app/components/ui/ConfirmModal.test.jsx`

**Interfaces:**
- Produces:
  - `<Button variant="primary"|"secondary"|"danger" onClick loading disabled>{children}</Button>`
  - `<ToastProvider>{children}</ToastProvider>` + `useToast()` returning `{ showToast(message, type) }` where `type` is `'success'|'error'|'info'|'warn'` — matches the existing Blade `showToast(msg, type)` convention from CLAUDE.md rule 9.
  - `<Modal open onClose title>{children}</Modal>`
  - `<ConfirmModal open title body onConfirm onCancel />` — the React equivalent of the existing `confirmAction(title, body, onConfirm)` pattern from CLAUDE.md rule 11.
  - These four are consumed by every domain-entity component built in Task 7 onward.

- [ ] **Step 1: Write failing Button test**

Create `resources/js-app/components/ui/Button.test.jsx`:

```jsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import Button from './Button';

describe('Button', () => {
    it('calls onClick when clicked', () => {
        const onClick = vi.fn();
        render(<Button onClick={onClick}>Save</Button>);

        fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(onClick).toHaveBeenCalledOnce();
    });

    it('does not call onClick when disabled', () => {
        const onClick = vi.fn();
        render(<Button onClick={onClick} disabled>Save</Button>);

        fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(onClick).not.toHaveBeenCalled();
    });

    it('shows loading state and disables the button', () => {
        render(<Button loading>Save</Button>);

        expect(screen.getByRole('button')).toBeDisabled();
        expect(screen.getByText('Loading…')).toBeInTheDocument();
    });
});
```

- [ ] **Step 2: Run to verify failure**

Run: `npm run test -- Button`
Expected: FAIL — `Failed to resolve import "./Button"`.

- [ ] **Step 3: Implement Button**

Create `resources/js-app/components/ui/Button.jsx`:

```jsx
const VARIANT_CLASSES = {
    primary: 'bg-green-600 hover:bg-green-700 text-white',
    secondary: 'bg-white hover:bg-gray-50 text-gray-800 border border-gray-300',
    danger: 'bg-red-600 hover:bg-red-700 text-white',
};

export default function Button({
    children,
    variant = 'primary',
    loading = false,
    disabled = false,
    onClick,
    type = 'button',
}) {
    return (
        <button
            type={type}
            onClick={onClick}
            disabled={disabled || loading}
            className={`px-4 py-2 rounded-md text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed ${VARIANT_CLASSES[variant]}`}
        >
            {loading ? 'Loading…' : children}
        </button>
    );
}
```

- [ ] **Step 4: Run to verify pass**

Run: `npm run test -- Button`
Expected: PASS (3 tests)

- [ ] **Step 5: Write failing Toast test**

Create `resources/js-app/components/ui/Toast.test.jsx`:

```jsx
import { describe, it, expect } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { ToastProvider, useToast } from './Toast';

function Trigger() {
    const { showToast } = useToast();
    return <button onClick={() => showToast('Saved.', 'success')}>Trigger</button>;
}

describe('ToastProvider', () => {
    it('renders a toast when showToast is called', async () => {
        render(
            <ToastProvider>
                <Trigger />
            </ToastProvider>
        );

        screen.getByText('Trigger').click();

        await waitFor(() => expect(screen.getByText('Saved.')).toBeInTheDocument());
    });
});
```

- [ ] **Step 6: Run to verify failure**

Run: `npm run test -- Toast`
Expected: FAIL — module doesn't exist.

- [ ] **Step 7: Implement Toast**

Create `resources/js-app/components/ui/Toast.jsx`:

```jsx
import { createContext, useCallback, useContext, useState } from 'react';

const ToastContext = createContext(null);

const TYPE_CLASSES = {
    success: 'bg-green-600',
    error: 'bg-red-600',
    info: 'bg-blue-600',
    warn: 'bg-amber-500',
};

export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);

    const showToast = useCallback((message, type = 'info') => {
        const id = `${Date.now()}-${Math.random()}`;
        setToasts((current) => [...current, { id, message, type }]);

        setTimeout(() => {
            setToasts((current) => current.filter((t) => t.id !== id));
        }, 4000);
    }, []);

    const dismiss = (id) => setToasts((current) => current.filter((t) => t.id !== id));

    return (
        <ToastContext.Provider value={{ showToast }}>
            {children}
            <div className="fixed bottom-4 right-4 flex flex-col gap-2 z-50">
                {toasts.map((t) => (
                    <div
                        key={t.id}
                        className={`text-white px-4 py-3 rounded-md shadow-lg cursor-pointer ${TYPE_CLASSES[t.type] ?? TYPE_CLASSES.info}`}
                        onClick={() => dismiss(t.id)}
                    >
                        {t.message}
                    </div>
                ))}
            </div>
        </ToastContext.Provider>
    );
}

export function useToast() {
    const ctx = useContext(ToastContext);
    if (!ctx) throw new Error('useToast must be used within a ToastProvider');
    return ctx;
}
```

- [ ] **Step 8: Run to verify pass**

Run: `npm run test -- Toast`
Expected: PASS

- [ ] **Step 9: Write failing Modal test**

Create `resources/js-app/components/ui/Modal.test.jsx`:

```jsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import Modal from './Modal';

describe('Modal', () => {
    it('renders children and title when open', () => {
        render(<Modal open title="Edit Supplier" onClose={() => {}}>Body content</Modal>);

        expect(screen.getByText('Edit Supplier')).toBeInTheDocument();
        expect(screen.getByText('Body content')).toBeInTheDocument();
    });

    it('renders nothing when closed', () => {
        render(<Modal open={false} title="Edit Supplier" onClose={() => {}}>Body content</Modal>);

        expect(screen.queryByText('Body content')).not.toBeInTheDocument();
    });

    it('calls onClose when the close button is clicked', () => {
        const onClose = vi.fn();
        render(<Modal open title="Edit Supplier" onClose={onClose}>Body content</Modal>);

        fireEvent.click(screen.getByRole('button', { name: 'Close' }));

        expect(onClose).toHaveBeenCalledOnce();
    });
});
```

- [ ] **Step 10: Run to verify failure, then implement**

Run: `npm run test -- Modal` → FAIL, then create `resources/js-app/components/ui/Modal.jsx`:

```jsx
export default function Modal({ open, title, onClose, children }) {
    if (!open) return null;

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-40" style={{ padding: '1rem' }}>
            <div className="bg-white rounded-lg shadow-xl w-full" style={{ maxWidth: '32rem' }}>
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h2 className="font-semibold text-gray-800">{title}</h2>
                    <button aria-label="Close" onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        ×
                    </button>
                </div>
                <div className="p-6">{children}</div>
            </div>
        </div>
    );
}
```

Run: `npm run test -- Modal`
Expected: PASS (3 tests)

- [ ] **Step 11: Write failing ConfirmModal test**

Create `resources/js-app/components/ui/ConfirmModal.test.jsx`:

```jsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import ConfirmModal from './ConfirmModal';

describe('ConfirmModal', () => {
    it('calls onConfirm then onCancel is not called', () => {
        const onConfirm = vi.fn();
        const onCancel = vi.fn();
        render(
            <ConfirmModal
                open
                title="Delete supplier?"
                body="This cannot be undone."
                onConfirm={onConfirm}
                onCancel={onCancel}
            />
        );

        fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

        expect(onConfirm).toHaveBeenCalledOnce();
        expect(onCancel).not.toHaveBeenCalled();
    });

    it('calls onCancel when cancel is clicked', () => {
        const onCancel = vi.fn();
        render(
            <ConfirmModal
                open
                title="Delete supplier?"
                body="This cannot be undone."
                onConfirm={() => {}}
                onCancel={onCancel}
            />
        );

        fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(onCancel).toHaveBeenCalledOnce();
    });
});
```

- [ ] **Step 12: Run to verify failure, then implement**

Run: `npm run test -- ConfirmModal` → FAIL, then create `resources/js-app/components/ui/ConfirmModal.jsx`:

```jsx
import Modal from './Modal';
import Button from './Button';

export default function ConfirmModal({ open, title, body, onConfirm, onCancel }) {
    return (
        <Modal open={open} title={title} onClose={onCancel}>
            <p className="text-sm text-gray-600 mb-6">{body}</p>
            <div className="flex justify-end gap-3">
                <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                <Button variant="danger" onClick={onConfirm}>Confirm</Button>
            </div>
        </Modal>
    );
}
```

Run: `npm run test -- ConfirmModal`
Expected: PASS (2 tests)

- [ ] **Step 13: Commit**

```bash
git add resources/js-app/components/ui
git commit -m "feat: add shared Button, Toast, Modal, ConfirmModal components"
```

---

### Task 5: Shared UI library — Table (instant client-side search), FormField, Card

**Files:**
- Create: `resources/js-app/components/ui/Table.jsx`
- Create: `resources/js-app/components/ui/Table.test.jsx`
- Create: `resources/js-app/components/ui/FormField.jsx`
- Create: `resources/js-app/components/ui/FormField.test.jsx`
- Create: `resources/js-app/components/ui/Card.jsx`
- Create: `resources/js-app/components/ui/Card.test.jsx`

**Interfaces:**
- Produces:
  - `<Table columns={[{key, label, render?}]} rows={array} searchPlaceholder="Search suppliers…" rowKey={(row) => row.id} />` — filters `rows` client-side across every column's stringified value as the user types; renders a live "N of M" count. This is the exact mechanism CLAUDE.md rule 6 requires — all rows loaded once, JS filters, no `?search=`.
  - `<FormField label name value onChange error type="text"|"email"|"number"|"textarea" />`
  - `<Card title>{children}</Card>`
  - Consumed by every domain-entity `Row`/`Card`/`Form`/`Detail` component starting with Task 7.

- [ ] **Step 1: Write failing Table test**

Create `resources/js-app/components/ui/Table.test.jsx`:

```jsx
import { describe, it, expect } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import Table from './Table';

const rows = [
    { id: 1, name: 'Acme Steel', category: 'Raw Material' },
    { id: 2, name: 'Bolt & Co', category: 'Fasteners' },
];

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'category', label: 'Category' },
];

describe('Table', () => {
    it('renders all rows and a total count', () => {
        render(<Table columns={columns} rows={rows} rowKey={(r) => r.id} searchPlaceholder="Search…" />);

        expect(screen.getByText('Acme Steel')).toBeInTheDocument();
        expect(screen.getByText('Bolt & Co')).toBeInTheDocument();
        expect(screen.getByText('2')).toBeInTheDocument();
    });

    it('filters rows instantly as the user types, client-side', () => {
        render(<Table columns={columns} rows={rows} rowKey={(r) => r.id} searchPlaceholder="Search…" />);

        fireEvent.change(screen.getByPlaceholderText('Search…'), { target: { value: 'bolt' } });

        expect(screen.queryByText('Acme Steel')).not.toBeInTheDocument();
        expect(screen.getByText('Bolt & Co')).toBeInTheDocument();
        expect(screen.getByText('1 of 2')).toBeInTheDocument();
    });

    it('shows a no-results message when nothing matches', () => {
        render(<Table columns={columns} rows={rows} rowKey={(r) => r.id} searchPlaceholder="Search…" />);

        fireEvent.change(screen.getByPlaceholderText('Search…'), { target: { value: 'zzz' } });

        expect(screen.getByText('No results.')).toBeInTheDocument();
    });
});
```

- [ ] **Step 2: Run to verify failure**

Run: `npm run test -- Table`
Expected: FAIL — module doesn't exist.

- [ ] **Step 3: Implement Table**

Create `resources/js-app/components/ui/Table.jsx`:

```jsx
import { useMemo, useState } from 'react';

export default function Table({ columns, rows, rowKey, searchPlaceholder }) {
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return rows;

        return rows.filter((row) =>
            columns.some((col) => String(row[col.key] ?? '').toLowerCase().includes(q))
        );
    }, [rows, query, columns]);

    return (
        <div>
            <div className="flex items-center justify-between mb-3">
                <input
                    type="text"
                    placeholder={searchPlaceholder}
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                    style={{ maxWidth: '20rem' }}
                />
                <span className="text-sm text-gray-500 ml-4 whitespace-nowrap">
                    {query ? `${filtered.length} of ${rows.length}` : rows.length}
                </span>
            </div>

            {filtered.length === 0 ? (
                <p className="text-sm text-gray-500 py-6 text-center">No results.</p>
            ) : (
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-gray-500 border-b border-gray-200">
                            {columns.map((col) => (
                                <th key={col.key} className="py-2 pr-4 font-medium">{col.label}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.map((row) => (
                            <tr key={rowKey(row)} className="border-b border-gray-100">
                                {columns.map((col) => (
                                    <td key={col.key} className="py-2 pr-4">
                                        {col.render ? col.render(row) : row[col.key]}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
}
```

- [ ] **Step 4: Run to verify pass**

Run: `npm run test -- Table`
Expected: PASS (3 tests)

- [ ] **Step 5: Write failing FormField test**

Create `resources/js-app/components/ui/FormField.test.jsx`:

```jsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import FormField from './FormField';

describe('FormField', () => {
    it('renders label and value, and calls onChange', () => {
        const onChange = vi.fn();
        render(<FormField label="Supplier name" name="name" value="Acme" onChange={onChange} />);

        expect(screen.getByLabelText('Supplier name')).toHaveValue('Acme');

        fireEvent.change(screen.getByLabelText('Supplier name'), { target: { value: 'Acme Steel' } });

        expect(onChange).toHaveBeenCalledWith('name', 'Acme Steel');
    });

    it('renders an error message when provided', () => {
        render(<FormField label="Supplier name" name="name" value="" onChange={() => {}} error="Name is required." />);

        expect(screen.getByText('Name is required.')).toBeInTheDocument();
    });
});
```

- [ ] **Step 6: Run to verify failure, then implement**

Run: `npm run test -- FormField` → FAIL, then create `resources/js-app/components/ui/FormField.jsx`:

```jsx
export default function FormField({ label, name, value, onChange, error, type = 'text' }) {
    const inputProps = {
        id: name,
        name,
        value,
        onChange: (e) => onChange(name, e.target.value),
        className: `border rounded-md px-3 py-2 text-sm w-full ${error ? 'border-red-400' : 'border-gray-300'}`,
    };

    return (
        <div className="mb-4">
            <label htmlFor={name} className="block text-sm font-medium text-gray-700 mb-1">
                {label}
            </label>
            {type === 'textarea' ? (
                <textarea {...inputProps} />
            ) : (
                <input type={type} {...inputProps} />
            )}
            {error && <p className="text-sm text-red-600 mt-1">{error}</p>}
        </div>
    );
}
```

Run: `npm run test -- FormField`
Expected: PASS (2 tests)

- [ ] **Step 7: Write failing Card test, then implement**

Create `resources/js-app/components/ui/Card.test.jsx`:

```jsx
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import Card from './Card';

describe('Card', () => {
    it('renders a title and children', () => {
        render(<Card title="Supplier summary">Body</Card>);

        expect(screen.getByText('Supplier summary')).toBeInTheDocument();
        expect(screen.getByText('Body')).toBeInTheDocument();
    });
});
```

Run: `npm run test -- Card` → FAIL, then create `resources/js-app/components/ui/Card.jsx`:

```jsx
export default function Card({ title, children }) {
    return (
        <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
            {title && <h3 className="font-semibold text-gray-800 mb-3">{title}</h3>}
            {children}
        </div>
    );
}
```

Run: `npm run test -- Card`
Expected: PASS (1 test)

- [ ] **Step 8: Commit**

```bash
git add resources/js-app/components/ui
git commit -m "feat: add shared Table, FormField, Card components"
```

---

### Task 6: Dashboard vertical slice — API endpoint, React page, live update via Reverb

**Files:**
- Modify: `app/Http/Controllers/Api/DashboardController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/DashboardControllerTest.php`
- Create: `resources/js-app/api/client.js`
- Create: `resources/js-app/pages/DashboardPage.jsx`
- Create: `resources/js-app/pages/DashboardPage.test.jsx`
- Modify: `resources/js-app/App.jsx`

**Interfaces:**
- Consumes: `Button`, `Card` from Task 4/5; `echo` from Task 3; `DashboardPinged` event shape `{message, at}` from Task 2.
- Produces: `resources/js-app/api/client.js` exports `apiGet(path)` / `apiPost(path, body)` — every later page/component uses these two functions for all API calls (handles CSRF cookie via Sanctum's `/sanctum/csrf-cookie`, credentials `include`, JSON parsing, error rejection). `DashboardPage` is mounted at `/app` in `App.jsx`.

- [ ] **Step 1: Write failing test for the dashboard summary endpoint**

Create `tests/Feature/Api/DashboardControllerTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard/summary')->assertStatus(401);
    }

    public function test_summary_returns_supplier_count(): void
    {
        $user = User::factory()->create();
        Supplier::factory()->count(3)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard/summary');

        $response->assertOk()->assertJsonPath('suppliers_total', 3);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=DashboardControllerTest`
Expected: FAIL — either route not found or missing `SupplierFactory`.

If it fails on a missing factory, create `database/factories/SupplierFactory.php`:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supplier_code' => 'SUP-' . $this->faker->unique()->numberBetween(1000, 9999),
            'name' => $this->faker->company(),
            'category' => $this->faker->randomElement(['Raw Material', 'Fasteners', 'Equipment']),
            'email' => $this->faker->unique()->companyEmail(),
            'is_active' => true,
        ];
    }
}
```

- [ ] **Step 3: Add the summary route and controller method**

Add to `routes/api.php`, inside the `auth:sanctum` group:

```php
        Route::get('dashboard/summary', [\App\Http\Controllers\Api\DashboardController::class, 'summary']);
```

Add to `app/Http/Controllers/Api/DashboardController.php` (alongside the existing `ping` method):

```php
    public function summary()
    {
        return response()->json([
            'suppliers_total' => \App\Models\Supplier::count(),
        ]);
    }
```

- [ ] **Step 4: Run to verify pass**

Run: `php artisan test --filter=DashboardControllerTest`
Expected: PASS

- [ ] **Step 5: Implement the API client helper**

Create `resources/js-app/api/client.js`:

```js
function getCookie(name) {
    const match = document.cookie.match(new RegExp(`(^| )${name}=([^;]+)`));
    return match ? decodeURIComponent(match[2]) : null;
}

async function ensureCsrfCookie() {
    if (getCookie('XSRF-TOKEN')) return;
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
}

async function request(path, options = {}) {
    await ensureCsrfCookie();

    const response = await fetch(`/api/v1${path}`, {
        credentials: 'include',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') ?? '',
            ...options.headers,
        },
        ...options,
    });

    const body = await response.json().catch(() => null);

    if (!response.ok) {
        return Promise.reject(body ?? { message: 'Request failed.' });
    }

    return body;
}

export const apiGet = (path) => request(path);
export const apiPost = (path, data) => request(path, { method: 'POST', body: JSON.stringify(data) });
export const apiPut = (path, data) => request(path, { method: 'PUT', body: JSON.stringify(data) });
export const apiDelete = (path) => request(path, { method: 'DELETE' });
```

- [ ] **Step 6: Write failing test for DashboardPage**

Create `resources/js-app/pages/DashboardPage.test.jsx`:

```jsx
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { ToastProvider } from '../components/ui/Toast';
import * as client from '../api/client';
import DashboardPage from './DashboardPage';

vi.mock('../echo', () => ({
    echo: { private: () => ({ listen: () => {} }) },
}));

describe('DashboardPage', () => {
    beforeEach(() => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ suppliers_total: 7 });
    });

    it('loads and displays the supplier total', async () => {
        render(
            <ToastProvider>
                <DashboardPage currentUserId={1} />
            </ToastProvider>
        );

        await waitFor(() => expect(screen.getByText('7')).toBeInTheDocument());
    });
});
```

- [ ] **Step 7: Run to verify failure**

Run: `npm run test -- DashboardPage`
Expected: FAIL — module doesn't exist.

- [ ] **Step 8: Implement DashboardPage**

Create `resources/js-app/pages/DashboardPage.jsx`:

```jsx
import { useEffect, useState } from 'react';
import Card from '../components/ui/Card';
import { apiGet } from '../api/client';
import { echo } from '../echo';
import { useToast } from '../components/ui/Toast';

export default function DashboardPage({ currentUserId }) {
    const [summary, setSummary] = useState(null);
    const { showToast } = useToast();

    useEffect(() => {
        apiGet('/dashboard/summary').then(setSummary);
    }, []);

    useEffect(() => {
        if (!currentUserId) return;

        const channel = echo.private(`App.Models.User.${currentUserId}`);
        channel.listen('.dashboard.pinged', (event) => {
            showToast(event.message, 'info');
        });

        return () => echo.leave(`App.Models.User.${currentUserId}`);
    }, [currentUserId, showToast]);

    return (
        <Card title="Suppliers">
            <p className="text-3xl font-bold text-gray-800">
                {summary ? summary.suppliers_total : '…'}
            </p>
        </Card>
    );
}
```

- [ ] **Step 9: Run to verify pass**

Run: `npm run test -- DashboardPage`
Expected: PASS

- [ ] **Step 10: Mount DashboardPage in the App router**

Edit `resources/js-app/App.jsx`:

```jsx
import { Routes, Route } from 'react-router-dom';
import DashboardPage from './pages/DashboardPage';

export default function App({ currentUserId }) {
    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white border-b border-gray-100 px-6 py-4">
                <span className="font-bold text-gray-800">SteelERP</span>
            </header>
            <main className="p-6">
                <Routes>
                    <Route path="/app" element={<DashboardPage currentUserId={currentUserId} />} />
                </Routes>
            </main>
        </div>
    );
}
```

Update the App shell test at `resources/js-app/App.test.jsx` to mock the API client the same way `DashboardPage.test.jsx` does, since `App` now renders `DashboardPage` at `/app`:

```jsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { ToastProvider } from './components/ui/Toast';
import * as client from './api/client';
import App from './App';

vi.mock('./echo', () => ({
    echo: { private: () => ({ listen: () => {} }) },
}));

describe('App', () => {
    it('renders the SteelERP app shell', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ suppliers_total: 0 });

        render(
            <MemoryRouter initialEntries={['/app']}>
                <ToastProvider>
                    <App currentUserId={1} />
                </ToastProvider>
            </MemoryRouter>
        );

        expect(screen.getByText('SteelERP')).toBeInTheDocument();
    });
});
```

Pass the authenticated user's id from the Blade shell into React via a data attribute, so `main.jsx` can read it. Edit `resources/views/app-shell.blade.php`'s body:

```blade
    <div id="react-app" data-user-id="{{ auth()->id() }}"></div>
```

Edit `resources/js-app/main.jsx` to read it and wrap `App` in `ToastProvider`:

```jsx
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App';
import { ToastProvider } from './components/ui/Toast';

const container = document.getElementById('react-app');
const currentUserId = Number(container.dataset.userId) || null;

createRoot(container).render(
    <StrictMode>
        <BrowserRouter>
            <ToastProvider>
                <App currentUserId={currentUserId} />
            </ToastProvider>
        </BrowserRouter>
    </StrictMode>
);
```

- [ ] **Step 11: Run the full frontend suite to verify nothing regressed**

Run: `npm run test`
Expected: PASS (all suites)

- [ ] **Step 12: Commit**

```bash
git add app/Http/Controllers/Api/DashboardController.php routes/api.php database/factories/SupplierFactory.php tests/Feature/Api/DashboardControllerTest.php resources/js-app resources/views/app-shell.blade.php
git commit -m "feat: dashboard vertical slice with live update via Reverb"
```

---

### Task 7: Supplier API endpoints

**Files:**
- Create: `app/Http/Controllers/Api/Purchase/SupplierController.php`
- Create: `app/Http/Resources/SupplierResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/Purchase/SupplierControllerTest.php`

**Interfaces:**
- Consumes: `SupplierFactory` from Task 6.
- Produces: `GET /api/v1/purchase/suppliers` → `{data: [SupplierResource, ...]}`, `GET /api/v1/purchase/suppliers/{supplier}` → `{data: SupplierResource}`, `POST /api/v1/purchase/suppliers`, `PUT /api/v1/purchase/suppliers/{supplier}`, `DELETE /api/v1/purchase/suppliers/{supplier}`. `SupplierResource` shape: `{id, supplier_code, name, category, contact_person, email, phone, whatsapp_number, is_active, credit_days}` — this exact shape is what Task 8's React components consume.

- [ ] **Step 1: Write failing tests for all five endpoints**

Create `tests/Feature/Api/Purchase/SupplierControllerTest.php`:

```php
<?php

namespace Tests\Feature\Api\Purchase;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/purchase/suppliers')->assertStatus(401);
    }

    public function test_index_returns_suppliers_ordered_by_name(): void
    {
        $user = User::factory()->create();
        Supplier::factory()->create(['name' => 'Zeta Steel']);
        Supplier::factory()->create(['name' => 'Acme Steel']);

        $response = $this->actingAs($user)->getJson('/api/v1/purchase/suppliers');

        $response->assertOk();
        $this->assertSame('Acme Steel', $response->json('data.0.name'));
        $this->assertSame('Zeta Steel', $response->json('data.1.name'));
    }

    public function test_show_returns_a_single_supplier(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/v1/purchase/suppliers/{$supplier->id}");

        $response->assertOk()->assertJsonPath('data.id', $supplier->id);
    }

    public function test_store_creates_a_supplier(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/purchase/suppliers', [
            'name' => 'New Supplier Co',
            'email' => 'contact@newsupplier.test',
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'New Supplier Co');
        $this->assertDatabaseHas('suppliers', ['name' => 'New Supplier Co']);
    }

    public function test_store_requires_a_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/purchase/suppliers', [])
            ->assertStatus(422);
    }

    public function test_update_modifies_a_supplier(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)->putJson("/api/v1/purchase/suppliers/{$supplier->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_destroy_deletes_a_supplier(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();

        $this->actingAs($user)->deleteJson("/api/v1/purchase/suppliers/{$supplier->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=SupplierControllerTest`
Expected: FAIL — route/controller not found.

- [ ] **Step 3: Implement the API resource**

Create `app/Http/Resources/SupplierResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'supplier_code' => $this->supplier_code,
            'name' => $this->name,
            'category' => $this->category,
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp_number' => $this->whatsapp_number,
            'is_active' => $this->is_active,
            'credit_days' => $this->credit_days,
        ];
    }
}
```

- [ ] **Step 4: Implement the controller**

Create `app/Http/Controllers/Api/Purchase/SupplierController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        return SupplierResource::collection(Supplier::orderBy('name')->get());
    }

    public function show(Supplier $supplier)
    {
        return new SupplierResource($supplier);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_code' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'whatsapp_number' => 'nullable|string|max:20',
            'credit_days' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $supplier = Supplier::create(array_merge($data, [
            'is_active' => $data['is_active'] ?? true,
        ]));

        return (new SupplierResource($supplier))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'supplier_code' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'whatsapp_number' => 'nullable|string|max:20',
            'credit_days' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $supplier->update($data);

        return new SupplierResource($supplier);
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return response()->noContent();
    }
}
```

- [ ] **Step 5: Register the routes**

Add to `routes/api.php`, inside the `auth:sanctum` group:

```php
        Route::prefix('purchase')->group(function () {
            Route::apiResource('suppliers', \App\Http\Controllers\Api\Purchase\SupplierController::class);
        });
```

- [ ] **Step 6: Run to verify pass**

Run: `php artisan test --filter=SupplierControllerTest`
Expected: PASS (7 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/Purchase/SupplierController.php app/Http/Resources/SupplierResource.php routes/api.php tests/Feature/Api/Purchase/SupplierControllerTest.php
git commit -m "feat: add Supplier API endpoints"
```

---

### Task 8: Supplier domain-entity components (Row, Card, Form, Detail) + mounted list page

**Files:**
- Create: `resources/js-app/components/purchase/supplier/SupplierRow.jsx`
- Create: `resources/js-app/components/purchase/supplier/SupplierCard.jsx`
- Create: `resources/js-app/components/purchase/supplier/SupplierForm.jsx`
- Create: `resources/js-app/components/purchase/supplier/SupplierForm.test.jsx`
- Create: `resources/js-app/components/purchase/supplier/SupplierDetail.jsx`
- Create: `resources/js-app/pages/purchase/SupplierListPage.jsx`
- Create: `resources/js-app/pages/purchase/SupplierListPage.test.jsx`
- Modify: `resources/js-app/App.jsx`

**Interfaces:**
- Consumes: `Table`, `Button`, `FormField`, `Modal`, `ConfirmModal`, `Card` (Tasks 4/5), `apiGet`/`apiPost` (Task 6), `SupplierResource` shape (Task 7: `{id, supplier_code, name, category, contact_person, email, phone, whatsapp_number, is_active, credit_days}`).
- Produces: `SupplierListPage` mounted at `/app/purchase/suppliers` — the template every later module's list page (Items, Purchase Orders, etc.) copies.

- [ ] **Step 1: Implement SupplierRow (presentational, no test needed — pure render mapping covered by SupplierListPage's integration test)**

Create `resources/js-app/components/purchase/supplier/SupplierRow.jsx`:

```jsx
export function supplierTableColumns({ onEdit, onDelete }) {
    return [
        { key: 'supplier_code', label: 'Code' },
        { key: 'name', label: 'Name' },
        { key: 'category', label: 'Category' },
        { key: 'email', label: 'Email' },
        {
            key: 'is_active',
            label: 'Status',
            render: (row) => (row.is_active ? 'Active' : 'Inactive'),
        },
        {
            key: 'actions',
            label: '',
            render: (row) => (
                <div className="flex gap-2">
                    <button className="text-blue-600 text-sm" onClick={() => onEdit(row)}>Edit</button>
                    <button className="text-red-600 text-sm" onClick={() => onDelete(row)}>Delete</button>
                </div>
            ),
        },
    ];
}
```

- [ ] **Step 2: Implement SupplierCard**

Create `resources/js-app/components/purchase/supplier/SupplierCard.jsx`:

```jsx
import Card from '../../ui/Card';

export default function SupplierCard({ supplier }) {
    return (
        <Card title={supplier.name}>
            <dl className="text-sm text-gray-600 space-y-1">
                <div><dt className="inline font-medium">Code: </dt><dd className="inline">{supplier.supplier_code ?? '—'}</dd></div>
                <div><dt className="inline font-medium">Category: </dt><dd className="inline">{supplier.category ?? '—'}</dd></div>
                <div><dt className="inline font-medium">Email: </dt><dd className="inline">{supplier.email ?? '—'}</dd></div>
                <div><dt className="inline font-medium">Status: </dt><dd className="inline">{supplier.is_active ? 'Active' : 'Inactive'}</dd></div>
            </dl>
        </Card>
    );
}
```

- [ ] **Step 3: Write failing SupplierForm test**

Create `resources/js-app/components/purchase/supplier/SupplierForm.test.jsx`:

```jsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import SupplierForm from './SupplierForm';

describe('SupplierForm', () => {
    it('submits the current field values', () => {
        const onSubmit = vi.fn();
        render(<SupplierForm initialValues={{ name: '', email: '' }} errors={{}} onSubmit={onSubmit} submitting={false} />);

        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Acme Steel' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({ name: 'Acme Steel' }));
    });

    it('shows validation errors passed in as props', () => {
        render(<SupplierForm initialValues={{ name: '' }} errors={{ name: 'Name is required.' }} onSubmit={() => {}} submitting={false} />);

        expect(screen.getByText('Name is required.')).toBeInTheDocument();
    });
});
```

- [ ] **Step 4: Run to verify failure**

Run: `npm run test -- SupplierForm`
Expected: FAIL — module doesn't exist.

- [ ] **Step 5: Implement SupplierForm**

Create `resources/js-app/components/purchase/supplier/SupplierForm.jsx`:

```jsx
import { useState } from 'react';
import FormField from '../../ui/FormField';
import Button from '../../ui/Button';

export default function SupplierForm({ initialValues, errors, onSubmit, submitting }) {
    const [values, setValues] = useState({
        name: '',
        supplier_code: '',
        category: '',
        email: '',
        phone: '',
        ...initialValues,
    });

    const handleChange = (name, value) => setValues((current) => ({ ...current, [name]: value }));

    return (
        <form onSubmit={(e) => { e.preventDefault(); onSubmit(values); }}>
            <FormField label="Name" name="name" value={values.name} onChange={handleChange} error={errors.name} />
            <FormField label="Supplier code" name="supplier_code" value={values.supplier_code} onChange={handleChange} error={errors.supplier_code} />
            <FormField label="Category" name="category" value={values.category} onChange={handleChange} error={errors.category} />
            <FormField label="Email" name="email" type="email" value={values.email} onChange={handleChange} error={errors.email} />
            <FormField label="Phone" name="phone" value={values.phone} onChange={handleChange} error={errors.phone} />
            <div className="flex justify-end">
                <Button type="submit" loading={submitting}>Save</Button>
            </div>
        </form>
    );
}
```

- [ ] **Step 6: Run to verify pass**

Run: `npm run test -- SupplierForm`
Expected: PASS (2 tests)

- [ ] **Step 7: Implement SupplierDetail**

Create `resources/js-app/components/purchase/supplier/SupplierDetail.jsx`:

```jsx
import Card from '../../ui/Card';

export default function SupplierDetail({ supplier }) {
    return (
        <Card title={supplier.name}>
            <dl className="grid grid-cols-2 gap-4 text-sm text-gray-600">
                <div><dt className="font-medium text-gray-800">Supplier code</dt><dd>{supplier.supplier_code ?? '—'}</dd></div>
                <div><dt className="font-medium text-gray-800">Category</dt><dd>{supplier.category ?? '—'}</dd></div>
                <div><dt className="font-medium text-gray-800">Contact person</dt><dd>{supplier.contact_person ?? '—'}</dd></div>
                <div><dt className="font-medium text-gray-800">Email</dt><dd>{supplier.email ?? '—'}</dd></div>
                <div><dt className="font-medium text-gray-800">Phone</dt><dd>{supplier.phone ?? '—'}</dd></div>
                <div><dt className="font-medium text-gray-800">Credit days</dt><dd>{supplier.credit_days ?? '—'}</dd></div>
                <div><dt className="font-medium text-gray-800">Status</dt><dd>{supplier.is_active ? 'Active' : 'Inactive'}</dd></div>
            </dl>
        </Card>
    );
}
```

- [ ] **Step 8: Write failing SupplierListPage integration test**

Create `resources/js-app/pages/purchase/SupplierListPage.test.jsx`:

```jsx
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { ToastProvider } from '../../components/ui/Toast';
import * as client from '../../api/client';
import SupplierListPage from './SupplierListPage';

const suppliers = [
    { id: 1, supplier_code: 'SUP-1', name: 'Acme Steel', category: 'Raw Material', email: 'a@acme.test', is_active: true },
    { id: 2, supplier_code: 'SUP-2', name: 'Bolt & Co', category: 'Fasteners', email: 'b@bolt.test', is_active: false },
];

describe('SupplierListPage', () => {
    beforeEach(() => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: suppliers });
    });

    it('loads and lists suppliers, and filters instantly by search', async () => {
        render(
            <ToastProvider>
                <SupplierListPage />
            </ToastProvider>
        );

        await waitFor(() => expect(screen.getByText('Acme Steel')).toBeInTheDocument());
        expect(screen.getByText('Bolt & Co')).toBeInTheDocument();

        fireEvent.change(screen.getByPlaceholderText('Search suppliers…'), { target: { value: 'bolt' } });

        expect(screen.queryByText('Acme Steel')).not.toBeInTheDocument();
        expect(screen.getByText('Bolt & Co')).toBeInTheDocument();
    });

    it('opens the create-supplier modal', async () => {
        render(
            <ToastProvider>
                <SupplierListPage />
            </ToastProvider>
        );

        await waitFor(() => expect(screen.getByText('Acme Steel')).toBeInTheDocument());

        fireEvent.click(screen.getByRole('button', { name: 'New Supplier' }));

        expect(screen.getByText('Add Supplier')).toBeInTheDocument();
    });
});
```

- [ ] **Step 9: Run to verify failure**

Run: `npm run test -- SupplierListPage`
Expected: FAIL — module doesn't exist.

- [ ] **Step 10: Implement SupplierListPage**

Create `resources/js-app/pages/purchase/SupplierListPage.jsx`:

```jsx
import { useEffect, useState } from 'react';
import Table from '../../components/ui/Table';
import Button from '../../components/ui/Button';
import Modal from '../../components/ui/Modal';
import ConfirmModal from '../../components/ui/ConfirmModal';
import SupplierForm from '../../components/purchase/supplier/SupplierForm';
import { supplierTableColumns } from '../../components/purchase/supplier/SupplierRow';
import { apiGet, apiPost, apiPut, apiDelete } from '../../api/client';
import { useToast } from '../../components/ui/Toast';

export default function SupplierListPage() {
    const [suppliers, setSuppliers] = useState([]);
    const [editing, setEditing] = useState(null);
    const [formOpen, setFormOpen] = useState(false);
    const [pendingDelete, setPendingDelete] = useState(null);
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);
    const { showToast } = useToast();

    const load = () => apiGet('/purchase/suppliers').then((res) => setSuppliers(res.data));

    useEffect(() => { load(); }, []);

    const openCreate = () => { setEditing(null); setErrors({}); setFormOpen(true); };
    const openEdit = (supplier) => { setEditing(supplier); setErrors({}); setFormOpen(true); };

    const handleSubmit = async (values) => {
        setSubmitting(true);
        try {
            if (editing) {
                await apiPut(`/purchase/suppliers/${editing.id}`, values);
                showToast('Supplier updated.', 'success');
            } else {
                await apiPost('/purchase/suppliers', values);
                showToast('Supplier created.', 'success');
            }
            setFormOpen(false);
            await load();
        } catch (err) {
            setErrors(err.errors ? Object.fromEntries(Object.entries(err.errors).map(([k, v]) => [k, v[0]])) : {});
            showToast(err.message ?? 'Error', 'error');
        } finally {
            setSubmitting(false);
        }
    };

    const handleDelete = async () => {
        try {
            await apiDelete(`/purchase/suppliers/${pendingDelete.id}`);
            showToast('Supplier deleted.', 'success');
            setPendingDelete(null);
            await load();
        } catch (err) {
            showToast(err.message ?? 'Error', 'error');
        }
    };

    return (
        <div>
            <div className="flex justify-between items-center mb-4">
                <h1 className="text-xl font-semibold text-gray-800">Suppliers</h1>
                <Button onClick={openCreate}>New Supplier</Button>
            </div>

            <Table
                columns={supplierTableColumns({ onEdit: openEdit, onDelete: setPendingDelete })}
                rows={suppliers}
                rowKey={(row) => row.id}
                searchPlaceholder="Search suppliers…"
            />

            <Modal open={formOpen} title={editing ? 'Edit Supplier' : 'Add Supplier'} onClose={() => setFormOpen(false)}>
                <SupplierForm
                    initialValues={editing ?? {}}
                    errors={errors}
                    submitting={submitting}
                    onSubmit={handleSubmit}
                />
            </Modal>

            <ConfirmModal
                open={!!pendingDelete}
                title="Delete supplier?"
                body={pendingDelete ? `This will permanently remove "${pendingDelete.name}".` : ''}
                onConfirm={handleDelete}
                onCancel={() => setPendingDelete(null)}
            />
        </div>
    );
}
```

- [ ] **Step 11: Run to verify pass**

Run: `npm run test -- SupplierListPage`
Expected: PASS (2 tests)

- [ ] **Step 12: Mount the route and add a nav link**

Edit `resources/js-app/App.jsx`, add the route:

```jsx
import { Routes, Route } from 'react-router-dom';
import DashboardPage from './pages/DashboardPage';
import SupplierListPage from './pages/purchase/SupplierListPage';

export default function App({ currentUserId }) {
    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white border-b border-gray-100 px-6 py-4 flex gap-6 items-center">
                <span className="font-bold text-gray-800">SteelERP</span>
                <a href="/app" className="text-sm text-gray-600">Dashboard</a>
                <a href="/app/purchase/suppliers" className="text-sm text-gray-600">Suppliers</a>
            </header>
            <main className="p-6">
                <Routes>
                    <Route path="/app" element={<DashboardPage currentUserId={currentUserId} />} />
                    <Route path="/app/purchase/suppliers" element={<SupplierListPage />} />
                </Routes>
            </main>
        </div>
    );
}
```

Add a link to the migrated module from the existing Blade sidebar so users can discover it. Edit `resources/views/layouts/navigation.blade.php`, inside the existing nav links section (near the `Dashboard` link):

```blade
                    <x-nav-link href="/app/purchase/suppliers" :active="request()->is('app/purchase/*')">
                        {{ __('Suppliers (New)') }}
                    </x-nav-link>
```

- [ ] **Step 13: Run the full frontend and backend suites**

Run: `npm run test && php artisan test`
Expected: PASS (all suites, both runners)

- [ ] **Step 14: Commit**

```bash
git add resources/js-app resources/views/layouts/navigation.blade.php
git commit -m "feat: Supplier domain components and list page, mounted at /app/purchase/suppliers"
```

---

## Definition of Done for this plan

- `php artisan test` passes in full.
- `npm run test` passes in full.
- Visiting `/app` while logged in shows the live dashboard; visiting `/app/purchase/suppliers` shows a working supplier list with instant client-side search, create/edit modal, and delete confirmation, all backed by the new API — with zero changes to any existing Blade route, view, or controller.
- This plan does not migrate any other module. The next plan (Purchase module full migration) starts from this same pattern: one API controller + resource per entity, one component family per entity, one list/detail page per entity, mounted under `/app/purchase/...`.
