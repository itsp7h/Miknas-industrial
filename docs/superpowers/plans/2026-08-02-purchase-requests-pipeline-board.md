# Purchase Requests Pipeline Board Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert the read-only Purchase Requests pipeline board (`/purchase/pipeline`) to a live-updating React page (desktop + mobile), while the per-request detail/RFQ/quotes/signature/LPO workflow stays on its existing Blade page.

**Architecture:** New `PurchaseRequestStageChanged`/`PurchaseRequestCreated` broadcast events on the `purchase` channel (mirroring `SupplierSaved`), a read-only `Api/Purchase/PurchasePipelineController@index` reusing the existing Blade controller's permission-filtered query, and desktop/mobile React board pages using the existing `useLiveList` hook. The existing `<x-purchase.request-modal />` creation flow is embedded unchanged in `app-shell.blade.php`, triggered via `window.mprModalOpen()` from the React page — not ported to React in this pass.

**Tech Stack:** Laravel 12 (Reverb), React 19, Vitest, PHPUnit.

## Global Constraints

- The per-request detail page, RFQ, supplier quotes, signature, and LPO generation stay Blade — out of scope. — design spec `2026-08-02-purchase-requests-pipeline-board-design.md`
- The API endpoint must reuse `PurchasePipelineController::withRelations()`'s exact permission-filtering logic (view-all / view-active-pipeline / view-own), not reimplement it. — same spec
- `PurchaseRequestStageChanged` must fire from `PurchaseStageService` itself (the one place `advance`/`setStage`/`setStageIfNotPast` change a stage), not from individual call sites, so nothing can bypass the broadcast. — same spec
- Clicking a board row navigates via a real `<a href="...">` to the Blade detail page, not a React route. — same spec
- Use `ShouldBroadcastNow` for both new events, matching the established pattern (queued broadcasting silently never delivers without a running worker). — established in prior phases

---

### Task 1: Backend — broadcast events + read-only pipeline API

**Files:**
- Create: `app/Events/PurchaseRequestStageChanged.php`
- Create: `app/Events/PurchaseRequestCreated.php`
- Modify: `app/Services/PurchaseStageService.php`
- Modify: `app/Http/Controllers/Purchase/PurchaseRequestController.php` (fire `PurchaseRequestCreated` from `store()`)
- Create: `app/Http/Controllers/Api/Purchase/PurchasePipelineController.php`
- Create: `app/Http/Resources/PurchaseRequestBoardResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/PurchaseRequestStageChangedBroadcastTest.php`
- Test: `tests/Feature/PurchaseRequestCreatedBroadcastTest.php`
- Test: `tests/Feature/Api/Purchase/PurchasePipelineControllerTest.php`

**Interfaces:**
- Produces: `PurchaseRequestStageChanged{id, request_number, stage}` on `private-purchase` as `.purchase-request.stage-changed`.
- Produces: `PurchaseRequestCreated{id, request_number, date, project_name, requested_by_name, department, stage}` on `private-purchase` as `.purchase-request.created`.
- Produces: `GET /api/v1/purchase/pipeline` → `{data: [PurchaseRequestBoardResource]}` (flat list, both active and completed — the frontend splits by `stage === 'complete'` client-side), filtered by the same permission rules as the Blade board.

- [ ] **Step 1: Read `PurchaseStageService` and `PurchasePipelineController::withRelations()` first**

Before writing any code, read both files in full (`app/Services/PurchaseStageService.php`, `app/Http/Controllers/Purchase/PurchasePipelineController.php`) to confirm the exact method signatures (`advance`, `setStage`, `setStageIfNotPast`) and the exact permission-filtering logic (`purchase-requests.view-all` / `view-active-pipeline` / `view-own`) you must reuse, not reimplement.

- [ ] **Step 2: Write the failing broadcast event tests**

```php
<?php
// tests/Feature/PurchaseRequestStageChangedBroadcastTest.php

namespace Tests\Feature;

use App\Events\PurchaseRequestStageChanged;
use App\Models\PurchaseRequest;
use App\Services\PurchaseStageService;
use Tests\TestCase;

class PurchaseRequestStageChangedBroadcastTest extends TestCase
{
    public function test_advancing_a_stage_broadcasts_the_change(): void
    {
        \Illuminate\Support\Facades\Event::fake([PurchaseRequestStageChanged::class]);
        $pr = PurchaseRequest::factory()->create(['stage' => 'draft']);

        app(PurchaseStageService::class)->advance($pr);

        \Illuminate\Support\Facades\Event::assertDispatched(
            PurchaseRequestStageChanged::class,
            fn ($e) => $e->purchaseRequestId === $pr->id && $e->stage === 'gm_approval'
        );
    }

    public function test_event_broadcasts_on_the_shared_purchase_channel(): void
    {
        $event = new PurchaseRequestStageChanged(1, 'MPR26-0001', 'rfq');

        $channels = $event->broadcastOn();

        $this->assertSame('private-purchase', $channels[0]->name);
        $this->assertSame('purchase-request.stage-changed', $event->broadcastAs());
        $this->assertSame(['id' => 1, 'request_number' => 'MPR26-0001', 'stage' => 'rfq'], $event->broadcastWith());
    }
}
```

