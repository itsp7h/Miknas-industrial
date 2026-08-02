# App Shell + Supplier React Migration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Blade app shell (sidebar/topbar/notification bell) with a React shell that instantly switches between desktop and mobile layouts, wire live (Reverb-pushed) notifications, and convert the Supplier page (Purchase module) to React end-to-end as the template for the remaining Purchase entities.

**Architecture:** `resources/js-app/` gains a `useViewport()` hook and `AppShell`/`DesktopShell`/`MobileShell` layout components. `layouts/app.blade.php` (Blade sidebar) keeps serving not-yet-migrated modules; `app-shell.blade.php` (React) now serves Dashboard and the new `/app/purchase/suppliers` route. Backend gets a `SupplierSaved` broadcast event on a shared private `purchase` Reverb channel, and a new `Api/Purchase/SupplierController`. The old Blade Supplier controller/routes/views are deleted in the same change.

**Tech Stack:** Laravel 12 (Reverb, Sanctum SPA), React 19 + React Router 7, Vitest + React Testing Library, PHPUnit 11.

## Global Constraints

- Mobile and desktop are separate component files (`pages/desktop/...` vs `pages/mobile/...`), never one file branching on device inline. — spec `2026-08-02-mobile-view-and-live-everywhere-design.md`
- Viewport breakpoint is 768px; switching is instant on resize/rotate, no reload. — same spec
- All state changes reaching a user on screen go through Reverb broadcast + Echo, not polling. — same spec
- Module cutover is full-replace: old Blade Supplier controller/routes/views are deleted in this same change, never left coexisting. — same spec, CLAUDE.md gotcha #12
- Data entry is AJAX-only (`fetch`, JSON responses), no `<form>` POST submits. — CLAUDE.md gotcha #11
- No `alert()`/`confirm()`/`prompt()` — use `showToast` / modals. — CLAUDE.md gotcha #7
- `layouts/app.blade.php` keeps serving Inventory/Production/Sales/Settings until their own migration phase; must stay visually consistent with the new React shell. — spec, "Shell Coexistence" section

---

### Task 1: `useViewport()` hook

**Files:**
- Create: `resources/js-app/hooks/useViewport.js`
- Test: `resources/js-app/hooks/useViewport.test.js`

**Interfaces:**
- Produces: `useViewport(): 'mobile' | 'desktop'` — a React hook, breakpoint 768px (`window.innerWidth < 768` → `'mobile'`), re-evaluates live on `resize`.

- [ ] **Step 1: Write the failing test**

```javascript
// resources/js-app/hooks/useViewport.test.js
import { describe, it, expect, afterEach } from 'vitest';
import { render, screen, act } from '@testing-library/react';
import useViewport from './useViewport';

function Probe() {
    const viewport = useViewport();
    return <div>viewport:{viewport}</div>;
}

function setWidth(width) {
    window.innerWidth = width;
    window.dispatchEvent(new Event('resize'));
}

describe('useViewport', () => {
    afterEach(() => setWidth(1024));

    it('reports desktop above the 768px breakpoint', () => {
        setWidth(1024);
        render(<Probe />);
        expect(screen.getByText('viewport:desktop')).toBeInTheDocument();
    });

    it('reports mobile below the 768px breakpoint', () => {
        setWidth(500);
        render(<Probe />);
        expect(screen.getByText('viewport:mobile')).toBeInTheDocument();
    });

    it('switches live on resize without remounting', () => {
        setWidth(1024);
        render(<Probe />);
        expect(screen.getByText('viewport:desktop')).toBeInTheDocument();

        act(() => setWidth(400));
        expect(screen.getByText('viewport:mobile')).toBeInTheDocument();
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- useViewport`
Expected: FAIL — `Cannot find module './useViewport'`

- [ ] **Step 3: Write the implementation**

```javascript
// resources/js-app/hooks/useViewport.js
import { useEffect, useState } from 'react';

const BREAKPOINT = 768;

function readViewport() {
    return window.innerWidth < BREAKPOINT ? 'mobile' : 'desktop';
}

export default function useViewport() {
    const [viewport, setViewport] = useState(readViewport);

    useEffect(() => {
        function onResize() {
            setViewport(readViewport());
        }
        window.addEventListener('resize', onResize);
        return () => window.removeEventListener('resize', onResize);
    }, []);

    return viewport;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test -- useViewport`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add resources/js-app/hooks/useViewport.js resources/js-app/hooks/useViewport.test.js