```php
<?php
// tests/Feature/PurchaseRequestCreatedBroadcastTest.php

namespace Tests\Feature;

use App\Events\PurchaseRequestCreated;
use App\Models\User;
use Tests\TestCase;

class PurchaseRequestCreatedBroadcastTest extends TestCase
{
    public function test_submitting_a_request_broadcasts_it(): void
    {
        \Illuminate\Support\Facades\Event::fake([PurchaseRequestCreated::class]);
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('purchase.requests.store'), [
            'date' => now()->toDateString(),
            'project_name' => 'Test Project',
            'requested_by_name' => $user->name,
            'items' => [
                ['description' => 'Steel bars', 'quantity_required' => 10],
            ],
        ]);

        \Illuminate\Support\Facades\Event::assertDispatched(PurchaseRequestCreated::class);
    }

    public function test_event_broadcasts_on_the_shared_purchase_channel(): void
    {
        $event = new PurchaseRequestCreated(1, 'MPR26-0001', '2026-08-02', 'Test Project', 'Jane', 'Ops', 'draft');

        $channels = $event->broadcastOn();

        $this->assertSame('private-purchase', $channels[0]->name);
        $this->assertSame('purchase-request.created', $event->broadcastAs());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PurchaseRequestStageChangedBroadcastTest`
Run: `php artisan test --filter=PurchaseRequestCreatedBroadcastTest`
Expected: FAIL — event classes not found

- [ ] **Step 3: Write the two events**

```php
<?php
// app/Events/PurchaseRequestStageChanged.php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class PurchaseRequestStageChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $purchaseRequestId,
        public string $requestNumber,
        public string $stage,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('purchase')];
    }

    public function broadcastAs(): string
    {
        return 'purchase-request.stage-changed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->purchaseRequestId,
            'request_number' => $this->requestNumber,
            'stage' => $this->stage,
        ];
    }
}
```

```php
<?php
// app/Events/PurchaseRequestCreated.php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class PurchaseRequestCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $purchaseRequestId,
        public string $requestNumber,
        public string $date,
        public ?string $projectName,
        public ?string $requestedByName,
        public ?string $department,
        public string $stage,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('purchase')];
    }

    public function broadcastAs(): string
    {
        return 'purchase-request.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->purchaseRequestId,
            'request_number' => $this->requestNumber,
            'date' => $this->date,
            'project_name' => $this->projectName,
            'requested_by_name' => $this->requestedByName,
            'department' => $this->department,
            'stage' => $this->stage,
        ];
    }
}
```

- [ ] **Step 4: Fire `PurchaseRequestStageChanged` from `PurchaseStageService`**

In each of `advance()`, `setStage()`, and `setStageIfNotPast()` (wherever they actually call `$request->update(['stage' => ...])` — check the real current code from Step 1, this is illustrative), add `event(new PurchaseRequestStageChanged($request->id, $request->request_number, $newStage));` right after the update. If multiple methods share the same underlying update call internally, fire it from that single shared point instead of duplicating in each public method — check the actual structure and use your judgement, but every path that changes `stage` must fire the event exactly once.

- [ ] **Step 5: Fire `PurchaseRequestCreated` from `PurchaseRequestController::store()`**

After the `DB::transaction` block that creates `$pr` and its items (see the existing `store()` method), add:

```php
event(new \App\Events\PurchaseRequestCreated(
    $pr->id, $pr->request_number, $pr->date, $pr->project_name,
    $pr->requested_by_name, $pr->department, $pr->stage
));
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=PurchaseRequestStageChangedBroadcastTest`
Run: `php artisan test --filter=PurchaseRequestCreatedBroadcastTest`

- [ ] **Step 7: Write the failing API controller test**

```php
<?php
// tests/Feature/Api/Purchase/PurchasePipelineControllerTest.php

namespace Tests\Feature\Api\Purchase;

use App\Models\PurchaseRequest;
use App\Models\User;
use Tests\TestCase;

class PurchasePipelineControllerTest extends TestCase
{
    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/purchase/pipeline');

        $response->assertUnauthorized();
    }

    public function test_index_returns_requests_the_user_can_view(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('purchase-requests.view-all'); // check the actual permission-granting mechanism used elsewhere in this codebase's tests (Spatie permissions) — adapt if different
        $this->actingAs($user);
        PurchaseRequest::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/purchase/pipeline');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_filters_to_own_requests_for_view_own_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('purchase-requests.view-own');
        $this->actingAs($user);
        PurchaseRequest::factory()->create(['requested_by' => $user->id]);
        PurchaseRequest::factory()->create(['requested_by' => User::factory()->create()->id]);

        $response = $this->getJson('/api/v1/purchase/pipeline');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
```

Check first how existing tests in this codebase grant permissions to a test user (grep for `givePermissionTo` in `tests/Feature`) and match that exact pattern — the example above may not match the real mechanism.

- [ ] **Step 8: Run test to verify it fails**

Run: `php artisan test --filter=PurchasePipelineControllerTest`
Expected: FAIL — route not found

- [ ] **Step 9: Write `PurchaseRequestBoardResource`**

```php
<?php
// app/Http/Resources/PurchaseRequestBoardResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseRequestBoardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'date' => $this->date,
            'project_name' => $this->project_name,
            'department' => $this->department,
            'requested_by_name' => $this->requested_by_name ?? $this->requestedBy?->name,
            'stage' => $this->stage,
            'remarks' => $this->remarks,
        ];
    }
}
```

- [ ] **Step 10: Write the API controller, reusing the existing permission-filter logic exactly**

```php
<?php
// app/Http/Controllers/Api/Purchase/PurchasePipelineController.php

namespace App\Http\Controllers\Api\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseRequestBoardResource;
use App\Models\PurchaseRequest;
use App\Policies\PurchaseRequestPolicy;

class PurchasePipelineController extends Controller
{
    public function index()
    {
        $query = PurchaseRequest::with('requestedBy');
        $user = auth()->user();

        if (! $user->can('purchase-requests.view-all')) {
            if ($user->can('purchase-requests.view-active-pipeline')) {
                $query->whereIn('stage', PurchaseRequestPolicy::ACTIVE_PIPELINE_STAGES);
            } elseif ($user->can('purchase-requests.view-own')) {
                $query->where('requested_by', $user->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return PurchaseRequestBoardResource::collection($query->latest()->get());
    }
}
```

This mirrors `Purchase\PurchasePipelineController::withRelations()` exactly (same permission checks, same stage constant) but loads only `requestedBy` (not the heavier `signature`/`rfqInvitations`/`supplierQuotes` relations the Blade detail page needs) since the board doesn't render them.

- [ ] **Step 11: Register the route**

```php
// routes/api.php — inside the existing purchase prefix group
Route::get('pipeline', [\App\Http\Controllers\Api\Purchase\PurchasePipelineController::class, 'index']);
```

- [ ] **Step 12: Run tests to verify they pass**

Run: `php artisan test --filter=PurchasePipelineControllerTest`
Run: `php artisan test` (full suite, confirm no regressions)

- [ ] **Step 13: Commit**

```bash
git add app/Events/PurchaseRequestStageChanged.php app/Events/PurchaseRequestCreated.php app/Services/PurchaseStageService.php app/Http/Controllers/Purchase/PurchaseRequestController.php app/Http/Controllers/Api/Purchase/PurchasePipelineController.php app/Http/Resources/PurchaseRequestBoardResource.php routes/api.php tests/Feature/PurchaseRequestStageChangedBroadcastTest.php tests/Feature/PurchaseRequestCreatedBroadcastTest.php tests/Feature/Api/Purchase/PurchasePipelineControllerTest.php
git commit -m "feat: add Purchase Request stage-change/created broadcasts and read-only pipeline API"
```

---

### Task 2: Frontend — desktop + mobile Pipeline Board pages

**Files:**
- Create: `resources/js-app/pages/desktop/purchase/PipelineBoardPage.jsx`
- Create: `resources/js-app/pages/desktop/purchase/PipelineBoardPage.test.jsx`
- Create: `resources/js-app/pages/mobile/purchase/PipelineBoardPage.jsx`
- Create: `resources/js-app/pages/mobile/purchase/PipelineBoardPage.test.jsx`
- Modify: `resources/js-app/App.jsx`
- Modify: `resources/js-app/layouts/navItems.js`
- Modify: `resources/views/app-shell.blade.php`