git commit -m "feat: add useViewport hook for live desktop/mobile switching"
```

---

### Task 2: `DesktopShell`, `MobileShell`, `AppShell`

**Files:**
- Create: `resources/js-app/layouts/DesktopShell.jsx`
- Create: `resources/js-app/layouts/MobileShell.jsx`
- Create: `resources/js-app/layouts/AppShell.jsx`
- Create: `resources/js-app/layouts/AppShell.test.jsx`
- Modify: `resources/js-app/App.jsx`

**Interfaces:**
- Consumes: `useViewport()` from Task 1.
- Produces: `<AppShell>{children}</AppShell>` — renders `<DesktopShell>` or `<MobileShell>` based on viewport, each wrapping `children` with nav chrome. Both shells accept no other props; nav items are hardcoded (Dashboard, Suppliers) for this plan and extended by later Purchase-entity plans.

- [ ] **Step 1: Write the failing test**

```jsx
// resources/js-app/layouts/AppShell.test.jsx
import { describe, it, expect, afterEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import AppShell from './AppShell';

function setWidth(width) {
    window.innerWidth = width;
    window.dispatchEvent(new Event('resize'));
}

describe('AppShell', () => {
    afterEach(() => setWidth(1024));

    it('renders the desktop sidebar shell above the breakpoint', () => {
        setWidth(1024);
        render(
            <MemoryRouter>
                <AppShell><div>page content</div></AppShell>
            </MemoryRouter>
        );
        expect(screen.getByTestId('desktop-shell')).toBeInTheDocument();
        expect(screen.getByText('page content')).toBeInTheDocument();
    });

    it('renders the mobile shell below the breakpoint', () => {
        setWidth(500);
        render(
            <MemoryRouter>
                <AppShell><div>page content</div></AppShell>
            </MemoryRouter>
        );
        expect(screen.getByTestId('mobile-shell')).toBeInTheDocument();
        expect(screen.getByText('page content')).toBeInTheDocument();
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- AppShell`
Expected: FAIL — `Cannot find module './AppShell'`

- [ ] **Step 3: Write `DesktopShell.jsx`**

```jsx
// resources/js-app/layouts/DesktopShell.jsx
import { Link, useLocation } from 'react-router-dom';
import NotificationBell from '../components/NotificationBell';

const NAV_ITEMS = [
    { to: '/app', label: 'Dashboard' },
    { to: '/app/purchase/suppliers', label: 'Suppliers' },
];

export default function DesktopShell({ children }) {
    const location = useLocation();

    return (
        <div data-testid="desktop-shell" style={{ display: 'flex', minHeight: '100vh' }}>
            <aside style={{ width: 260, minWidth: 260, background: '#0f172a', color: '#fff' }}>
                <div style={{ padding: '20px 20px 16px', borderBottom: '1px solid #1e293b' }}>
                    <div style={{ fontWeight: 700, fontSize: 15 }}>SteelERP</div>
                    <div style={{ color: '#64748b', fontSize: 11 }}>Manufacturing &amp; Trading</div>
                </div>
                <nav style={{ padding: 12 }}>
                    {NAV_ITEMS.map((item) => (
                        <Link
                            key={item.to}
                            to={item.to}
                            style={{
                                display: 'block',
                                padding: '8px 12px',
                                borderRadius: 8,
                                marginBottom: 2,
                                fontSize: 13.5,
                                fontWeight: 500,
                                textDecoration: 'none',
                                color: location.pathname === item.to ? '#fff' : '#94a3b8',
                                background: location.pathname === item.to ? '#2563eb' : 'transparent',
                            }}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>
            </aside>
            <div style={{ flex: 1, display: 'flex', flexDirection: 'column' }}>
                <header style={{
                    height: 60, borderBottom: '1px solid #e2e8f0',
                    display: 'flex', alignItems: 'center', justifyContent: 'flex-end', padding: '0 24px',
                }}>
                    <NotificationBell />
                </header>
                <main style={{ flex: 1, padding: 24 }}>{children}</main>
            </div>
        </div>
    );
}
```

- [ ] **Step 4: Write `MobileShell.jsx`**

```jsx
// resources/js-app/layouts/MobileShell.jsx
import { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import NotificationBell from '../components/NotificationBell';

const NAV_ITEMS = [
    { to: '/app', label: 'Dashboard' },
    { to: '/app/purchase/suppliers', label: 'Suppliers' },
];

export default function MobileShell({ children }) {
    const [menuOpen, setMenuOpen] = useState(false);
    const location = useLocation();

    return (
        <div data-testid="mobile-shell" style={{ minHeight: '100vh' }}>
            <header style={{
                display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                padding: '12px 16px', borderBottom: '1px solid #e2e8f0',
            }}>
                <button aria-label="Menu" onClick={() => setMenuOpen((v) => !v)} style={{ fontSize: 20 }}>
                    ☰
                </button>
                <span style={{ fontWeight: 700 }}>SteelERP</span>
                <NotificationBell />
            </header>

            {menuOpen && (
                <nav style={{ borderBottom: '1px solid #e2e8f0' }}>
                    {NAV_ITEMS.map((item) => (
                        <Link
                            key={item.to}
                            to={item.to}
                            onClick={() => setMenuOpen(false)}
                            style={{
                                display: 'block',
                                padding: '12px 16px',
                                textDecoration: 'none',
                                color: location.pathname === item.to ? '#2563eb' : '#334155',
                                fontWeight: location.pathname === item.to ? 600 : 400,
                            }}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>
            )}

            <main style={{ padding: 16 }}>{children}</main>
        </div>
    );
}
```

- [ ] **Step 5: Write `AppShell.jsx`**

```jsx
// resources/js-app/layouts/AppShell.jsx
import useViewport from '../hooks/useViewport';
import DesktopShell from './DesktopShell';
import MobileShell from './MobileShell';

export default function AppShell({ children }) {
    const viewport = useViewport();
    const Shell = viewport === 'mobile' ? MobileShell : DesktopShell;
    return <Shell>{children}</Shell>;
}
```

- [ ] **Step 6: `NotificationBell` placeholder so the shells compile (full live version is Task 4)**

```jsx
// resources/js-app/components/NotificationBell.jsx
export default function NotificationBell() {
    return <button aria-label="Notifications">🔔</button>;
}
```

- [ ] **Step 7: Wrap `App.jsx` content in `AppShell`**

```jsx
// resources/js-app/App.jsx
import { Routes, Route } from 'react-router-dom';
import AppShell from './layouts/AppShell';
import DashboardPage from './pages/DashboardPage';

export default function App({ currentUserId }) {
    return (
        <AppShell>
            <Routes>
                <Route path="/app" element={<DashboardPage currentUserId={currentUserId} />} />
                <Route path="*" element={<div>Page not found.</div>} />
            </Routes>
        </AppShell>
    );
}
```

- [ ] **Step 8: Run test to verify it passes**

Run: `npm run test -- AppShell`
Expected: PASS (2 tests). Also run `npm run test` (full suite) to confirm `App.test.jsx` still passes with the shell wrapping.

- [ ] **Step 9: Commit**

```bash
git add resources/js-app/layouts resources/js-app/components/NotificationBell.jsx resources/js-app/App.jsx
git commit -m "feat: add React AppShell with live desktop/mobile switching"
```

---

### Task 3: Backend — `NotificationBroadcast` event replacing 30s polling

**Files:**
- Create: `app/Events/NotificationPushed.php`
- Modify: `routes/channels.php`
- Modify: `app/Http/Controllers/NotificationController.php` (existing controller backing `notifications.unread`/`notifications.read-all` — fire the event wherever a notification is created; if none exists yet, fire from the point notifications are currently generated)
- Test: `tests/Feature/NotificationBroadcastTest.php`

**Interfaces:**
- Consumes: existing `App.Models.User.{id}` private channel (already defined in `routes/channels.php`, already used by `DashboardPinged`).
- Produces: `NotificationPushed` event, broadcasts on `private-App.Models.User.{id}` as `.notification.pushed` with payload `{id, title, body, url, created_at}`.

- [ ] **Step 1: Find where notifications are currently created**

Run: `grep -rn "notifications.unread\|notifications.read-all" routes/web.php app/Http/Controllers`

Read the controller method backing `notifications.unread` to find the model/table notifications are stored in, and find (or add) the single place new notification rows get inserted.

- [ ] **Step 2: Write the failing test**

```php
<?php
// tests/Feature/NotificationBroadcastTest.php

namespace Tests\Feature;

use App\Events\NotificationPushed;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationBroadcastTest extends TestCase
{
    public function test_it_is_a_broadcastable_event_on_the_users_private_channel(): void
    {
        $user = User::factory()->create();

        $event = new NotificationPushed($user->id, 'Test title', 'Test body', '/app');

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame('App.Models.User.'.$user->id, $channels[0]->name);
        $this->assertSame('notification.pushed', $event->broadcastAs());
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=NotificationBroadcastTest`
Expected: FAIL — class `App\Events\NotificationPushed` not found

- [ ] **Step 4: Write the event**

```php
<?php
// app/Events/NotificationPushed.php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationPushed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public ?string $url = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'notification.pushed';
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=NotificationBroadcastTest`
Expected: PASS

- [ ] **Step 6: Fire the event where notifications are created**

Using what Step 1 found, add `event(new NotificationPushed($recipientId, $title, $body, $url));` immediately after the existing notification-creation code (do not change the existing DB-insert logic).

- [ ] **Step 7: Commit**

```bash
git add app/Events/NotificationPushed.php app/Http/Controllers/NotificationController.php tests/Feature/NotificationBroadcastTest.php
git commit -m "feat: broadcast NotificationPushed event for live notification delivery"
```

---

### Task 4: Frontend — live `NotificationBell`

**Files:**
- Modify: `resources/js-app/components/NotificationBell.jsx`
- Create: `resources/js-app/components/NotificationBell.test.jsx`
- Modify: `resources/js-app/App.jsx` (pass `currentUserId` down to `AppShell` → shells → `NotificationBell`, since the bell needs it to open the Echo channel)
- Modify: `resources/js-app/layouts/AppShell.jsx`, `DesktopShell.jsx`, `MobileShell.jsx` (thread `currentUserId` prop through)

**Interfaces:**
- Consumes: `echo` from `resources/js-app/echo.js` (existing), `apiGet` from `resources/js-app/api/client.js` (existing).
- Produces: `<NotificationBell currentUserId={number}>` — fetches unread notifications once on mount via `GET /api/v1/notifications/unread` (reuse existing web route's data source; if no API endpoint exists yet, add a thin `Api/NotificationController@unread` returning the same data as the existing Blade-facing route), then live-patches the list on `.notification.pushed`.

- [ ] **Step 1: Write the failing test**

```jsx
// resources/js-app/components/NotificationBell.test.jsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import NotificationBell from './NotificationBell';
import * as client from '../api/client';

let capturedHandler;
vi.mock('../echo', () => ({
    echo: {
        private: () => ({
            listen: (event, handler) => { capturedHandler = handler; },
        }),
        leave: () => {},
    },
}));

describe('NotificationBell', () => {
    it('shows the unread count fetched on mount', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ notifications: [{ id: 1, title: 'Hello', body: 'World', url: null }] });

        render(<NotificationBell currentUserId={1} />);

        await waitFor(() => expect(screen.getByText('1')).toBeInTheDocument());
    });

    it('adds a live-pushed notification without refetching', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ notifications: [] });

        render(<NotificationBell currentUserId={1} />);
        await waitFor(() => expect(client.apiGet).toHaveBeenCalled());

        fireEvent.click(screen.getByLabelText('Notifications'));
        capturedHandler({ id: 2, title: 'New GRN', body: 'GRN #5 confirmed', url: '/app/purchase/grns/5' });

        await waitFor(() => expect(screen.getByText('New GRN')).toBeInTheDocument());
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- NotificationBell`
Expected: FAIL — current placeholder component has no count/list/click behavior

- [ ] **Step 3: Write the implementation**

```jsx
// resources/js-app/components/NotificationBell.jsx
import { useEffect, useState } from 'react';
import { apiGet } from '../api/client';
import { echo } from '../echo';

export default function NotificationBell({ currentUserId }) {
    const [notifications, setNotifications] = useState([]);
    const [open, setOpen] = useState(false);

    useEffect(() => {
        apiGet('/notifications/unread').then((res) => setNotifications(res.notifications ?? []));
    }, []);

    useEffect(() => {
        if (!currentUserId) return;

        const channel = echo.private(`App.Models.User.${currentUserId}`);
        channel.listen('.notification.pushed', (event) => {
            setNotifications((prev) => [event, ...prev]);
        });

        return () => echo.leave(`App.Models.User.${currentUserId}`);
    }, [currentUserId]);

    return (
        <div style={{ position: 'relative' }}>
            <button aria-label="Notifications" onClick={() => setOpen((v) => !v)}>
                🔔{notifications.length > 0 && <span>{notifications.length}</span>}
            </button>
            {open && (
                <div style={{
                    position: 'absolute', right: 0, top: '100%', width: 280,
                    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 8, zIndex: 50,
                }}>
                    {notifications.length === 0 ? (
                        <div style={{ padding: 24, textAlign: 'center', color: '#94a3b8', fontSize: 13 }}>
                            No new notifications
                        </div>
                    ) : (
                        notifications.map((n) => (
                            <div key={n.id} style={{ padding: 12, borderBottom: '1px solid #f1f5f9' }}>
                                <div style={{ fontWeight: 600, fontSize: 13 }}>{n.title}</div>
                                <div style={{ fontSize: 12, color: '#64748b' }}>{n.body}</div>
                            </div>
                        ))
                    )}
                </div>
            )}
        </div>
    );
}
```

- [ ] **Step 4: Thread `currentUserId` through the shells**

In `AppShell.jsx`, `DesktopShell.jsx`, `MobileShell.jsx`: add `currentUserId` to each component's props and pass it to `<NotificationBell currentUserId={currentUserId} />`. In `App.jsx`, change `<AppShell>` to `<AppShell currentUserId={currentUserId}>`.

- [ ] **Step 5: Add the API endpoint if it doesn't already exist**

If Task 3's Step 1 found only a web (session) route for unread notifications, add:

```php
// routes/api.php, inside the auth:sanctum group
Route::get('notifications/unread', [\App\Http\Controllers\Api\NotificationController::class, 'unread']);
```

```php
<?php
// app/Http/Controllers/Api/NotificationController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function unread(Request $request)
    {
        // Reuse the exact query the existing notifications.unread web route uses
        // (found in Task 3, Step 1) so both surfaces stay in sync.
    }
}
```

Fill in the query body with the same logic the existing `notifications.unread` route uses — do not invent new notification-fetching logic.

- [ ] **Step 6: Run test to verify it passes**

Run: `npm run test -- NotificationBell`
Expected: PASS (2 tests)

- [ ] **Step 7: Commit**

```bash
git add resources/js-app/components/NotificationBell.jsx resources/js-app/components/NotificationBell.test.jsx resources/js-app/layouts resources/js-app/App.jsx app/Http/Controllers/Api/NotificationController.php routes/api.php
git commit -m "feat: live-push notification bell via Reverb, remove 30s polling need"
```

---

### Task 5: Backend — `SupplierSaved` broadcast event + `purchase` channel

**Files:**
- Create: `app/Events/SupplierSaved.php`
- Modify: `routes/channels.php`
- Test: `tests/Feature/SupplierBroadcastTest.php`

**Interfaces:**
- Produces: `SupplierSaved` event, constructed as `new SupplierSaved(Supplier $supplier)`, broadcasts on `private-purchase` as `.supplier.saved` with payload `{id, supplier_code, name, category, is_active}` (via `Supplier`'s existing attributes — no new resource class needed for this small payload).
- Produces: `purchase` channel authorization — any authenticated user (matches existing app-wide auth model; no dedicated "purchase" permission currently gates viewing supplier lists per `routes/web.php`).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/SupplierBroadcastTest.php

namespace Tests\Feature;

use App\Events\SupplierSaved;
use App\Models\Supplier;
use Tests\TestCase;

class SupplierBroadcastTest extends TestCase
{
    public function test_it_broadcasts_on_the_shared_purchase_channel(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Acme Steel']);

        $event = new SupplierSaved($supplier);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame('purchase', $channels[0]->name);
        $this->assertSame('supplier.saved', $event->broadcastAs());
        $this->assertSame('Acme Steel', $event->broadcastWith()['name']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SupplierBroadcastTest`
Expected: FAIL — class `App\Events\SupplierSaved` not found

- [ ] **Step 3: Write the event**

```php
<?php
// app/Events/SupplierSaved.php

namespace App\Events;

use App\Models\Supplier;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupplierSaved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Supplier $supplier) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('purchase')];
    }

    public function broadcastAs(): string
    {
        return 'supplier.saved';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->supplier->id,
            'supplier_code' => $this->supplier->supplier_code,
            'name' => $this->supplier->name,
            'category' => $this->supplier->category,
            'is_active' => (bool) $this->supplier->is_active,
        ];
    }
}
```

- [ ] **Step 4: Authorize the `purchase` channel**

```php
// routes/channels.php — add below the existing App.Models.User.{id} channel
Broadcast::channel('purchase', function (User $user) {
    return true; // any authenticated user; matches existing purchase.suppliers.index route (auth+verified only, no extra gate)
});
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=SupplierBroadcastTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Events/SupplierSaved.php routes/channels.php tests/Feature/SupplierBroadcastTest.php
git commit -m "feat: add SupplierSaved broadcast event on shared purchase channel"
```

---

### Task 6: Backend — `Api/Purchase/SupplierController`

**Files:**
- Create: `app/Http/Controllers/Api/Purchase/SupplierController.php`
- Create: `app/Http/Resources/SupplierResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/Purchase/SupplierControllerTest.php`

**Interfaces:**
- Consumes: `SupplierSaved` event from Task 5.
- Produces: `GET /api/v1/purchase/suppliers` → `{data: SupplierResource[]}`; `POST /api/v1/purchase/suppliers` → `{data: SupplierResource}` (201) or `{message, errors}` (422); `PUT /api/v1/purchase/suppliers/{supplier}` → `{data: SupplierResource}` (200) or 422. Both writes fire `SupplierSaved`.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Api/Purchase/SupplierControllerTest.php

namespace Tests\Feature\Api\Purchase;

use App\Events\SupplierSaved;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SupplierControllerTest extends TestCase
{
    private function actingUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        return $user;
    }

    public function test_index_returns_all_suppliers(): void
    {
        $this->actingUser();
        Supplier::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/purchase/suppliers');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_store_creates_a_supplier_and_broadcasts(): void
    {
        Event::fake([SupplierSaved::class]);
        $this->actingUser();

        $response = $this->postJson('/api/v1/purchase/suppliers', [
            'name' => 'Acme Steel',
            'credit_days' => 30,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('suppliers', ['name' => 'Acme Steel']);
        Event::assertDispatched(SupplierSaved::class);
    }

    public function test_store_requires_a_name(): void
    {
        $this->actingUser();

        $response = $this->postJson('/api/v1/purchase/suppliers', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_update_modifies_a_supplier_and_broadcasts(): void
    {
        Event::fake([SupplierSaved::class]);
        $this->actingUser();
        $supplier = Supplier::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson("/api/v1/purchase/suppliers/{$supplier->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'New Name']);
        Event::assertDispatched(SupplierSaved::class);
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/purchase/suppliers');

        $response->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=SupplierControllerTest`
Expected: FAIL — route `/api/v1/purchase/suppliers` not found (404)

- [ ] **Step 3: Write `SupplierResource`**

```php
<?php
// app/Http/Resources/SupplierResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
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
            'address' => $this->address,
            'credit_days' => $this->credit_days,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

```php
<?php
// app/Http/Controllers/Api/Purchase/SupplierController.php

namespace App\Http\Controllers\Api\Purchase;

use App\Events\SupplierSaved;
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'supplier_code' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'credit_days' => 'nullable|integer|min:0',
        ]);

        $supplier = Supplier::create(array_merge($data, [
            'is_active' => (bool) $request->input('is_active', true),
        ]));

        event(new SupplierSaved($supplier));

        return (new SupplierResource($supplier))->response()->setStatusCode(201);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'supplier_code' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'credit_days' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $supplier->update($data);

        event(new SupplierSaved($supplier));

        return new SupplierResource($supplier);
    }
}
```

- [ ] **Step 5: Register the routes**

```php
// routes/api.php — inside the auth:sanctum group
Route::prefix('purchase')->group(function () {
    Route::get('suppliers', [\App\Http\Controllers\Api\Purchase\SupplierController::class, 'index']);
    Route::post('suppliers', [\App\Http\Controllers\Api\Purchase\SupplierController::class, 'store']);
    Route::put('suppliers/{supplier}', [\App\Http\Controllers\Api\Purchase\SupplierController::class, 'update']);
});
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=SupplierControllerTest`
Expected: PASS (5 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/Purchase/SupplierController.php app/Http/Resources/SupplierResource.php routes/api.php tests/Feature/Api/Purchase/SupplierControllerTest.php
git commit -m "feat: add Api/Purchase/SupplierController with SupplierSaved broadcasting"
```

---

### Task 7: Frontend — desktop Supplier pages (list + create/edit)

**Files:**
- Create: `resources/js-app/pages/desktop/purchase/SupplierListPage.jsx`
- Create: `resources/js-app/pages/desktop/purchase/SupplierListPage.test.jsx`
- Create: `resources/js-app/components/purchase/supplier/SupplierForm.jsx`
- Create: `resources/js-app/components/purchase/supplier/SupplierForm.test.jsx`
- Modify: `resources/js-app/App.jsx` (add route)

**Interfaces:**
- Consumes: `apiGet`/`apiPost`/`apiPut` (`api/client.js`), `Table`/`Modal`/`FormField`/`useToast` (existing `components/ui/*`), `echo` (`echo.js`).
- Produces: `<SupplierListPage />` — no props, self-fetching. `<SupplierForm supplier={supplierOrNull} onSaved={(supplier) => void} onCancel={() => void} />` — used for both create (`supplier=null`) and edit.

- [ ] **Step 1: Write the failing `SupplierForm` test**

```jsx
// resources/js-app/components/purchase/supplier/SupplierForm.test.jsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import SupplierForm from './SupplierForm';
import * as client from '../../../api/client';

describe('SupplierForm', () => {
    it('requires a name before submitting', async () => {
        const onSaved = vi.fn();
        render(<SupplierForm supplier={null} onSaved={onSaved} onCancel={() => {}} />);

        fireEvent.click(screen.getByText('Save'));

        await waitFor(() => expect(screen.getByText(/name is required/i)).toBeInTheDocument());
        expect(onSaved).not.toHaveBeenCalled();
    });

    it('posts a new supplier and calls onSaved', async () => {
        const created = { id: 5, name: 'Acme Steel' };
        vi.spyOn(client, 'apiPost').mockResolvedValue({ data: created });
        const onSaved = vi.fn();

        render(<SupplierForm supplier={null} onSaved={onSaved} onCancel={() => {}} />);
        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Acme Steel' } });
        fireEvent.click(screen.getByText('Save'));

        await waitFor(() => expect(onSaved).toHaveBeenCalledWith(created));
        expect(client.apiPost).toHaveBeenCalledWith('/purchase/suppliers', expect.objectContaining({ name: 'Acme Steel' }));
    });

    it('puts an edit for an existing supplier', async () => {
        const updated = { id: 5, name: 'Acme Renamed' };
        vi.spyOn(client, 'apiPut').mockResolvedValue({ data: updated });
        const onSaved = vi.fn();

        render(<SupplierForm supplier={{ id: 5, name: 'Acme Steel' }} onSaved={onSaved} onCancel={() => {}} />);
        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Acme Renamed' } });
        fireEvent.click(screen.getByText('Save'));

        await waitFor(() => expect(onSaved).toHaveBeenCalledWith(updated));
        expect(client.apiPut).toHaveBeenCalledWith('/purchase/suppliers/5', expect.objectContaining({ name: 'Acme Renamed' }));
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- SupplierForm`
Expected: FAIL — `Cannot find module './SupplierForm'`

- [ ] **Step 3: Write `SupplierForm.jsx`**

```jsx
// resources/js-app/components/purchase/supplier/SupplierForm.jsx
import { useState } from 'react';
import FormField from '../../ui/FormField';
import { apiPost, apiPut } from '../../../api/client';

const BLANK = { name: '', supplier_code: '', category: '', contact_person: '', email: '', phone: '', whatsapp_number: '', address: '', credit_days: '' };

export default function SupplierForm({ supplier, onSaved, onCancel }) {
    const [values, setValues] = useState({ ...BLANK, ...supplier });
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    function handleChange(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    async function handleSave() {
        if (!values.name.trim()) {
            setErrors({ name: 'Name is required.' });
            return;
        }
        setErrors({});
        setSaving(true);
        try {
            const response = supplier
                ? await apiPut(`/purchase/suppliers/${supplier.id}`, values)
                : await apiPost('/purchase/suppliers', values);
            onSaved(response.data);
        } catch (err) {
            setErrors(err.errors ? Object.fromEntries(Object.entries(err.errors).map(([k, v]) => [k, v[0]])) : {});
        } finally {
            setSaving(false);
        }
    }

    return (
        <div>
            <FormField label="Name" name="name" value={values.name} onChange={handleChange} error={errors.name} />
            <FormField label="Supplier Code" name="supplier_code" value={values.supplier_code} onChange={handleChange} />
            <FormField label="Category" name="category" value={values.category} onChange={handleChange} />
            <FormField label="Contact Person" name="contact_person" value={values.contact_person} onChange={handleChange} />
            <FormField label="Email" name="email" value={values.email} onChange={handleChange} type="email" />
            <FormField label="Phone" name="phone" value={values.phone} onChange={handleChange} />
            <FormField label="WhatsApp Number" name="whatsapp_number" value={values.whatsapp_number} onChange={handleChange} />
            <FormField label="Address" name="address" value={values.address} onChange={handleChange} type="textarea" />
            <FormField label="Credit Days" name="credit_days" value={values.credit_days} onChange={handleChange} type="number" />
            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end', marginTop: 16 }}>
                <button onClick={onCancel} disabled={saving}>Cancel</button>
                <button onClick={handleSave} disabled={saving}>Save</button>
            </div>
        </div>
    );
}
```

- [ ] **Step 4: Run `SupplierForm` test to verify it passes**

Run: `npm run test -- SupplierForm`
Expected: PASS (3 tests)

- [ ] **Step 5: Write the failing `SupplierListPage` test**

```jsx
// resources/js-app/pages/desktop/purchase/SupplierListPage.test.jsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { ToastProvider } from '../../../components/ui/Toast';
import SupplierListPage from './SupplierListPage';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => {} }), leave: () => {} },
}));

describe('SupplierListPage', () => {
    it('loads and displays suppliers', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [{ id: 1, name: 'Acme Steel', category: 'Raw Material', is_active: true }] });

        render(<ToastProvider><SupplierListPage /></ToastProvider>);

        await waitFor(() => expect(screen.getByText('Acme Steel')).toBeInTheDocument());
    });

    it('opens the create modal and adds the new supplier to the list on save', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });
        vi.spyOn(client, 'apiPost').mockResolvedValue({ data: { id: 9, name: 'New Supplier', category: null, is_active: true } });

        render(<ToastProvider><SupplierListPage /></ToastProvider>);
        await waitFor(() => expect(client.apiGet).toHaveBeenCalled());

        fireEvent.click(screen.getByText('New Supplier'));
        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'New Supplier' } });
        fireEvent.click(screen.getByText('Save'));

        await waitFor(() => expect(screen.getByText('New Supplier')).toBeInTheDocument());
    });
});
```

- [ ] **Step 6: Run test to verify it fails**

Run: `npm run test -- SupplierListPage`
Expected: FAIL — `Cannot find module './SupplierListPage'`

- [ ] **Step 7: Write `SupplierListPage.jsx`**

```jsx
// resources/js-app/pages/desktop/purchase/SupplierListPage.jsx
import { useEffect, useState } from 'react';
import Card from '../../../components/ui/Card';
import Table from '../../../components/ui/Table';
import Modal from '../../../components/ui/Modal';
import SupplierForm from '../../../components/purchase/supplier/SupplierForm';
import { apiGet } from '../../../api/client';
import { echo } from '../../../echo';
import { useToast } from '../../../components/ui/Toast';

const COLUMNS = [
    { key: 'name', label: 'Name' },
    { key: 'category', label: 'Category' },
    { key: 'is_active', label: 'Active', render: (row) => (row.is_active ? 'Yes' : 'No') },
];

export default function SupplierListPage() {
    const [suppliers, setSuppliers] = useState([]);
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const { showToast } = useToast();

    useEffect(() => {
        apiGet('/purchase/suppliers').then((res) => setSuppliers(res.data));
    }, []);

    useEffect(() => {
        const channel = echo.private('purchase');
        channel.listen('.supplier.saved', (event) => {
            setSuppliers((prev) => {
                const exists = prev.some((s) => s.id === event.id);
                return exists ? prev.map((s) => (s.id === event.id ? { ...s, ...event } : s)) : [...prev, event];
            });
        });
        return () => echo.leave('purchase');
    }, []);

    function openCreate() {
        setEditing(null);
        setModalOpen(true);
    }

    function openEdit(supplier) {
        setEditing(supplier);
        setModalOpen(true);
    }

    function handleSaved(supplier) {
        setSuppliers((prev) => {
            const exists = prev.some((s) => s.id === supplier.id);
            return exists ? prev.map((s) => (s.id === supplier.id ? supplier : s)) : [...prev, supplier];
        });
        setModalOpen(false);
        showToast('Supplier saved.', 'success');
    }

    const columnsWithActions = [
        ...COLUMNS,
        { key: 'actions', label: '', render: (row) => <button onClick={() => openEdit(row)}>Edit</button> },
    ];

    return (
        <Card title="Suppliers">
            <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 12 }}>
                <button onClick={openCreate}>New Supplier</button>
            </div>
            <Table columns={columnsWithActions} rows={suppliers} rowKey={(row) => row.id} searchPlaceholder="Search suppliers…" />
            <Modal open={modalOpen} title={editing ? 'Edit Supplier' : 'New Supplier'} onClose={() => setModalOpen(false)}>
                <SupplierForm supplier={editing} onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
        </Card>
    );
}
```

- [ ] **Step 8: Run test to verify it passes**

Run: `npm run test -- SupplierListPage`
Expected: PASS (2 tests)

- [ ] **Step 9: Add the route in `App.jsx`**

```jsx
// resources/js-app/App.jsx
import { Routes, Route } from 'react-router-dom';
import AppShell from './layouts/AppShell';
import DashboardPage from './pages/DashboardPage';
import SupplierListPage from './pages/desktop/purchase/SupplierListPage';

export default function App({ currentUserId }) {
    return (
        <AppShell currentUserId={currentUserId}>
            <Routes>
                <Route path="/app" element={<DashboardPage currentUserId={currentUserId} />} />
                <Route path="/app/purchase/suppliers" element={<SupplierListPage />} />
                <Route path="*" element={<div>Page not found.</div>} />
            </Routes>
        </AppShell>
    );
}
```

(Task 8 replaces this with a viewport-aware route element once the mobile page exists.)

- [ ] **Step 10: Commit**

```bash
git add resources/js-app/pages/desktop/purchase resources/js-app/components/purchase/supplier resources/js-app/App.jsx
git commit -m "feat: add desktop Supplier list/create/edit React pages with live updates"
```

---

### Task 8: Frontend — mobile Supplier list page + viewport-aware routing

**Files:**
- Create: `resources/js-app/pages/mobile/purchase/SupplierListPage.jsx`
- Create: `resources/js-app/pages/mobile/purchase/SupplierListPage.test.jsx`
- Modify: `resources/js-app/App.jsx`

**Interfaces:**
- Consumes: `useViewport()` (Task 1), `SupplierForm` (Task 7, shared across both viewports — the form itself doesn't need a mobile variant since it's already a single-column stacked layout).
- Produces: `<SupplierListPage />` (mobile) — same data contract as the desktop version, but renders cards instead of a table.

- [ ] **Step 1: Write the failing test**

```jsx
// resources/js-app/pages/mobile/purchase/SupplierListPage.test.jsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { ToastProvider } from '../../../components/ui/Toast';
import SupplierListPage from './SupplierListPage';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => {} }), leave: () => {} },
}));

describe('SupplierListPage (mobile)', () => {
    it('renders suppliers as cards, not a table', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [{ id: 1, name: 'Acme Steel', category: 'Raw Material', is_active: true }] });

        render(<ToastProvider><SupplierListPage /></ToastProvider>);

        await waitFor(() => expect(screen.getByText('Acme Steel')).toBeInTheDocument());
        expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- pages/mobile/purchase/SupplierListPage`
Expected: FAIL — `Cannot find module './SupplierListPage'`

- [ ] **Step 3: Write the mobile page**

```jsx
// resources/js-app/pages/mobile/purchase/SupplierListPage.jsx
import { useEffect, useState } from 'react';
import Modal from '../../../components/ui/Modal';
import SupplierForm from '../../../components/purchase/supplier/SupplierForm';
import { apiGet } from '../../../api/client';
import { echo } from '../../../echo';
import { useToast } from '../../../components/ui/Toast';

export default function SupplierListPage() {
    const [suppliers, setSuppliers] = useState([]);
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const { showToast } = useToast();

    useEffect(() => {
        apiGet('/purchase/suppliers').then((res) => setSuppliers(res.data));
    }, []);

    useEffect(() => {
        const channel = echo.private('purchase');
        channel.listen('.supplier.saved', (event) => {
            setSuppliers((prev) => {
                const exists = prev.some((s) => s.id === event.id);
                return exists ? prev.map((s) => (s.id === event.id ? { ...s, ...event } : s)) : [...prev, event];
            });
        });
        return () => echo.leave('purchase');
    }, []);

    function handleSaved(supplier) {
        setSuppliers((prev) => {
            const exists = prev.some((s) => s.id === supplier.id);
            return exists ? prev.map((s) => (s.id === supplier.id ? supplier : s)) : [...prev, supplier];
        });
        setModalOpen(false);
        showToast('Supplier saved.', 'success');
    }

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <h1 style={{ fontSize: 18, fontWeight: 700 }}>Suppliers</h1>
                <button onClick={() => { setEditing(null); setModalOpen(true); }}>New</button>
            </div>
            {suppliers.map((supplier) => (
                <div
                    key={supplier.id}
                    onClick={() => { setEditing(supplier); setModalOpen(true); }}
                    style={{ border: '1px solid #e2e8f0', borderRadius: 8, padding: 12, marginBottom: 8 }}
                >
                    <div style={{ fontWeight: 600 }}>{supplier.name}</div>
                    <div style={{ fontSize: 13, color: '#64748b' }}>{supplier.category ?? '—'}</div>
                    <div style={{ fontSize: 12, color: supplier.is_active ? '#16a34a' : '#dc2626' }}>
                        {supplier.is_active ? 'Active' : 'Inactive'}
                    </div>
                </div>
            ))}
            <Modal open={modalOpen} title={editing ? 'Edit Supplier' : 'New Supplier'} onClose={() => setModalOpen(false)}>
                <SupplierForm supplier={editing} onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
        </div>
    );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test -- pages/mobile/purchase/SupplierListPage`
Expected: PASS

- [ ] **Step 5: Wire viewport-aware routing in `App.jsx`**

```jsx
// resources/js-app/App.jsx
import { Routes, Route } from 'react-router-dom';
import AppShell from './layouts/AppShell';
import useViewport from './hooks/useViewport';
import DashboardPage from './pages/DashboardPage';
import DesktopSupplierListPage from './pages/desktop/purchase/SupplierListPage';
import MobileSupplierListPage from './pages/mobile/purchase/SupplierListPage';

export default function App({ currentUserId }) {
    const viewport = useViewport();
    const SupplierListPage = viewport === 'mobile' ? MobileSupplierListPage : DesktopSupplierListPage;

    return (
        <AppShell currentUserId={currentUserId}>
            <Routes>
                <Route path="/app" element={<DashboardPage currentUserId={currentUserId} />} />
                <Route path="/app/purchase/suppliers" element={<SupplierListPage />} />
                <Route path="*" element={<div>Page not found.</div>} />
            </Routes>
        </AppShell>
    );
}
```

- [ ] **Step 6: Run the full frontend suite**

Run: `npm run test`
Expected: PASS — all existing + new tests.

- [ ] **Step 7: Commit**

```bash
git add resources/js-app/pages/mobile/purchase resources/js-app/App.jsx
git commit -m "feat: add mobile Supplier list page with live viewport-aware routing"
```

---

### Task 9: Cutover — route + nav wiring, delete old Blade Supplier page

**Files:**
- Modify: `routes/web.php` (add `/app/purchase/suppliers` catch-all mounting `app-shell.blade.php`; remove `purchase.suppliers.*` web routes and the `suppliers/import`/`suppliers/template`/`suppliers/export-pdf` routes)
- Modify: `resources/views/layouts/app.blade.php` (Suppliers nav link now points to `/app/purchase/suppliers`, a full navigation — not an SPA link, since the Blade shell doesn't run React)
- Delete: `app/Http/Controllers/Purchase/SupplierController.php`
- Delete: `resources/views/purchase/suppliers/` (entire directory: index, create, edit, pdf)
- Delete: `tests/Feature/Purchase/SupplierControllerTest.php` (old Blade-page test, if present — check `find tests -iname "*Supplier*"` first; keep any test unrelated to the deleted Blade controller, e.g. `SupplierImportService` tests, which are backend logic reused by nothing UI-specific)
- Test: `tests/Feature/AppShellRouteTest.php`

**Interfaces:**
- Produces: `GET /app/purchase/suppliers` → renders `app-shell.blade.php` (same view Dashboard already uses at `/app`), auth-protected identically to the old route.

- [ ] **Step 1: Inventory what currently exists for Suppliers**

Run: `grep -n "suppliers" routes/web.php` and `find tests -iname "*Supplier*"` and `find app/Services -iname "*Supplier*"` to confirm exactly what to remove vs. keep (keep `SupplierImportService.php` and its tests — they're reused by the `suppliers:import` artisan command, not by the deleted controller).

- [ ] **Step 2: Write the failing route test**

```php
<?php
// tests/Feature/AppShellRouteTest.php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AppShellRouteTest extends TestCase
{
    public function test_purchase_suppliers_react_route_renders_the_app_shell(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/app/purchase/suppliers');

        $response->assertOk();
        $response->assertViewIs('app-shell');
    }

    public function test_purchase_suppliers_route_requires_auth(): void
    {
        $response = $this->get('/app/purchase/suppliers');

        $response->assertRedirect('/login');
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=AppShellRouteTest`
Expected: FAIL — 404, route not defined

- [ ] **Step 4: Add the route, remove the old Blade Supplier routes**

In `routes/web.php`, inside the existing `prefix('purchase')` group, delete the `suppliers` resource route and its `import`/`template`/`export-pdf` custom routes. Inside the existing `/app` group (the one already serving Dashboard), add:

```php
Route::get('/app/purchase/suppliers', function () {
    return view('app-shell');
})->middleware(['auth', 'verified']);
```

(Match whatever route-group structure the existing `/app` dashboard route already uses — same middleware, same naming convention — rather than introducing a new pattern.)

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=AppShellRouteTest`
Expected: PASS

- [ ] **Step 6: Update the Blade sidebar nav link**

In `resources/views/layouts/app.blade.php`, find the existing Suppliers `<a>` link (pointing at `route('purchase.suppliers.index')`) and change its `href` to `/app/purchase/suppliers`, keeping its existing styling/active-state logic (active-state check can key off `request()->is('app/purchase/suppliers')` instead of `request()->routeIs(...)` since the named route is being removed).

- [ ] **Step 7: Delete the old Blade controller, views, and superseded tests**

```bash
git rm app/Http/Controllers/Purchase/SupplierController.php
git rm -r resources/views/purchase/suppliers
# Only if Step 1 found a test file exercising the deleted Blade controller's routes/views:
git rm tests/Feature/Purchase/SupplierControllerTest.php
```

- [ ] **Step 8: Run the full backend and frontend suites**

Run: `php artisan test` — expect all passing except any pre-existing unrelated failures noted in git history (confirm count against the last known-good baseline).
Run: `npm run test` — expect all passing.
Run: `npm run build` — expect a clean Vite build (confirms no dangling imports of deleted files).

- [ ] **Step 9: Manual verification**

Run: `npm run dev` and `php artisan serve`, then in a browser:
- Log in, confirm Dashboard still loads at `/app`.
- Click "Suppliers" in the sidebar, confirm the React Supplier list loads at `/app/purchase/suppliers`, shows existing suppliers, and "New Supplier" works end-to-end.
- Resize the browser window below 768px width without reloading — confirm the layout instantly switches to the mobile card view and the mobile hamburger menu appears.
- Open a second browser session (or incognito window) logged in as a different user, create a supplier in one window, confirm it appears live (no refresh) in the other window's list — proves the Reverb broadcast path works end-to-end (requires `php artisan reverb:start` running locally; confirm it's running via `.env`'s `BROADCAST_CONNECTION=reverb`).

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: cut over Suppliers to React, delete old Blade Supplier page

Full replacement per CLAUDE.md gotcha #12 — no side-by-side coexistence.
Supplier CRUD, list, and live updates now live at /app/purchase/suppliers.
Old purchase.suppliers.* Blade routes/controller/views removed."
```

---

## What's Next

This plan delivers the app shell foundation (live desktop/mobile switching, live notifications) and the full Supplier vertical slice (backend event + API + desktop page + mobile page + live updates + cutover). The remaining Purchase entities — PurchaseRequest, PurchaseOrder, GRN, SupplierInvoice, SupplierPayment — each get their own follow-up plan reusing this exact pattern (broadcast event → API controller → desktop page → mobile page → cutover), plus any entity-specific workflow steps (PR approve/reject, GRN confirm) as extra tasks within that entity's plan.