**Interfaces:**
- Consumes: `useLiveList` (existing hook, `apiGet`, `echo`, upsert/delete pattern), `Table` (existing shared component).
- Produces: `<PipelineBoardPage />` (both viewports) — self-fetching, no props.

- [ ] **Step 1: Read `useLiveList.js`, an existing `SupplierListPage.jsx`, and `resources/views/purchase/pipeline/index.blade.php` + `_table.blade.php` before writing any code**

Confirm `useLiveList`'s exact current signature/return shape (it may differ from earlier phases if it's been touched since) and the exact columns/labels the existing Blade board uses, so the React version matches it visually.

- [ ] **Step 2: Write the failing desktop test**

```jsx
// resources/js-app/pages/desktop/purchase/PipelineBoardPage.test.jsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { ToastProvider } from '../../../components/ui/Toast';
import PipelineBoardPage from './PipelineBoardPage';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => {}, stopListening: () => {} }), leave: () => {} },
}));

describe('PipelineBoardPage (desktop)', () => {
    it('splits requests into Active and Completed tabs', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [
                { id: 1, request_number: 'MPR26-0001', stage: 'draft', project_name: 'A', requested_by_name: 'Jane', department: 'Ops', date: '2026-08-01' },
                { id: 2, request_number: 'MPR26-0002', stage: 'complete', project_name: 'B', requested_by_name: 'Sam', department: 'Ops', date: '2026-07-01' },
            ],
        });

        render(<ToastProvider><PipelineBoardPage /></ToastProvider>);

        await waitFor(() => expect(screen.getByText('MPR26-0001')).toBeInTheDocument());
        expect(screen.queryByText('MPR26-0002')).not.toBeInTheDocument();

        fireEvent.click(screen.getByText(/Completed/));
        expect(screen.getByText('MPR26-0002')).toBeInTheDocument();
    });

    it('updates a request\'s stage live without a refetch', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 1, request_number: 'MPR26-0001', stage: 'draft', project_name: 'A', requested_by_name: 'Jane', department: 'Ops', date: '2026-08-01' }],
        });

        render(<ToastProvider><PipelineBoardPage /></ToastProvider>);
        await waitFor(() => expect(screen.getByText('MPR26-0001')).toBeInTheDocument());

        // The component's internal echo.listen callback is captured via the mocked echo module in a fuller
        // test setup; for this test, verify the row's stage badge reflects an updated upsertItem call path
        // by re-rendering with updated mock data if the live-event capture pattern from useLiveList tests applies.
        expect(screen.getByText(/Draft/i)).toBeInTheDocument();
    });
});
```

Adapt the second test to actually capture and fire the Echo handler if `useLiveList.test.jsx`'s existing pattern for this makes that straightforward — follow whatever pattern that file already established for testing live updates (e.g. capturing `ch.listen`'s callback via a shared mock), rather than inventing a new approach.

- [ ] **Step 3: Run test to verify it fails**

Run: `npm run test -- pages/desktop/purchase/PipelineBoardPage`
Expected: FAIL — module not found

- [ ] **Step 4: Implement the desktop page**

```jsx
// resources/js-app/pages/desktop/purchase/PipelineBoardPage.jsx
import { useState } from 'react';
import Card from '../../../components/ui/Card';
import Table from '../../../components/ui/Table';
import useLiveList from '../../../hooks/useLiveList';

const STAGE_LABELS = {
    draft: 'Draft', gm_approval: 'GM Approval', rfq: 'RFQ', quoting: 'Quoting',
    comparison: 'Comparison', lpo: 'LPO', receiving: 'Receiving', payment: 'Payment', complete: 'Complete',
};

const COLUMNS = [
    { key: 'request_number', label: 'Request #' },
    { key: 'project_name', label: 'Project' },
    { key: 'department', label: 'Department' },
    { key: 'requested_by_name', label: 'Requested By' },
    {
        key: 'stage',
        label: 'Stage',
        render: (row) => (
            <span style={{
                fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 20,
                background: row.stage === 'complete' ? '#dcfce7' : '#fffbeb',
                color: row.stage === 'complete' ? '#15803d' : '#92400e',
            }}>
                {STAGE_LABELS[row.stage] ?? row.stage}
            </span>
        ),
    },
    { key: 'date', label: 'Date' },
    {
        key: 'link', label: '',
        render: (row) => <a href={`/purchase/pipeline/${row.id}`}>View</a>,
    },
];

export default function PipelineBoardPage() {
    const { items } = useLiveList({
        endpoint: '/purchase/pipeline',
        channel: 'purchase',
        event: '.purchase-request.created',
        mergeKey: 'id',
        errorMessage: 'Failed to load the purchase pipeline.',
    });

    // Stage changes patch existing rows via the same channel; upsertItem's merge-by-id
    // logic (from useLiveList) means .purchase-request.stage-changed also needs wiring —
    // if useLiveList only supports one `event`/`deleteEvent` pair, use two hook calls or
    // extend the hook to accept an array of {event, handler} pairs. Check the hook's real
    // capabilities from Step 1 before deciding; this comment marks the decision point.

    const [tab, setTab] = useState('active');
    const active = items.filter((r) => r.stage !== 'complete');
    const completed = items.filter((r) => r.stage === 'complete');

    return (
        <Card title="Purchase Pipeline">
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 12 }}>
                <div>
                    <button onClick={() => setTab('active')}>Active ({active.length})</button>
                    <button onClick={() => setTab('completed')}>Completed ({completed.length})</button>
                </div>
                <button onClick={() => window.mprModalOpen && window.mprModalOpen()}>+ New Request</button>
            </div>
            <Table
                columns={COLUMNS}
                rows={tab === 'active' ? active : completed}
                rowKey={(row) => row.id}
                searchPlaceholder="Search requests…"
            />
        </Card>
    );
}
```

Resolve the comment's decision point during implementation: read `useLiveList.js`'s actual current shape (from Step 1) and either (a) call the hook twice with different `event` values if it supports independent subscriptions cleanly, or (b) extend `useLiveList` to accept multiple events (an array of `{event, handler}` — a small, backward-compatible addition), whichever fits its real current implementation with the least disruption. Whichever you choose, both `.purchase-request.created` (new row appears) and `.purchase-request.stage-changed` (existing row's `stage` field updates) must work live.

- [ ] **Step 5: Run test to verify it passes**

Run: `npm run test -- pages/desktop/purchase/PipelineBoardPage`

- [ ] **Step 6: Write the failing mobile test, then implement the mobile page**

Same two tests adapted for card rendering (`queryByRole('table')` should be null), same tab-switching and live-update behavior, laid out as a card list per request instead of a table row. Follow the same structural pattern as the Supplier module's mobile page.

- [ ] **Step 7: Run test to verify it passes**

Run: `npm run test -- pages/mobile/purchase/PipelineBoardPage`

- [ ] **Step 8: Wire the route into `App.jsx`, viewport-aware, following the exact pattern already used for the Supplier route**

- [ ] **Step 9: Add "Pipeline" to `navItems.js`'s Purchase group, pointing at `/app/purchase/pipeline`**

Check `resources/js-app/layouts/navItems.js`'s current structure (built in a prior phase) and add this entry in the same shape as the existing entries.

- [ ] **Step 10: Embed `<x-purchase.request-modal />` in `app-shell.blade.php`**

Add `<x-purchase.request-modal />` once, outside the `#react-app` mount div, so its Alpine.js state and `window.mprModalOpen()` function are available regardless of which React page is showing. Confirm it doesn't render its own visible trigger button in a way that duplicates or clutters the page (the component's own "+ New Request" `<button>` trigger, from `request-modal.blade.php:37`, would appear on-page unless suppressed — check whether the component accepts a prop/slot to hide its own trigger button, since the React page provides its own "+ New Request" button that calls `window.mprModalOpen()` directly; if no such option exists, hide the component's own button via a wrapping `<div style="display:none">` around just that button, or adapt as cleanly as the component allows without modifying its core form logic).

- [ ] **Step 11: Run the full frontend suite**

Run: `npm run test`

- [ ] **Step 12: Commit**

```bash
git add resources/js-app docs resources/views/app-shell.blade.php
git commit -m "feat: add live Purchase Requests Pipeline Board (desktop + mobile)"
```

---

### Task 3: Verification

- [ ] **Step 1: Run full suites**

Run: `php artisan test`, `npm run test`, `npm run build` — confirm no regressions beyond the one known pre-existing `ExampleTest` failure.

- [ ] **Step 2: Manual verification**

Confirm: `/app/purchase/pipeline` loads, splits active/completed correctly, "+ New Request" opens the existing modal, submitting a request from one browser session makes it appear live in another session's board (both on desktop and after resizing to mobile width), and clicking a row navigates to the existing Blade detail page correctly.
