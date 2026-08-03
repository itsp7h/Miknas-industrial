# Purchase Pipeline Detail Page (React, Phase 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the read-only shell + GM signature of the Purchase Pipeline detail page (`/purchase/pipeline/{id}`) to a React page with a desktop and a mobile component, fully cutting over from the Blade view, while keeping RFQ/quote/comparison/LPO/GRN/payment actions reachable via link-out to their existing (or newly-extracted-standalone) Blade pages.

**Architecture:** A new `Api\Purchase\PurchasePipelineController@show` returns a `PurchaseRequestDetailResource` (full relation graph + computed stage/progress + permission booleans). Two new React pages (`pages/desktop/purchase/PipelinePage.jsx`, `pages/mobile/purchase/PipelinePage.jsx`) render from that payload, share a new `StagePill` UI primitive and a new `SignaturePad` canvas component, and both subscribe to the existing `purchase-request.stage-changed` broadcast to refetch on change. `show.blade.php` and its route/controller method are deleted; two never-had-a-page actions (RFQ supplier-select, GRN-select) get small new standalone Blade pages so nothing is stranded; every other Blade file that linked to the now-deleted `purchase.pipeline.show` route is repointed at the new React URL.

**Tech Stack:** Laravel 12 / PHP 8.2, PHPUnit 11 (`RefreshDatabase`, Feature tests), React 18, react-router-dom, Vitest + `@testing-library/react`, Laravel Echo/Reverb (existing `purchase` private channel).

## Global Constraints

- Route parameter names must never be `{request}` — use `{purchaseRequest}` (CLAUDE.md gotcha #8).
- Modals/dynamic-width elements use inline `style="..."`, never Tailwind arbitrary classes (CLAUDE.md gotcha #1) — applies to any Blade view touched in this plan.
- No `alert()`/`confirm()`/`prompt()` anywhere — use `showToast()` / existing modal patterns (CLAUDE.md gotcha #7).
- No inline `@if(session(...))` banners in Blade views — the layout's toast system handles flash messages (CLAUDE.md gotcha #9).
- React pages must live in `pages/desktop/...` and `pages/mobile/...` as separate files, switched only via `useViewport()` — never one file branching on device (CLAUDE.md gotcha #12).
- Full cutover per file touched: no page should end up linked from two places (old Blade + new React) once this plan is done.
- API money fields are BD amounts formatted with 3 decimals in the existing Blade view (`number_format($x, 3)`) — match that in the resource/React rendering.

---

## Task 1: `PurchaseRequestDetailResource` + API `show` endpoint

**Files:**
- Create: `app/Http/Resources/PurchaseRequestDetailResource.php`
- Modify: `app/Http/Controllers/Api/Purchase/PurchasePipelineController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Api/Purchase/PurchasePipelineShowTest.php`

**Interfaces:**
- Produces: `GET /api/v1/purchase/pipeline/{purchaseRequest}` → `{ data: { id, request_number, date, project_name, department, requested_by_name, stage, stage_index, progress_pct, location, required_date_text, verified_by_name, status, signature: {...}|null, rfq_invitations: [...], items: [...], supplier_quotes: [...], purchase_orders: [...], permissions: { approve, manageRfq, manageQuotes, award, generateLpo, update } } }` — later tasks (7, 9, 10) consume this exact shape.

- [ ] **Step 1: Write the failing feature test**

```php
<?php

namespace Tests\Feature\Api\Purchase;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\PurchaseSignature;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePipelineShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_requires_authentication(): void
    {
        $pr = PurchaseRequest::factory()->create();

        $this->getJson("/api/v1/purchase/pipeline/{$pr->id}")->assertUnauthorized();
    }

    public function test_show_is_forbidden_without_view_permission(): void
    {
        $user = User::factory()->create();
        $pr = PurchaseRequest::factory()->create();

        $this->actingAs($user)->getJson("/api/v1/purchase/pipeline/{$pr->id}")->assertForbidden();
    }

    public function test_show_returns_full_detail_for_a_permitted_user(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('purchase-requests.view-all');
        $this->actingAs($user);

        $pr = PurchaseRequest::factory()->create(['stage' => 'quoting', 'location' => 'Site A']);
        $item = PurchaseRequestItem::factory()->create(['purchase_request_id' => $pr->id]);
        $supplier = Supplier::factory()->create();
        $invitation = RfqInvitation::factory()->create([
            'purchase_request_id' => $pr->id,
            'supplier_id' => $supplier->id,
            'status' => 'submitted',
        ]);
        $quote = SupplierQuote::factory()->create([
            'purchase_request_id' => $pr->id,
            'supplier_id' => $supplier->id,
            'rfq_invitation_id' => $invitation->id,
            'total_amount' => 123.456,
        ]);
        SupplierQuoteItem::factory()->create([
            'supplier_quote_id' => $quote->id,
            'purchase_request_item_id' => $item->id,
        ]);
        PurchaseOrder::factory()->create([
            'purchase_request_id' => $pr->id,
            'supplier_id' => $supplier->id,
        ]);

        $response = $this->getJson("/api/v1/purchase/pipeline/{$pr->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $pr->id);
        $response->assertJsonPath('data.stage', 'quoting');
        $response->assertJsonPath('data.stage_index', 3);
        $response->assertJsonCount(1, 'data.items');
        $response->assertJsonCount(1, 'data.rfq_invitations');
        $response->assertJsonCount(1, 'data.supplier_quotes');
        $response->assertJsonCount(1, 'data.purchase_orders');
        $response->assertJsonPath('data.supplier_quotes.0.total_amount', '123.456');
    }

    public function test_show_includes_signature_and_permission_flags(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['purchase-requests.view-all', 'purchase-requests.approve']);
        $this->actingAs($user);

        $pr = PurchaseRequest::factory()->create(['stage' => 'draft']);
        PurchaseSignature::factory()->create([
            'purchase_request_id' => $pr->id,
            'signed_by' => $user->id,
        ]);

        $response = $this->getJson("/api/v1/purchase/pipeline/{$pr->id}");

        $response->assertOk();
        $response->assertJsonPath('data.signature.signed_by_name', $user->name);
        $response->assertJsonPath('data.permissions.approve', true);
        $response->assertJsonPath('data.permissions.manageRfq', false);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=PurchasePipelineShowTest`
Expected: FAIL — route not found (404) or method not found, since `show()` doesn't exist yet. (If factories for `RfqInvitation`, `SupplierQuote`, `SupplierQuoteItem`, or `PurchaseSignature` don't exist yet, create minimal ones under `database/factories/` mirroring the existing `PurchaseRequestFactory` pattern before re-running — check `database/factories/` first; most Purchase models already have factories from earlier phases.)

- [ ] **Step 3: Create the resource**

```php
<?php

namespace App\Http\Resources;

use App\Services\PurchaseStageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseRequestDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \App\Services\PurchaseStageService $stages */
        $stages = app(PurchaseStageService::class);
        $user = $request->user();

        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'date' => $this->date?->toDateString(),
            'project_name' => $this->project_name,
            'department' => $this->department,
            'requested_by_name' => $this->requested_by_name ?? $this->requestedBy?->name,
            'location' => $this->location,
            'required_date_text' => $this->required_date_text,
            'verified_by_name' => $this->verified_by_name,
            'status' => $this->status,
            'stage' => $this->stage,
            'stage_index' => $stages->stageIndex($this->stage),
            'progress_pct' => $this->progressPct($stages),
            'created_at' => $this->created_at?->toIso8601String(),

            'signature' => $this->signature ? [
                'signature_image' => $this->signature->signature_image,
                'signed_by_name' => $this->signature->signedBy?->name,
                'signed_at' => $this->signature->signed_at?->toIso8601String(),
            ] : null,

            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'unit' => $item->unit,
                'quantity_required' => $item->quantity_required,
            ])->values(),

            'rfq_invitations' => $this->rfqInvitations->map(fn ($inv) => [
                'id' => $inv->id,
                'supplier_id' => $inv->supplier_id,
                'supplier_name' => $inv->supplier->name,
                'supplier_phone' => $inv->supplier->phone,
                'channel' => $inv->channel,
                'status' => $inv->status,
            ])->values(),

            'supplier_quotes' => $this->supplierQuotes->map(fn ($quote) => [
                'id' => $quote->id,
                'supplier_id' => $quote->supplier_id,
                'supplier_name' => $quote->supplier->name,
                'total_amount' => number_format((float) $quote->total_amount, 3),
                'submitted_at' => $quote->submitted_at?->toIso8601String(),
                'lead_time_days' => $quote->lead_time_days,
                'payment_terms' => $quote->payment_terms,
                'notes' => $quote->notes,
                'has_awarded_items' => $quote->hasAwardedItems(),
                'items' => $quote->items->map(fn ($qi) => [
                    'id' => $qi->id,
                    'purchase_request_item_id' => $qi->purchase_request_item_id,
                    'description' => $qi->description,
                    'supplier_description' => $qi->supplier_description,
                    'quantity' => $qi->quantity,
                    'unit' => $qi->unit,
                    'unit_price' => number_format((float) $qi->unit_price, 3),
                    'total_price' => number_format((float) $qi->total_price, 3),
                    'is_vatable' => (bool) $qi->is_vatable,
                    'not_available' => (bool) $qi->not_available,
                    'is_awarded' => (bool) $qi->is_awarded,
                ])->values(),
            ])->values(),

            'purchase_orders' => $this->purchaseOrders->map(fn ($po) => [
                'id' => $po->id,
                'po_number' => $po->po_number ?? ('PO-' . str_pad((string) $po->id, 5, '0', STR_PAD_LEFT)),
                'supplier_name' => $po->supplier->name ?? null,
                'status' => $po->status,
                'total_amount' => number_format((float) $po->total_amount, 3),
            ])->values(),

            'permissions' => [
                'update' => $user->can('update', $this->resource),
                'approve' => $user->can('approve', $this->resource),
                'manageRfq' => $user->can('manageRfq', $this->resource),
                'manageQuotes' => $user->can('manageQuotes', $this->resource),
                'award' => $user->can('award', $this->resource),
                'generateLpo' => $user->can('generateLpo', $this->resource),
            ],
        ];
    }

    private function progressPct(PurchaseStageService $stages): int
    {
        $total = count(PurchaseStageService::STAGES);
        if ($total <= 1) {
            return 100;
        }

        return (int) round(($stages->stageIndex($this->stage) / ($total - 1)) * 100);
    }
}
```

- [ ] **Step 4: Add the `show` action to the API controller**

```php
    public function show(\App\Models\PurchaseRequest $purchaseRequest)
    {
        $this->authorize('view', $purchaseRequest);

        $purchaseRequest->load([
            'requestedBy',
            'items',
            'signature.signedBy',
            'rfqInvitations.supplier',
            'supplierQuotes.supplier',
            'supplierQuotes.items',
            'purchaseOrders.supplier',
        ]);

        return new \App\Http\Resources\PurchaseRequestDetailResource($purchaseRequest);
    }
```

Add this method to `app/Http/Controllers/Api/Purchase/PurchasePipelineController.php`, alongside the existing `index()`. Add `use App\Http\Resources\PurchaseRequestDetailResource;` and `use App\Models\PurchaseRequest;` to the top-of-file `use` block instead of the inline FQCNs above if that matches the file's existing style (check the file first — `index()` already imports `PurchaseRequestPolicy` and `PurchaseStageService` at the top, so follow that convention for `show()` too).

- [ ] **Step 5: Register the route**

In `routes/api.php`, inside the existing `Route::prefix('purchase')->group(...)` block (the one containing `Route::get('pipeline', ...)`):

```php
            Route::get('pipeline/{purchaseRequest}', [\App\Http\Controllers\Api\Purchase\PurchasePipelineController::class, 'show']);
```

Add it directly below the existing `Route::get('pipeline', ...)` line — order doesn't matter here since `pipeline/{purchaseRequest}` and `pipeline` don't collide the way `{request}` gotchas do, but keeping them adjacent keeps the file readable.

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=PurchasePipelineShowTest`
Expected: PASS (4 tests). If a factory is missing, add a minimal one under `database/factories/` (e.g. `RfqInvitationFactory`, `SupplierQuoteFactory`, `SupplierQuoteItemFactory`, `PurchaseSignatureFactory`) with just the fields exercised above, following the existing `PurchaseRequestFactory`'s style, then re-run.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Resources/PurchaseRequestDetailResource.php app/Http/Controllers/Api/Purchase/PurchasePipelineController.php routes/api.php tests/Feature/Api/Purchase/PurchasePipelineShowTest.php database/factories
git commit -m "feat: add API show endpoint for Purchase Pipeline detail page"
```

---

## Task 2: API signature capture endpoint

**Files:**
- Create: `app/Http/Controllers/Api/Purchase/PurchaseSignatureController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Api/Purchase/PurchaseSignatureApiTest.php`

**Interfaces:**
- Consumes: `PurchaseStageService::advance()` (`app/Services/PurchaseStageService.php:15`), `PurchaseSignature::create()` (`app/Models/PurchaseSignature.php`).
- Produces: `POST /api/v1/purchase/requests/{purchaseRequest}/sign` with JSON body `{ signature_image: string }` → `201` `{ data: { signature_image, signed_by_name, signed_at } }` on success, `422` `{ message }` if already signed. Consumed by `components/purchase/SignaturePad.jsx` in Task 8.

- [ ] **Step 1: Write the failing feature test**

```php
<?php

namespace Tests\Feature\Api\Purchase;

use App\Models\PurchaseRequest;
use App\Models\PurchaseSignature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseSignatureApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_requires_approve_permission(): void
    {
        $user = User::factory()->create();
        $pr = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->actingAs($user)
            ->postJson("/api/v1/purchase/requests/{$pr->id}/sign", ['signature_image' => 'data:image/png;base64,abc'])
            ->assertForbidden();
    }

    public function test_sign_creates_signature_and_advances_stage(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('purchase-requests.approve');
        $this->actingAs($user);
        $pr = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $response = $this->postJson("/api/v1/purchase/requests/{$pr->id}/sign", [
            'signature_image' => 'data:image/png;base64,abc',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.signed_by_name', $user->name);
        $this->assertDatabaseHas('purchase_signatures', ['purchase_request_id' => $pr->id, 'signed_by' => $user->id]);
        $this->assertSame('gm_approval', $pr->refresh()->stage);
    }

    public function test_sign_rejects_a_request_that_is_already_signed(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('purchase-requests.approve');
        $this->actingAs($user);
        $pr = PurchaseRequest::factory()->create(['stage' => 'gm_approval']);
        PurchaseSignature::factory()->create(['purchase_request_id' => $pr->id]);

        $response = $this->postJson("/api/v1/purchase/requests/{$pr->id}/sign", [
            'signature_image' => 'data:image/png;base64,abc',
        ]);

        $response->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=PurchaseSignatureApiTest`
Expected: FAIL — 404, route doesn't exist yet.

- [ ] **Step 3: Implement the controller**

```php
<?php

namespace App\Http\Controllers\Api\Purchase;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\PurchaseSignature;
use App\Services\PurchaseStageService;
use Illuminate\Http\Request;

class PurchaseSignatureController extends Controller
{
    public function store(Request $request, PurchaseRequest $purchaseRequest, PurchaseStageService $stages)
    {
        $this->authorize('approve', $purchaseRequest);

        $validated = $request->validate([
            'signature_image' => ['required', 'string'],
        ]);

        if ($purchaseRequest->signature) {
            return response()->json(['message' => 'This request has already been signed.'], 422);
        }

        $signature = PurchaseSignature::create([
            'purchase_request_id' => $purchaseRequest->id,
            'signed_by' => auth()->id(),
            'signature_image' => $validated['signature_image'],
            'signed_at' => now(),
            'ip_address' => $request->ip(),
        ]);

        $stages->advance($purchaseRequest);

        $signature->load('signedBy');

        return response()->json([
            'data' => [
                'signature_image' => $signature->signature_image,
                'signed_by_name' => $signature->signedBy?->name,
                'signed_at' => $signature->signed_at?->toIso8601String(),
            ],
        ], 201);
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/api.php`, inside the same `Route::prefix('purchase')->group(...)` block:

```php
            Route::post('requests/{purchaseRequest}/sign', [\App\Http\Controllers\Api\Purchase\PurchaseSignatureController::class, 'store']);
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=PurchaseSignatureApiTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/Purchase/PurchaseSignatureController.php routes/api.php tests/Feature/Api/Purchase/PurchaseSignatureApiTest.php
git commit -m "feat: add API endpoint for GM signature capture"
```

---

## Task 3: Repoint every `purchase.pipeline.show` reference at the React URL

**Why now:** Task 6 deletes the `purchase.pipeline.show` route. Every call site that generates a URL from that route name must switch to a literal `/app/purchase/pipeline/{id}` path first, or those files (and their own tests) break.

**Files:**
- Modify: `app/Http/Controllers/Purchase/PurchaseSignatureController.php:43`
- Modify: `app/Http/Controllers/Purchase/RfqController.php:89,93,113`
- Modify: `app/Notifications/QuoteReceived.php:29`
- Modify: `resources/views/purchase/orders/show.blade.php:30`
- Modify: `resources/views/purchase/requests/show.blade.php:12`
- Modify: `resources/views/purchase/quotes/workspace.blade.php:9`
- Modify: `resources/views/purchase/requests/edit.blade.php:10,151`
- Test: `tests/Feature/Purchase/PurchasePipelineRedirectsTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: every one of the files above now builds its "back to pipeline" link as the literal string `"/app/purchase/pipeline/{$purchaseRequest->id}"` (Blade) or `'/app/purchase/pipeline/' . $purchaseRequest->id` (PHP) instead of `route('purchase.pipeline.show', $purchaseRequest)`.

- [ ] **Step 1: Write the failing test for the two backend redirects**

```php
<?php

namespace Tests\Feature\Purchase;

use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePipelineRedirectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_signing_redirects_to_the_react_pipeline_url(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('purchase-requests.approve');
        $this->actingAs($user);
        $pr = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $response = $this->post(route('purchase.requests.sign.store', $pr), [
            'signature_image' => 'data:image/png;base64,abc',
        ]);

        $response->assertRedirect("/app/purchase/pipeline/{$pr->id}");
    }

    public function test_sending_rfqs_redirects_to_the_react_pipeline_url(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('purchase-requests.manage-rfq');
        $this->actingAs($user);
        $pr = PurchaseRequest::factory()->create(['stage' => 'rfq']);
        $supplier = Supplier::factory()->create();
        $pr->rfqInvitations()->create([
            'supplier_id' => $supplier->id,
            'token' => 'tok-1',
            'channel' => 'email',
            'status' => 'pending',
        ]);

        $response = $this->post(route('purchase.requests.rfq.send-all', $pr));

        $response->assertRedirect("/app/purchase/pipeline/{$pr->id}");
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=PurchasePipelineRedirectsTest`
Expected: FAIL — both assertions fail because the controllers still redirect to `route('purchase.pipeline.show', ...)`, i.e. `/purchase/pipeline/{id}`.

- [ ] **Step 3: Update the two controllers**

In `app/Http/Controllers/Purchase/PurchaseSignatureController.php:43`, replace:

```php
        return redirect()->route('purchase.pipeline.show', $purchaseRequest)
            ->with('success', 'Signature saved. Request moved to RFQ stage.');
```

with:

```php
        return redirect('/app/purchase/pipeline/' . $purchaseRequest->id)
            ->with('success', 'Signature saved. Request moved to RFQ stage.');
```

In `app/Http/Controllers/Purchase/RfqController.php`, replace all three occurrences:

```php
                'redirect'    => route('purchase.pipeline.show', $purchaseRequest),
```
→
```php
                'redirect'    => '/app/purchase/pipeline/' . $purchaseRequest->id,
```

```php
        return redirect()->route('purchase.pipeline.show', $purchaseRequest)
            ->with('success', $added . ' supplier(s) added. Now send them the quote request links.');
```
→
```php
        return redirect('/app/purchase/pipeline/' . $purchaseRequest->id)
            ->with('success', $added . ' supplier(s) added. Now send them the quote request links.');
```

```php
        return redirect()->route('purchase.pipeline.show', $purchaseRequest)
            ->with('success', $pending->count() . ' supplier(s) notified. Waiting for quotes.');
```
→
```php
        return redirect('/app/purchase/pipeline/' . $purchaseRequest->id)
            ->with('success', $pending->count() . ' supplier(s) notified. Waiting for quotes.');
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=PurchasePipelineRedirectsTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Update the remaining view/notification references (no dedicated test — visual link targets)**

In `app/Notifications/QuoteReceived.php:29`, replace:
```php
            'url'              => route('purchase.pipeline.show', $pr),
```
with:
```php
            'url'              => '/app/purchase/pipeline/' . $pr->id,
```

In `resources/views/purchase/orders/show.blade.php:30`, replace:
```blade
    <a href="{{ $order->purchaseRequest ? route('purchase.pipeline.show', $order->purchaseRequest) : route('purchase.orders.index') }}"
```
with:
```blade
    <a href="{{ $order->purchaseRequest ? '/app/purchase/pipeline/' . $order->purchaseRequest->id : route('purchase.orders.index') }}"
```

In `resources/views/purchase/requests/show.blade.php:12`, replace:
```blade
        <a href="{{ route('purchase.pipeline.show', $purchaseRequest) }}"
```
with:
```blade
        <a href="/app/purchase/pipeline/{{ $purchaseRequest->id }}"
```

In `resources/views/purchase/quotes/workspace.blade.php:9`, replace:
```blade
    <a href="{{ route('purchase.pipeline.show', $request) }}"
```
with:
```blade
    <a href="/app/purchase/pipeline/{{ $request->id }}"
```

In `resources/views/purchase/requests/edit.blade.php:10` and `:151`, replace both:
```blade
        <a href="{{ route('purchase.pipeline.show', $purchaseRequest) }}" class="text-blue-600 hover:underline">{{ $purchaseRequest->request_number }}</a> /
```
with:
```blade
        <a href="/app/purchase/pipeline/{{ $purchaseRequest->id }}" class="text-blue-600 hover:underline">{{ $purchaseRequest->request_number }}</a> /
```
and:
```blade
        <a href="{{ route('purchase.pipeline.show', $purchaseRequest) }}" class="btn-secondary">Cancel</a>
```
with:
```blade
        <a href="/app/purchase/pipeline/{{ $purchaseRequest->id }}" class="btn-secondary">Cancel</a>
```

- [ ] **Step 6: Verify no reference to the route name remains**

Run: `grep -rn "purchase.pipeline.show" app resources routes`
Expected: no output (the only remaining definition, the route itself in `routes/web.php`, is removed in Task 6 — confirm again after that task).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Purchase/PurchaseSignatureController.php app/Http/Controllers/Purchase/RfqController.php app/Notifications/QuoteReceived.php resources/views/purchase/orders/show.blade.php resources/views/purchase/requests/show.blade.php resources/views/purchase/quotes/workspace.blade.php resources/views/purchase/requests/edit.blade.php tests/Feature/Purchase/PurchasePipelineRedirectsTest.php
git commit -m "refactor: repoint pipeline links at the React detail page URL"
```

---

## Task 4: Standalone "Select Suppliers" page (RFQ) stopgap

**Why:** `RfqController::selectSuppliers` (POST) has no GET page of its own — today it's only reachable via the `x-purchase.supplier-select-modal` component embedded in `show.blade.php`, which Task 6 deletes. This task extracts that modal into a real page.

**Files:**
- Modify: `app/Http/Controllers/Purchase/RfqController.php` (add `selectSuppliersPage`)
- Modify: `routes/web.php`
- Create: `resources/views/purchase/rfq/select-suppliers.blade.php`
- Create: `tests/Feature/Purchase/RfqSelectSuppliersPageTest.php`

**Interfaces:**
- Produces: `GET /purchase/requests/{purchaseRequest}/rfq/select-suppliers` (name: `purchase.requests.rfq.select-suppliers-page`) — a full page rendering the same 3-step chooser/selector/links flow the modal had, posting to the existing `purchase.requests.rfq.select` route. Consumed by the React stage-timeline's "Add Suppliers" link (Task 9/10).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Purchase;

use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfqSelectSuppliersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_requires_manage_rfq_permission(): void
    {
        $user = User::factory()->create();
        $pr = PurchaseRequest::factory()->create(['stage' => 'rfq']);

        $this->actingAs($user)
            ->get(route('purchase.requests.rfq.select-suppliers-page', $pr))
            ->assertForbidden();
    }

    public function test_page_lists_active_suppliers(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('purchase-requests.manage-rfq');
        $this->actingAs($user);
        $pr = PurchaseRequest::factory()->create(['stage' => 'rfq']);
        $supplier = Supplier::factory()->create(['name' => 'Acme Steel', 'is_active' => true]);

        $response = $this->get(route('purchase.requests.rfq.select-suppliers-page', $pr));

        $response->assertOk();
        $response->assertSee('Acme Steel');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=RfqSelectSuppliersPageTest`
Expected: FAIL — route `purchase.requests.rfq.select-suppliers-page` doesn't exist.

- [ ] **Step 3: Add the controller method**

In `app/Http/Controllers/Purchase/RfqController.php`, add (needs `use App\Models\PurchaseRequest;`, `use App\Models\Supplier;`, both already imported):

```php
    public function selectSuppliersPage(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('manageRfq', $purchaseRequest);

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $selectedIds = $purchaseRequest->rfqInvitations()->pluck('supplier_id')->toArray();

        return view('purchase.rfq.select-suppliers', [
            'pr' => $purchaseRequest,
            'suppliers' => $suppliers,
            'selectedIds' => $selectedIds,
        ]);
    }
```

- [ ] **Step 4: Register the route**

In `routes/web.php`, add directly below the existing `requests/{purchaseRequest}/rfq/select` and `requests/{purchaseRequest}/rfq/send-all` lines (around line 85-86):

```php
        Route::get('requests/{purchaseRequest}/rfq/select-suppliers', [RfqController::class, 'selectSuppliersPage'])->name('requests.rfq.select-suppliers-page');
```

- [ ] **Step 5: Create the standalone view**

This reuses the body of `resources/views/components/purchase/supplier-select-modal.blade.php` almost verbatim, with three changes: (a) it's a full Blade page (`@extends('layouts.app')`) instead of a `@props`-based component rendered inside a modal overlay, (b) the outer `<div id="supplier-modal" class="pipe-modal">...</div>` wrapper is replaced by a plain page container that's always visible (no `.open` class needed, no `openSupplierModal()`/`closeSupplierModal()` toggling), and (c) "Cancel" and the post-save "Done" action navigate to `/app/purchase/pipeline/{id}` (a real link / `window.location.href`) instead of closing a modal.

```blade
@extends('layouts.app')

@section('title', 'Select Suppliers — ' . $pr->request_number)

@section('content')
<style>
  .action-btn { display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;padding:6px 14px;border-radius:7px;text-decoration:none;white-space:nowrap;cursor:pointer;border:none; }
</style>

<div style="margin-bottom:20px;">
  <a href="/app/purchase/pipeline/{{ $pr->id }}"
     style="font-size:13px;color:#2563eb;text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
    </svg>
    {{ $pr->request_number }}
  </a>
</div>

<div style="background:#fff;border-radius:20px;max-width:680px;margin:0 auto;box-shadow:0 2px 12px rgba(0,0,0,.06);overflow:hidden;">

  {{-- Header --}}
  <div style="padding:20px 24px 16px;border-bottom:1px solid #f1f5f9;">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:4px;">
      <div>
        <div id="sup-modal-title" style="font-size:17px;font-weight:700;color:#0f172a;">Request for Quotation</div>
        <div id="sup-modal-subtitle" style="font-size:12px;color:#64748b;margin-top:3px;">How do you want to assign suppliers?</div>
      </div>
    </div>
    <div id="sup-mode-badge-row" style="display:none;align-items:center;gap:8px;margin-top:10px;">
      <button type="button" onclick="goBack()"
        style="display:flex;align-items:center;gap:4px;font-size:12px;color:#2563eb;background:none;border:none;cursor:pointer;padding:0;font-weight:600;">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg>
        Change method
      </button>
      <span style="color:#cbd5e1;">·</span>
      <span id="sup-mode-badge" style="font-size:11px;padding:2px 9px;border-radius:10px;font-weight:700;"></span>
    </div>
  </div>

  {{-- Step 1: Method selection --}}
  <div id="sup-step1" style="display:flex;flex-direction:column;">
    <div style="padding:24px;display:flex;flex-direction:column;gap:12px;">
      <button type="button" onclick="showStep('global')"
        style="width:100%;text-align:left;border:2px solid #e2e8f0;border-radius:12px;padding:18px 20px;cursor:pointer;display:flex;align-items:center;gap:16px;background:#fff;">
        <div style="width:44px;height:44px;background:#eff6ff;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">📦</div>
        <div style="flex:1;">
          <div style="font-size:14px;font-weight:700;color:#0f172a;">Full Order</div>
          <div style="font-size:12px;color:#64748b;margin-top:3px;">One set of suppliers handles the entire purchase request</div>
        </div>
      </button>
      <button type="button" onclick="showStep('item')"
        style="width:100%;text-align:left;border:2px solid #e2e8f0;border-radius:12px;padding:18px 20px;cursor:pointer;display:flex;align-items:center;gap:16px;background:#fff;">
        <div style="width:44px;height:44px;background:#f0fdf4;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">🔀</div>
        <div style="flex:1;">
          <div style="font-size:14px;font-weight:700;color:#0f172a;">By Item</div>
          <div style="font-size:12px;color:#64748b;margin-top:3px;">Assign different suppliers to specific items in this request</div>
        </div>
      </button>
    </div>
    <div style="padding:14px 24px;border-top:1px solid #f1f5f9;display:flex;justify-content:flex-end;background:#fafafa;">
      <a href="/app/purchase/pipeline/{{ $pr->id }}"
        style="padding:8px 18px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:600;color:#64748b;background:#fff;text-decoration:none;">
        Cancel
      </a>
    </div>
  </div>

  {{-- Step 2: Supplier selection (identical markup/behavior to the modal's step 2) --}}
  <div id="sup-step2" style="display:none;flex-direction:column;">
    <form id="sup-form" action="{{ route('purchase.requests.rfq.select', $pr) }}" method="POST" style="display:flex;flex-direction:column;">
      @csrf
      <input type="hidden" name="mode" id="sup-mode" value="">

      <div id="sup-global-pane" style="display:flex;flex-direction:column;">
        <div style="padding:14px 24px 10px;">
          <input id="sup-search" type="text" placeholder="Search suppliers…" oninput="filterGlobalSups(this.value)"
            style="width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #e2e8f0;border-radius:9px;font-size:13px;outline:none;">
        </div>
        <div id="sup-list">
          @forelse($suppliers as $sup)
          @php $alreadyAdded = in_array($sup->id, $selectedIds); @endphp
          <div class="g-sup-item" data-name="{{ strtolower($sup->name) }}"
            style="padding:11px 24px;display:flex;align-items:center;gap:14px;border-bottom:1px solid #f8fafc;{{ $alreadyAdded ? 'opacity:.45;pointer-events:none;' : '' }}">
            <input type="checkbox" name="supplier_ids[]" value="{{ $sup->id }}" id="gsup-{{ $sup->id }}"
              {{ $alreadyAdded ? 'disabled checked' : '' }}
              onchange="toggleGlobalChan({{ $sup->id }}, this.checked); updateGlobalCount();"
              style="width:17px;height:17px;accent-color:#2563eb;">
            <label for="gsup-{{ $sup->id }}" style="flex:1;">
              <div style="font-size:13px;font-weight:600;color:#0f172a;">
                {{ $sup->name }}
                @if($alreadyAdded)<span style="font-size:10px;background:#dcfce7;color:#15803d;padding:1px 6px;border-radius:10px;margin-left:6px;">Added</span>@endif
              </div>
            </label>
            @if(!$alreadyAdded)
            <div id="gchan-{{ $sup->id }}" style="display:none;">
              <input type="hidden" name="channel_{{ $sup->id }}" id="gchan-val-{{ $sup->id }}" value="email">
              <div style="display:flex;border:1px solid #e2e8f0;border-radius:7px;overflow:hidden;">
                <span onclick="setGChan({{ $sup->id }},'email')" id="gce-{{ $sup->id }}" style="padding:5px 9px;font-size:10px;font-weight:700;cursor:pointer;background:#eff6ff;color:#2563eb;">Email</span>
                <span onclick="setGChan({{ $sup->id }},'whatsapp')" id="gcw-{{ $sup->id }}" style="padding:5px 9px;font-size:10px;font-weight:700;cursor:pointer;background:#fff;color:#94a3b8;border-left:1px solid #e2e8f0;">WA</span>
                <span onclick="setGChan({{ $sup->id }},'both')" id="gcb-{{ $sup->id }}" style="padding:5px 9px;font-size:10px;font-weight:700;cursor:pointer;background:#fff;color:#94a3b8;border-left:1px solid #e2e8f0;">Both</span>
              </div>
            </div>
            @endif
          </div>
          @empty
          <div style="padding:40px;text-align:center;color:#94a3b8;font-size:13px;">No active suppliers found.</div>
          @endforelse
          <div id="no-sup-msg" style="display:none;padding:30px;text-align:center;color:#94a3b8;font-size:13px;">No suppliers match your search.</div>
        </div>
      </div>

      <div id="sup-item-pane" style="display:none;flex-direction:column;">
        @if($pr->items->isEmpty())
          <div style="padding:40px;text-align:center;color:#94a3b8;font-size:13px;">This request has no items yet.</div>
        @else
          @foreach($pr->items as $item)
          <div style="display:grid;grid-template-columns:1fr 175px 120px;gap:12px;align-items:center;padding:13px 24px;border-bottom:1px solid #f8fafc;">
            <div>
              <div style="font-size:13px;font-weight:600;color:#0f172a;">{{ $item->description }}</div>
              <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Qty: {{ rtrim(rtrim(number_format($item->quantity_required,2),'0'),'.') }}{{ $item->unit ? ' '.$item->unit : '' }}</div>
            </div>
            <div>
              <button type="button" id="idd-btn-{{ $item->id }}" onclick="toggleItemDd(event,{{ $item->id }})"
                style="width:100%;padding:8px 11px;border:1.5px solid #e2e8f0;border-radius:8px;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:6px;text-align:left;">
                <span id="idd-label-{{ $item->id }}" style="font-size:12px;color:#94a3b8;">Select suppliers…</span>
              </button>
            </div>
            <div id="ichan-item-btn-{{ $item->id }}" style="display:flex;border:1.5px solid #e2e8f0;border-radius:8px;overflow:hidden;opacity:.35;pointer-events:none;">
              <span onclick="setItemChan({{ $item->id }},'email')" id="ichan-ie-{{ $item->id }}" style="flex:1;padding:7px 4px;font-size:10px;font-weight:700;cursor:pointer;text-align:center;background:#eff6ff;color:#2563eb;">Email</span>
              <span onclick="setItemChan({{ $item->id }},'whatsapp')" id="ichan-iw-{{ $item->id }}" style="flex:1;padding:7px 4px;font-size:10px;font-weight:700;cursor:pointer;text-align:center;background:#fff;color:#94a3b8;border-left:1.5px solid #e2e8f0;">WA</span>
              <span onclick="setItemChan({{ $item->id }},'both')" id="ichan-ib-{{ $item->id }}" style="flex:1;padding:7px 4px;font-size:10px;font-weight:700;cursor:pointer;text-align:center;background:#fff;color:#94a3b8;border-left:1.5px solid #e2e8f0;">Both</span>
            </div>
          </div>
          @endforeach
          <div style="display:none;">
            @foreach($suppliers as $sup)
              <input type="hidden" name="channel_{{ $sup->id }}" id="ichan-val-{{ $sup->id }}" value="email">
            @endforeach
          </div>
        @endif
      </div>

      @foreach($pr->items as $item)
      <div id="idd-{{ $item->id }}" style="display:none;position:fixed;z-index:99999;background:#fff;border:1.5px solid #e2e8f0;border-radius:12px;box-shadow:0 12px 32px rgba(0,0,0,.18);overflow:hidden;min-width:240px;">
        <div style="padding:10px 10px 6px;">
          <input type="text" placeholder="Search suppliers…" oninput="filterItemDd({{ $item->id }}, this.value)"
            style="width:100%;box-sizing:border-box;padding:7px 10px;border:1px solid #e2e8f0;border-radius:7px;font-size:12px;outline:none;">
        </div>
        <div style="max-height:210px;overflow-y:auto;padding-bottom:4px;">
          @forelse($suppliers as $sup)
          @php $alreadyAdded = in_array($sup->id, $selectedIds); @endphp
          <label class="idd-row-{{ $item->id }}" data-name="{{ strtolower($sup->name) }}"
            style="display:flex;align-items:center;gap:10px;padding:8px 12px;{{ $alreadyAdded ? 'opacity:.45;' : '' }}">
            <input type="checkbox" name="item_suppliers[{{ $item->id }}][]" value="{{ $sup->id }}" data-supname="{{ $sup->name }}"
              id="isup-{{ $item->id }}-{{ $sup->id }}" {{ $alreadyAdded ? 'disabled checked' : '' }}
              onchange="onItemSupChange({{ $item->id }}, {{ $sup->id }})" style="width:15px;height:15px;accent-color:#2563eb;">
            <div>
              <div style="font-size:12px;font-weight:600;color:#0f172a;">{{ $sup->name }}</div>
            </div>
          </label>
          @empty
          <div style="padding:16px;text-align:center;color:#94a3b8;font-size:12px;">No suppliers found.</div>
          @endforelse
          <div id="idd-no-{{ $item->id }}" style="display:none;padding:14px;text-align:center;color:#94a3b8;font-size:12px;">No results</div>
        </div>
      </div>
      @endforeach
    </form>

    <div style="padding:14px 24px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;background:#fafafa;">
      <div style="font-size:12px;color:#64748b;" id="sup-footer-msg">0 selected</div>
      <div style="display:flex;gap:10px;">
        <a href="/app/purchase/pipeline/{{ $pr->id }}"
          style="padding:8px 18px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:600;color:#64748b;background:#fff;text-decoration:none;">
          Cancel
        </a>
        <button type="button" onclick="submitSuppliers()"
          style="padding:8px 22px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
          Save &amp; Continue →
        </button>
      </div>
    </div>
  </div>

  {{-- Step 3: Links --}}
  <div id="sup-step3" style="display:none;flex-direction:column;">
    <div id="sup-summary-body" style="padding:20px 24px;"></div>
    <div style="padding:14px 24px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;background:#fafafa;">
      <div style="font-size:12px;color:#64748b;" id="sup-link-count"></div>
      <button type="button" onclick="doneWithLinks()"
        style="padding:8px 22px;background:#16a34a;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
        Done ✓
      </button>
    </div>
  </div>
</div>

<script>
// Same behavior as components/purchase/supplier-select-modal.blade.php's script block,
// minus openSupplierModal/closeSupplierModal (there is no overlay to toggle — this is a
// full page) and with doneWithLinks() navigating instead of closing a modal.
var _supTab = 'global';

function showStep(method) {
  _supTab = method;
  document.getElementById('sup-mode').value = method === 'item' ? 'by_item' : 'global';
  var badge = document.getElementById('sup-mode-badge');
  if (method === 'global') { badge.textContent = '📦 Full Order'; badge.style.background = '#eff6ff'; badge.style.color = '#2563eb'; }
  else { badge.textContent = '🔀 By Item'; badge.style.background = '#f0fdf4'; badge.style.color = '#15803d'; }
  document.getElementById('sup-global-pane').style.display = method === 'global' ? 'flex' : 'none';
  document.getElementById('sup-item-pane').style.display   = method === 'item'   ? 'flex' : 'none';
  document.getElementById('sup-modal-title').textContent = 'Select Suppliers';
  document.getElementById('sup-modal-subtitle').textContent = 'Choose who receives the quote request';
  document.getElementById('sup-mode-badge-row').style.display = 'flex';
  document.getElementById('sup-step1').style.display = 'none';
  document.getElementById('sup-step2').style.display = 'flex';
  if (method === 'global') setTimeout(function(){ document.getElementById('sup-search').focus(); }, 50);
  updateFooter();
}
function goBack() {
  document.querySelectorAll('#sup-form input[type="checkbox"]:not([disabled])').forEach(function(cb) { cb.checked = false; });
  document.querySelectorAll('[id^="gchan-"]').forEach(function(el) { el.style.display = 'none'; });
  var searchEl = document.getElementById('sup-search');
  if (searchEl) { searchEl.value = ''; filterGlobalSups(''); }
  document.querySelectorAll('[id^="idd-label-"]').forEach(function(el) { el.textContent = 'Select suppliers…'; el.style.color = '#94a3b8'; });
  document.querySelectorAll('[id^="ichan-item-btn-"]').forEach(function(el) { el.style.opacity = '.35'; el.style.pointerEvents = 'none'; });
  _itemChan = {};
  document.getElementById('sup-mode').value = '';
  document.getElementById('sup-modal-title').textContent = 'Request for Quotation';
  document.getElementById('sup-modal-subtitle').textContent = 'How do you want to assign suppliers?';
  document.getElementById('sup-mode-badge-row').style.display = 'none';
  document.getElementById('sup-step1').style.display = 'flex';
  document.getElementById('sup-step2').style.display = 'none';
  document.getElementById('sup-step3').style.display = 'none';
  closeAllItemDd();
}
function filterGlobalSups(q) {
  q = q.toLowerCase().trim();
  var visible = 0;
  document.querySelectorAll('.g-sup-item').forEach(function(item) {
    var match = !q || item.dataset.name.indexOf(q) !== -1;
    item.style.display = match ? 'flex' : 'none';
    if (match) visible++;
  });
  document.getElementById('no-sup-msg').style.display = (visible === 0 && q) ? 'block' : 'none';
}
function toggleGlobalChan(id, checked) {
  var el = document.getElementById('gchan-' + id);
  if (el) el.style.display = checked ? 'block' : 'none';
  updateFooter();
}
var chanStyles = { email: {bg:'#eff6ff',fg:'#2563eb'}, whatsapp: {bg:'#f0fdf4',fg:'#15803d'}, both: {bg:'#fef3c7',fg:'#92400e'} };
function setGChan(id, val) { document.getElementById('gchan-val-' + id).value = val; }
function updateGlobalCount() { updateFooter(); }
var _openItemDd = null;
var _itemChan = {};
function setItemChan(itemId, val) {
  _itemChan[itemId] = val;
  document.querySelectorAll('input[name="item_suppliers[' + itemId + '][]"]:checked:not([disabled])').forEach(function(cb) {
    var inp = document.getElementById('ichan-val-' + cb.value);
    if (inp) inp.value = val;
  });
}
function toggleItemDd(event, itemId) {
  event.stopPropagation();
  var dd = document.getElementById('idd-' + itemId);
  if (!dd) return;
  if (_openItemDd === itemId) { dd.style.display = 'none'; _openItemDd = null; return; }
  closeAllItemDd();
  var btn = document.getElementById('idd-btn-' + itemId);
  var rect = btn.getBoundingClientRect();
  var ddWidth = 260;
  dd.style.left = Math.min(rect.left, window.innerWidth - ddWidth - 8) + 'px';
  dd.style.top = (rect.bottom + 4) + 'px';
  dd.style.width = ddWidth + 'px';
  dd.style.display = 'block';
  _openItemDd = itemId;
}
function closeAllItemDd() {
  if (_openItemDd !== null) {
    var dd = document.getElementById('idd-' + _openItemDd);
    if (dd) dd.style.display = 'none';
    _openItemDd = null;
  }
}
function filterItemDd(itemId, q) {
  q = q.toLowerCase().trim();
  var visible = 0;
  document.querySelectorAll('.idd-row-' + itemId).forEach(function(row) {
    var match = !q || (row.dataset.name || '').indexOf(q) !== -1;
    row.style.display = match ? 'flex' : 'none';
    if (match) visible++;
  });
  var noEl = document.getElementById('idd-no-' + itemId);
  if (noEl) noEl.style.display = (visible === 0 && q) ? 'block' : 'none';
}
function onItemSupChange(itemId, supId) {
  var checked = document.querySelectorAll('[id^="isup-' + itemId + '-"]:checked:not([disabled])');
  var label = document.getElementById('idd-label-' + itemId);
  if (label) {
    if (checked.length === 0) { label.textContent = 'Select suppliers…'; label.style.color = '#94a3b8'; }
    else { label.textContent = Array.from(checked).map(function(c){ return c.dataset.supname || c.value; }).join(', '); label.style.color = '#0f172a'; }
  }
  var anyCheckedForItem = document.querySelectorAll('input[name="item_suppliers[' + itemId + '][]"]:checked:not([disabled])').length > 0;
  var picker = document.getElementById('ichan-item-btn-' + itemId);
  if (picker) { picker.style.opacity = anyCheckedForItem ? '1' : '.35'; picker.style.pointerEvents = anyCheckedForItem ? 'auto' : 'none'; }
  var cb = document.getElementById('isup-' + itemId + '-' + supId);
  if (cb && cb.checked) {
    var chan = _itemChan[itemId] || 'email';
    var inp = document.getElementById('ichan-val-' + supId);
    if (inp) inp.value = chan;
  }
  updateFooter();
}
document.addEventListener('click', function(e) {
  if (_openItemDd === null) return;
  var dd  = document.getElementById('idd-' + _openItemDd);
  var btn = document.getElementById('idd-btn-' + _openItemDd);
  if (dd && !dd.contains(e.target) && btn && !btn.contains(e.target)) closeAllItemDd();
});
function updateFooter() {
  var msg = document.getElementById('sup-footer-msg');
  if (_supTab === 'global') {
    var n = document.querySelectorAll('#sup-list input[type="checkbox"]:checked:not([disabled])').length;
    msg.textContent = n + ' supplier' + (n === 1 ? '' : 's') + ' selected';
  } else {
    var checked = document.querySelectorAll('input[name^="item_suppliers["]:checked:not([disabled])');
    var supIds = new Set();
    checked.forEach(function(c) { supIds.add(c.value); });
    msg.textContent = supIds.size + ' supplier' + (supIds.size === 1 ? '' : 's') + ' assigned';
  }
}
var _rfqRedirect = null;
function submitSuppliers() {
  if (_supTab === 'global') {
    if (document.querySelectorAll('#sup-list input[type="checkbox"]:checked:not([disabled])').length === 0) { showToast('Please select at least one supplier.', 'warn'); return; }
  } else {
    if (document.querySelectorAll('input[name^="item_suppliers["]:checked:not([disabled])').length === 0) { showToast('Please assign at least one supplier to an item.', 'warn'); return; }
  }
  document.getElementById('sup-summary-body').innerHTML = '<div style="text-align:center;padding:40px;color:#64748b;font-size:13px;">Generating links…</div>';
  document.getElementById('sup-modal-title').textContent    = 'Quote Links';
  document.getElementById('sup-modal-subtitle').textContent = 'One-time-use links for each supplier';
  document.getElementById('sup-step2').style.display = 'none';
  document.getElementById('sup-step3').style.display = 'flex';
  var form = document.getElementById('sup-form');
  var CSRF = document.querySelector('meta[name="csrf-token"]').content;
  fetch(form.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: new FormData(form) })
    .then(function(r) { return r.json().then(function(body) { if (!r.ok) return Promise.reject(body); return body; }); })
    .then(function(data) { _rfqRedirect = data.redirect || null; showLinks(data.invitations || []); })
    .catch(function(err) {
      document.getElementById('sup-step3').style.display = 'none';
      document.getElementById('sup-step2').style.display = 'flex';
      showToast((err && err.message) || 'Something went wrong.', 'error');
    });
}
function escHtml(str) { return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
var _chanBadge = {
  email: '<span style="background:#eff6ff;color:#2563eb;padding:3px 10px;border-radius:8px;font-size:11px;font-weight:700;">Email</span>',
  whatsapp: '<span style="background:#f0fdf4;color:#15803d;padding:3px 10px;border-radius:8px;font-size:11px;font-weight:700;">WhatsApp</span>',
  both: '<span style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:8px;font-size:11px;font-weight:700;">Email + WA</span>',
};
function showLinks(invitations) {
  var html = '';
  if (!invitations || invitations.length === 0) {
    html = '<div style="padding:30px;text-align:center;color:#94a3b8;font-size:13px;">No new invitations were created.</div>';
  } else {
    invitations.forEach(function(inv) {
      html += '<div style="margin-bottom:10px;border:1.5px solid #e2e8f0;border-radius:10px;overflow:hidden;">'
        + '<div style="padding:9px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:8px;">'
        + '<span style="font-size:13px;font-weight:700;color:#0f172a;">' + escHtml(inv.supplier_name) + '</span>'
        + (_chanBadge[inv.channel] || '') + '</div>'
        + '<div style="padding:10px 14px;display:flex;align-items:center;gap:8px;">'
        + '<a href="' + escHtml(inv.url) + '" target="_blank" style="flex:1;padding:9px 14px;background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:8px;text-decoration:none;color:#1d4ed8;font-size:12px;font-weight:600;">Open Quote Link</a>'
        + '</div></div>';
    });
  }
  document.getElementById('sup-summary-body').innerHTML = html;
  document.getElementById('sup-modal-title').textContent    = 'Quote Links Ready';
  document.getElementById('sup-modal-subtitle').textContent = 'Share with suppliers via their preferred channel';
  document.getElementById('sup-link-count').textContent = invitations.length + ' link' + (invitations.length === 1 ? '' : 's') + ' generated';
}
function doneWithLinks() { window.location.href = _rfqRedirect || '/app/purchase/pipeline/{{ $pr->id }}'; }
</script>
@endsection
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=RfqSelectSuppliersPageTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Purchase/RfqController.php routes/web.php resources/views/purchase/rfq/select-suppliers.blade.php tests/Feature/Purchase/RfqSelectSuppliersPageTest.php
git commit -m "feat: add standalone Select Suppliers page as an RFQ stopgap"
```

---

## Task 5: Standalone "Record GRN" select page stopgap

**Why:** `x-purchase.select-grn-modal` has no standalone page either — it only exists embedded in `show.blade.php`.

**Files:**
- Modify: `app/Http/Controllers/Purchase/GoodsReceiptNoteController.php` (add `selectPage`)
- Modify: `routes/web.php`
- Create: `resources/views/purchase/grns/select.blade.php`
- Create: `tests/Feature/Purchase/GrnSelectPageTest.php`

**Interfaces:**
- Produces: `GET /purchase/requests/{purchaseRequest}/grn/select` (name: `purchase.requests.grn.select-page`) — lists receivable POs for that request, each linking to the existing `purchase.grns.create` route. Consumed by the React stage-timeline's "Record GRN" link (Task 9/10).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Purchase;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrnSelectPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_requires_authentication(): void
    {
        $pr = PurchaseRequest::factory()->create();

        $this->get(route('purchase.requests.grn.select-page', $pr))->assertRedirect(route('login'));
    }

    public function test_page_lists_receivable_purchase_orders(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $pr = PurchaseRequest::factory()->create(['stage' => 'receiving']);
        $supplier = Supplier::factory()->create(['name' => 'Acme Steel']);
        PurchaseOrder::factory()->create([
            'purchase_request_id' => $pr->id,
            'supplier_id' => $supplier->id,
            'status' => 'sent',
        ]);

        $response = $this->get(route('purchase.requests.grn.select-page', $pr));

        $response->assertOk();
        $response->assertSee('Acme Steel');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=GrnSelectPageTest`
Expected: FAIL — route `purchase.requests.grn.select-page` doesn't exist.

- [ ] **Step 3: Add the controller method**

In `app/Http/Controllers/Purchase/GoodsReceiptNoteController.php`, add (needs `use App\Models\PurchaseRequest;` added to the top-of-file `use` block):

```php
    public function selectPage(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('view', $purchaseRequest);

        $receivablePOs = $purchaseRequest->purchaseOrders()->whereIn('status', ['sent', 'partial'])->with('supplier')->get();

        return view('purchase.grns.select', [
            'pr' => $purchaseRequest,
            'receivablePOs' => $receivablePOs,
        ]);
    }
```

- [ ] **Step 4: Register the route**

In `routes/web.php`, add near the existing `grns.*` and `requests.*` routes (around line 104):

```php
        Route::get('requests/{purchaseRequest}/grn/select', [GoodsReceiptNoteController::class, 'selectPage'])->name('requests.grn.select-page');
```

- [ ] **Step 5: Create the standalone view**

```blade
@extends('layouts.app')

@section('title', 'Record Goods Receipt — ' . $pr->request_number)

@section('content')
<div style="margin-bottom:20px;">
  <a href="/app/purchase/pipeline/{{ $pr->id }}"
     style="font-size:13px;color:#2563eb;text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
    </svg>
    {{ $pr->request_number }}
  </a>
</div>

<div style="background:#fff;border-radius:20px;max-width:520px;margin:0 auto;box-shadow:0 2px 12px rgba(0,0,0,.06);overflow:hidden;">
  <div style="padding:20px 24px;border-bottom:1px solid #f1f5f9;">
    <div style="font-size:17px;font-weight:700;color:#0f172a;">Record Goods Receipt</div>
    <div style="font-size:12px;color:#64748b;margin-top:3px;">Choose the supplier / LPO you're receiving against</div>
  </div>

  <div>
    @forelse($receivablePOs as $po)
    <a href="{{ route('purchase.grns.create', ['purchase_order_id' => $po->id]) }}"
       style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 24px;text-decoration:none;border-bottom:1px solid #f8fafc;">
      <div>
        <div style="font-size:14px;font-weight:700;color:#0f172a;">{{ $po->supplier->name ?? '—' }}</div>
        <div style="font-size:11px;color:#94a3b8;margin-top:2px;">
          {{ $po->po_number ?? 'PO-' . str_pad($po->id, 5, '0', STR_PAD_LEFT) }} · BD {{ number_format($po->total_amount, 3) }}
        </div>
      </div>
      <svg width="16" height="16" fill="none" stroke="#94a3b8" stroke-width="2.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6"/>
      </svg>
    </a>
    @empty
    <div style="padding:40px 24px;text-align:center;color:#94a3b8;font-size:13px;">No outstanding LPOs to receive against.</div>
    @endforelse
  </div>

  <div style="padding:14px 24px;border-top:1px solid #f1f5f9;display:flex;justify-content:flex-end;background:#fafafa;">
    <a href="/app/purchase/pipeline/{{ $pr->id }}"
      style="padding:8px 18px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:600;color:#64748b;background:#fff;text-decoration:none;">
      Cancel
    </a>
  </div>
</div>
@endsection
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=GrnSelectPageTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Purchase/GoodsReceiptNoteController.php routes/web.php resources/views/purchase/grns/select.blade.php tests/Feature/Purchase/GrnSelectPageTest.php
git commit -m "feat: add standalone Record GRN select page as a stopgap"
```

---

## Task 5b: Standalone "Issue LPO" confirm page stopgap

**Why:** Self-review caught this: `PurchaseOrderController::generateFromRequest` (`app/Http/Controllers/Purchase/PurchaseOrderController.php:90`) is POST-only with no GET page either — like RFQ-select and GRN-select, its only UI today is the inline "Issue LPO →" form button embedded in `show.blade.php`. It needs the same stopgap treatment as Tasks 4-5, or the React page's "Issue LPO" link (Task 9/10) has nowhere real to point.

**Files:**
- Modify: `app/Http/Controllers/Purchase/PurchaseOrderController.php` (add `generateFromRequestPage`)
- Modify: `routes/web.php`
- Create: `resources/views/purchase/orders/generate-confirm.blade.php`
- Create: `tests/Feature/Purchase/GenerateLpoPageTest.php`

**Interfaces:**
- Produces: `GET /purchase/requests/{purchaseRequest}/generate-lpo/confirm` (name: `purchase.requests.generate-lpo-page`) — a small confirm page with a button that POSTs to the existing `purchase.requests.generate-lpo` route. Consumed by the "Issue LPO" link in Task 9/10's `StageAction`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Purchase;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateLpoPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_requires_generate_lpo_permission(): void
    {
        $user = User::factory()->create();
        $pr = PurchaseRequest::factory()->create(['stage' => 'lpo']);

        $this->actingAs($user)
            ->get(route('purchase.requests.generate-lpo-page', $pr))
            ->assertForbidden();
    }

    public function test_page_shows_the_request_number_and_a_confirm_button(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('purchase-requests.generate-lpo');
        $this->actingAs($user);
        $pr = PurchaseRequest::factory()->create(['stage' => 'lpo', 'request_number' => 'MPR26-0099']);

        $response = $this->get(route('purchase.requests.generate-lpo-page', $pr));

        $response->assertOk();
        $response->assertSee('MPR26-0099');
        $response->assertSee(route('purchase.requests.generate-lpo', $pr), false);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=GenerateLpoPageTest`
Expected: FAIL — route `purchase.requests.generate-lpo-page` doesn't exist.

- [ ] **Step 3: Add the controller method**

In `app/Http/Controllers/Purchase/PurchaseOrderController.php`, add (near `generateFromRequest`, reusing its existing `use App\Models\PurchaseRequest;` import):

```php
    public function generateFromRequestPage(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('generateLpo', $purchaseRequest);

        return view('purchase.orders.generate-confirm', ['pr' => $purchaseRequest]);
    }
```

- [ ] **Step 4: Register the route**

In `routes/web.php`, add directly above the existing `requests/{purchaseRequest}/generate-lpo` POST route (around line 100):

```php
        Route::get('requests/{purchaseRequest}/generate-lpo/confirm', [PurchaseOrderController::class, 'generateFromRequestPage'])->name('requests.generate-lpo-page');
```

- [ ] **Step 5: Create the view**

```blade
@extends('layouts.app')

@section('title', 'Issue LPO — ' . $pr->request_number)

@section('content')
<div style="margin-bottom:20px;">
  <a href="/app/purchase/pipeline/{{ $pr->id }}"
     style="font-size:13px;color:#2563eb;text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
    </svg>
    {{ $pr->request_number }}
  </a>
</div>

<div style="background:#fff;border-radius:20px;max-width:480px;margin:0 auto;box-shadow:0 2px 12px rgba(0,0,0,.06);overflow:hidden;">
  <div style="padding:24px;">
    <div style="font-size:17px;font-weight:700;color:#0f172a;margin-bottom:6px;">Issue LPO</div>
    <p style="font-size:13px;color:#64748b;margin:0 0 20px;">
      This generates a Local Purchase Order for each supplier with awarded items on {{ $pr->request_number }}, and moves the request to the Receiving stage.
    </p>
    <form action="{{ route('purchase.requests.generate-lpo', $pr) }}" method="POST">
      @csrf
      <div style="display:flex;gap:10px;">
        <a href="/app/purchase/pipeline/{{ $pr->id }}"
          style="flex:1;text-align:center;padding:10px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:600;color:#64748b;background:#f8fafc;text-decoration:none;">
          Cancel
        </a>
        <button type="submit"
          style="flex:2;padding:10px;border:none;border-radius:8px;font-size:13px;font-weight:700;color:#fff;background:#16a34a;cursor:pointer;">
          Issue LPO →
        </button>
      </div>
    </form>
  </div>
</div>
@endsection
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=GenerateLpoPageTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Purchase/PurchaseOrderController.php routes/web.php resources/views/purchase/orders/generate-confirm.blade.php tests/Feature/Purchase/GenerateLpoPageTest.php
git commit -m "feat: add standalone Issue LPO confirm page as a stopgap"
```

---

## Task 6: Delete the old Blade detail page, route, and controller method

**Files:**
- Delete: `resources/views/purchase/pipeline/show.blade.php`
- Modify: `app/Http/Controllers/Purchase/PurchasePipelineController.php` (remove `show()`)
- Modify: `routes/web.php` (remove the `pipeline/{purchaseRequest}` route)
- Modify: `tests/Feature/Purchase/PurchasePipelineScopingTest.php` (remove the two tests that hit the deleted route; the index-redirect test stays)

**Interfaces:**
- Consumes: nothing (this task only removes).
- Produces: `purchase.pipeline.show` no longer exists anywhere in the app — Task 3 already repointed every consumer.

- [ ] **Step 1: Confirm no remaining references before deleting**

Run: `grep -rn "purchase.pipeline.show\|PurchasePipelineController::show\|pipeline\.show" app resources routes tests`
Expected: only the definitions being deleted in this task show up (the route line in `routes/web.php`, the `show()` method in the Blade controller, and the two tests in `PurchasePipelineScopingTest.php` that call `route('purchase.pipeline.show', ...)`). If anything else appears, stop and fix it first — it means Task 3 missed a reference.

- [ ] **Step 2: Remove the two now-obsolete tests**

In `tests/Feature/Purchase/PurchasePipelineScopingTest.php`, delete these two methods (their coverage now lives in `tests/Feature/Api/Purchase/PurchasePipelineShowTest.php` from Task 1):

```php
    public function test_user_without_view_permission_cannot_open_a_single_request(): void
    {
        $user = User::factory()->create();
        $pr = PurchaseRequest::factory()->create();

        $this->actingAs($user)->get(route('purchase.pipeline.show', $pr))->assertForbidden();
    }

    public function test_requester_can_open_their_own_request(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $pr = PurchaseRequest::factory()->create(['requested_by' => $requester->id]);

        $this->actingAs($requester)->get(route('purchase.pipeline.show', $pr))->assertOk();
    }
```

Leave `test_pipeline_index_redirects_to_the_react_board` and its class/imports in place.

- [ ] **Step 3: Remove the route**

In `routes/web.php`, delete this line (around line 78):

```php
        Route::get('pipeline/{purchaseRequest}', [PurchasePipelineController::class, 'show'])->name('pipeline.show');
```

- [ ] **Step 4: Remove the controller method**

In `app/Http/Controllers/Purchase/PurchasePipelineController.php`, delete the entire `show()` method (lines 37-58 as read at plan-writing time — re-check current line numbers before editing). After removal, check whether `Supplier` and `PurchaseStageService` are still used anywhere else in the file (they were only used inside `show()`); if not, remove their now-unused `use` statements too. `withRelations()` stays — it's still used elsewhere in this controller/class hierarchy... actually verify: if `withRelations()` was only called from the now-deleted `show()` and nowhere else in this file, delete it as dead code too (grep `withRelations` across the codebase first to be sure nothing else calls it).

- [ ] **Step 5: Delete the view**

```bash
git rm resources/views/purchase/pipeline/show.blade.php
```

- [ ] **Step 6: Run the full Purchase Blade test suite**

Run: `php artisan test --filter=Purchase`
Expected: PASS — `PurchasePipelineScopingTest` now only has the redirect test, and nothing else references the deleted route/view/method.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "refactor: delete the Blade Pipeline detail page — superseded by the React page"
```

---

## Task 7: `StagePill` shared UI component

**Files:**
- Create: `resources/js-app/components/ui/StagePill.jsx`
- Create: `resources/js-app/components/ui/StagePill.test.jsx`

**Interfaces:**
- Produces: `export default function StagePill({ stage, label })` — renders a colored pill, green when `stage === 'complete'`, amber otherwise, showing `label`. Consumed by Task 9/10's `PipelinePage`.

- [ ] **Step 1: Write the failing test**

```jsx
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import StagePill from './StagePill';

describe('StagePill', () => {
    it('renders the given label', () => {
        render(<StagePill stage="rfq" label="RFQ" />);
        expect(screen.getByText('RFQ')).toBeInTheDocument();
    });

    it('uses a green background for the complete stage', () => {
        render(<StagePill stage="complete" label="Complete" />);
        const pill = screen.getByText('Complete');
        expect(pill).toHaveStyle({ background: '#dcfce7' });
    });

    it('uses an amber background for any non-complete stage', () => {
        render(<StagePill stage="draft" label="Draft" />);
        const pill = screen.getByText('Draft');
        expect(pill).toHaveStyle({ background: '#fffbeb' });
    });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx vitest run resources/js-app/components/ui/StagePill.test.jsx`
Expected: FAIL — `StagePill.jsx` doesn't exist.

- [ ] **Step 3: Implement the component**

```jsx
export default function StagePill({ stage, label }) {
    const isComplete = stage === 'complete';
    return (
        <span style={{
            fontSize: 11, fontWeight: 700, padding: '4px 12px', borderRadius: 20,
            background: isComplete ? '#dcfce7' : '#fffbeb',
            color: isComplete ? '#15803d' : '#92400e',
        }}>
            {label}
        </span>
    );
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `npx vitest run resources/js-app/components/ui/StagePill.test.jsx`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/js-app/components/ui/StagePill.jsx resources/js-app/components/ui/StagePill.test.jsx
git commit -m "feat: add shared StagePill UI component"
```

---

## Task 8: `SignaturePad` component

**Files:**
- Create: `resources/js-app/components/purchase/SignaturePad.jsx`
- Create: `resources/js-app/components/purchase/SignaturePad.test.jsx`

**Interfaces:**
- Consumes: `apiPost` (`resources/js-app/api/client.js:63`), `useToast` (`resources/js-app/components/ui/Toast.jsx`), `Modal` (`resources/js-app/components/ui/Modal.jsx`).
- Produces: `export default function SignaturePad({ open, purchaseRequestId, existingSignature, onClose, onSigned })` — `existingSignature` is `{ signature_image, signed_by_name, signed_at } | null`; when present, shows a read-only view instead of the canvas; `onSigned(signatureData)` fires after a successful POST to `/purchase/requests/{id}/sign`. Consumed by `PipelinePage` (Tasks 9/10/11).

- [ ] **Step 1: Write the failing tests**

```jsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { ToastProvider } from '../ui/Toast';
import SignaturePad from './SignaturePad';
import * as client from '../../api/client';

describe('SignaturePad', () => {
    it('shows the existing signature image when already signed, with no canvas', () => {
        render(
            <ToastProvider>
                <SignaturePad
                    open
                    purchaseRequestId={7}
                    existingSignature={{ signature_image: 'data:image/png;base64,xyz', signed_by_name: 'Jane Doe', signed_at: '2026-08-01T10:00:00Z' }}
                    onClose={() => {}}
                    onSigned={() => {}}
                />
            </ToastProvider>
        );
        expect(screen.getByText(/Jane Doe/)).toBeInTheDocument();
        expect(document.querySelector('canvas')).not.toBeInTheDocument();
    });

    it('renders a blank canvas and a Confirm button when not yet signed', () => {
        render(
            <ToastProvider>
                <SignaturePad open purchaseRequestId={7} existingSignature={null} onClose={() => {}} onSigned={() => {}} />
            </ToastProvider>
        );
        expect(document.querySelector('canvas')).toBeInTheDocument();
        expect(screen.getByText(/Confirm Signature/)).toBeInTheDocument();
    });

    it('warns via toast instead of submitting when nothing was drawn', () => {
        render(
            <ToastProvider>
                <SignaturePad open purchaseRequestId={7} existingSignature={null} onClose={() => {}} onSigned={() => {}} />
            </ToastProvider>
        );
        const postSpy = vi.spyOn(client, 'apiPost');
        fireEvent.click(screen.getByText(/Confirm Signature/));
        expect(postSpy).not.toHaveBeenCalled();
    });

    it('posts the captured signature and calls onSigned on success', async () => {
        const onSigned = vi.fn();
        vi.spyOn(client, 'apiPost').mockResolvedValue({
            data: { signature_image: 'data:image/png;base64,abc', signed_by_name: 'Jane Doe', signed_at: '2026-08-03T10:00:00Z' },
        });

        render(
            <ToastProvider>
                <SignaturePad open purchaseRequestId={7} existingSignature={null} onClose={() => {}} onSigned={onSigned} />
            </ToastProvider>
        );

        const canvas = document.querySelector('canvas');
        fireEvent.mouseDown(canvas, { clientX: 10, clientY: 10 });
        fireEvent.mouseMove(canvas, { clientX: 20, clientY: 20 });
        fireEvent.mouseUp(canvas);

        fireEvent.click(screen.getByText(/Confirm Signature/));

        await waitFor(() => expect(onSigned).toHaveBeenCalled());
        expect(client.apiPost).toHaveBeenCalledWith('/purchase/requests/7/sign', expect.objectContaining({ signature_image: expect.stringContaining('data:image') }));
    });
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npx vitest run resources/js-app/components/purchase/SignaturePad.test.jsx`
Expected: FAIL — `SignaturePad.jsx` doesn't exist.

- [ ] **Step 3: Implement the component**

```jsx
import { useEffect, useRef, useState } from 'react';
import Modal from '../ui/Modal';
import { apiPost } from '../../api/client';
import { useToast } from '../ui/Toast';

export default function SignaturePad({ open, purchaseRequestId, existingSignature, onClose, onSigned }) {
    const canvasRef = useRef(null);
    const drawingRef = useRef(false);
    const hasMarkRef = useRef(false);
    const [hasMark, setHasMark] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const { showToast } = useToast();

    useEffect(() => {
        if (!open || existingSignature) return;
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#1e293b';

        function pos(e) {
            const rect = canvas.getBoundingClientRect();
            return {
                x: (e.clientX - rect.left) * (canvas.width / rect.width),
                y: (e.clientY - rect.top) * (canvas.height / rect.height),
            };
        }
        function start(e) {
            drawingRef.current = true;
            const p = pos(e);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
            hasMarkRef.current = true;
            setHasMark(true);
        }
        function move(e) {
            if (!drawingRef.current) return;
            const p = pos(e);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
        }
        function end() { drawingRef.current = false; }

        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        canvas.addEventListener('mouseup', end);
        canvas.addEventListener('mouseleave', end);
        canvas.addEventListener('touchstart', (e) => { e.preventDefault(); start(e.touches[0]); }, { passive: false });
        canvas.addEventListener('touchmove', (e) => { e.preventDefault(); move(e.touches[0]); }, { passive: false });
        canvas.addEventListener('touchend', end);

        return () => {
            canvas.removeEventListener('mousedown', start);
            canvas.removeEventListener('mousemove', move);
            canvas.removeEventListener('mouseup', end);
            canvas.removeEventListener('mouseleave', end);
        };
    }, [open, existingSignature]);

    function clear() {
        const canvas = canvasRef.current;
        if (!canvas) return;
        canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
        hasMarkRef.current = false;
        setHasMark(false);
    }

    function submit() {
        if (!hasMarkRef.current) {
            showToast('Please draw your signature first.', 'warn');
            return;
        }
        const dataUrl = canvasRef.current.toDataURL('image/png');
        setSubmitting(true);
        apiPost(`/purchase/requests/${purchaseRequestId}/sign`, { signature_image: dataUrl })
            .then((res) => onSigned(res.data))
            .catch((err) => showToast(err.message || 'Failed to save signature.', 'error'))
            .finally(() => setSubmitting(false));
    }

    return (
        <Modal open={open} title="GM Digital Signature" onClose={onClose}>
            {existingSignature ? (
                <div style={{ textAlign: 'center' }}>
                    <img src={existingSignature.signature_image} alt="Signature" style={{ maxWidth: '100%', border: '1px solid #e2e8f0', borderRadius: 10, background: '#f8fafc' }} />
                    <div style={{ fontSize: 12, color: '#64748b', marginTop: 10 }}>
                        Signed by <strong>{existingSignature.signed_by_name ?? '—'}</strong>
                        {existingSignature.signed_at && ` on ${new Date(existingSignature.signed_at).toLocaleString()}`}
                    </div>
                </div>
            ) : (
                <>
                    <p style={{ fontSize: 13, color: '#475569', margin: '0 0 14px' }}>
                        Draw your signature below to approve this purchase request. This is recorded with your name, timestamp, and IP.
                    </p>
                    <canvas
                        ref={canvasRef}
                        width={510}
                        height={180}
                        style={{ width: '100%', border: '2px dashed #cbd5e1', borderRadius: 10, cursor: 'crosshair', touchAction: 'none', background: '#fafafa', display: 'block' }}
                    />
                    <div style={{ display: 'flex', gap: 10, marginTop: 14 }}>
                        <button type="button" onClick={clear} style={{ flex: 1, padding: 11, border: '1.5px solid #e2e8f0', borderRadius: 8, fontSize: 13, fontWeight: 600, color: '#64748b', background: '#f8fafc', cursor: 'pointer' }}>
                            Clear
                        </button>
                        <button type="button" onClick={submit} disabled={submitting || !hasMark} style={{ flex: 2, padding: 11, border: 'none', borderRadius: 8, fontSize: 13, fontWeight: 700, color: '#fff', background: 'linear-gradient(135deg,#7c3aed,#4f46e5)', cursor: 'pointer' }}>
                            {submitting ? 'Saving…' : 'Confirm Signature →'}
                        </button>
                    </div>
                </>
            )}
        </Modal>
    );
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `npx vitest run resources/js-app/components/purchase/SignaturePad.test.jsx`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/js-app/components/purchase/SignaturePad.jsx resources/js-app/components/purchase/SignaturePad.test.jsx
git commit -m "feat: add SignaturePad canvas component"
```

---

## Task 9: Desktop `PipelinePage` — read-only shell

**Files:**
- Create: `resources/js-app/pages/desktop/purchase/PipelinePage.jsx`
- Create: `resources/js-app/pages/desktop/purchase/PipelinePage.test.jsx`

**Interfaces:**
- Consumes: `apiGet` (`resources/js-app/api/client.js:62`), `StagePill` (Task 7), `useParams` from `react-router-dom`.
- Produces: `export default function PipelinePage()` — reads `:id` from the route, fetches `GET /purchase/pipeline/{id}`, renders header + stage timeline + sidebar. No signature capture or live updates yet (Tasks 11/12 add those on top).

- [ ] **Step 1: Write the failing test**

```jsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ToastProvider } from '../../../components/ui/Toast';
import PipelinePage from './PipelinePage';
import * as client from '../../../api/client';

function renderAt(id) {
    return render(
        <ToastProvider>
            <MemoryRouter initialEntries={[`/app/purchase/pipeline/${id}`]}>
                <Routes>
                    <Route path="/app/purchase/pipeline/:id" element={<PipelinePage />} />
                </Routes>
            </MemoryRouter>
        </ToastProvider>
    );
}

const BASE_DETAIL = {
    id: 12, request_number: 'MPR26-0012', stage: 'quoting', stage_index: 3, progress_pct: 37,
    project_name: 'Warehouse Racking', department: 'Ops', requested_by_name: 'Jane Doe', date: '2026-08-01',
    location: 'Site A', required_date_text: '15 Aug 2026', verified_by_name: null, status: 'approved',
    signature: null, items: [], rfq_invitations: [], supplier_quotes: [], purchase_orders: [],
    permissions: { update: false, approve: false, manageRfq: false, manageQuotes: true, award: false, generateLpo: false },
};

describe('PipelinePage (desktop)', () => {
    it('fetches by id from the route and renders the header', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: BASE_DETAIL });
        renderAt(12);
        await waitFor(() => expect(client.apiGet).toHaveBeenCalledWith('/purchase/pipeline/12'));
        expect(screen.getByText('MPR26-0012')).toBeInTheDocument();
        expect(screen.getByText('Warehouse Racking')).toBeInTheDocument();
    });

    it('renders every stage in the timeline with the current one highlighted', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: BASE_DETAIL });
        renderAt(12);
        await waitFor(() => expect(screen.getByText('MPR26-0012')).toBeInTheDocument());
        ['Purchase Request', 'GM Signature', 'Select Suppliers', 'Awaiting Quotes', 'Quote Comparison', 'LPO Issued', 'Receiving Materials', 'Payment', 'Complete']
            .forEach((label) => expect(screen.getByText(label)).toBeInTheDocument());
    });

    it('links "View Quotes" out to the existing Blade quotes page for the current stage', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: BASE_DETAIL });
        renderAt(12);
        await waitFor(() => expect(screen.getByText('MPR26-0012')).toBeInTheDocument());
        expect(screen.getByText(/View Quotes/).closest('a')).toHaveAttribute('href', '/purchase/requests/12/quotes');
    });

    it('renders sidebar sections only when their data is present', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: {
                ...BASE_DETAIL,
                rfq_invitations: [{ id: 1, supplier_id: 1, supplier_name: 'Acme Steel', supplier_phone: null, channel: 'email', status: 'submitted' }],
                supplier_quotes: [{ id: 1, supplier_id: 1, supplier_name: 'Acme Steel', total_amount: '120.500', submitted_at: '2026-08-02T10:00:00Z', lead_time_days: 5, payment_terms: 'Net 30', notes: null, has_awarded_items: false, items: [] }],
            },
        });
        renderAt(12);
        await waitFor(() => expect(screen.getByText('MPR26-0012')).toBeInTheDocument());
        expect(screen.getByText(/Suppliers \(1\)/)).toBeInTheDocument();
        expect(screen.getByText(/Quotes \(1\)/)).toBeInTheDocument();
        expect(screen.queryByText(/LPOs/)).not.toBeInTheDocument();
    });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx vitest run resources/js-app/pages/desktop/purchase/PipelinePage.test.jsx`
Expected: FAIL — `PipelinePage.jsx` doesn't exist.

- [ ] **Step 3: Implement the page**

```jsx
import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { apiGet } from '../../../api/client';
import { useToast } from '../../../components/ui/Toast';
import StagePill from '../../../components/ui/StagePill';

const STAGES = ['draft', 'gm_approval', 'rfq', 'quoting', 'comparison', 'lpo', 'receiving', 'payment', 'complete'];
const STAGE_LABELS = {
    draft: 'Purchase Request', gm_approval: 'GM Signature', rfq: 'Select Suppliers', quoting: 'Awaiting Quotes',
    comparison: 'Quote Comparison', lpo: 'LPO Issued', receiving: 'Receiving Materials', payment: 'Payment', complete: 'Complete',
};

function stageSubtext(stage, pr) {
    if (stage === 'draft') return `Created by ${pr.requested_by_name ?? '—'}`;
    if (stage === 'gm_approval') return pr.signature ? `Signed by ${pr.signature.signed_by_name ?? '—'}` : 'Awaiting GM signature';
    if (stage === 'rfq') return pr.rfq_invitations.length ? `${pr.rfq_invitations.length} supplier(s) selected` : 'Select suppliers to receive quote requests';
    if (stage === 'quoting') return `${pr.supplier_quotes.length} quote(s) received`;
    if (stage === 'comparison') return `${pr.supplier_quotes.length} quote(s) ready to compare`;
    return null;
}

function StageAction({ stage, current, pr, onOpenSignature }) {
    if (!current) return null;
    const linkStyle = { fontSize: 12, fontWeight: 700, padding: '6px 14px', borderRadius: 7, textDecoration: 'none', color: '#fff', background: '#f59e0b' };
    if (stage === 'draft' || stage === 'gm_approval') {
        if (!pr.permissions.approve) return null;
        return <button type="button" onClick={onOpenSignature} style={{ ...linkStyle, background: '#7c3aed', border: 'none', cursor: 'pointer' }}>{pr.signature ? 'View Signature' : 'Sign'}</button>;
    }
    if (stage === 'rfq' && pr.permissions.manageRfq) {
        return <a href={`/purchase/requests/${pr.id}/rfq/select-suppliers`} style={{ ...linkStyle, background: '#2563eb' }}>+ Add Suppliers</a>;
    }
    if (stage === 'quoting' && pr.permissions.manageQuotes) {
        return <a href={`/purchase/requests/${pr.id}/quotes`} style={linkStyle}>View Quotes ({pr.supplier_quotes.length}) →</a>;
    }
    if (stage === 'comparison' && pr.permissions.manageQuotes) {
        return <a href={`/purchase/requests/${pr.id}/compare`} style={linkStyle}>Compare & Award →</a>;
    }
    if (stage === 'lpo' && pr.permissions.generateLpo) {
        return <a href={`/purchase/requests/${pr.id}/generate-lpo/confirm`} style={{ ...linkStyle, background: '#16a34a' }}>Issue LPO →</a>;
    }
    if (stage === 'receiving') {
        return <a href={`/purchase/requests/${pr.id}/grn/select`} style={{ ...linkStyle, background: '#16a34a' }}>Record GRN →</a>;
    }
    if (stage === 'payment') {
        return <a href={`/purchase/payments/create`} style={{ ...linkStyle, background: '#0f172a' }}>Issue Payment →</a>;
    }
    return null;
}

export default function PipelinePage() {
    const { id } = useParams();
    const [pr, setPr] = useState(null);
    const { showToast } = useToast();

    useEffect(() => {
        apiGet(`/purchase/pipeline/${id}`)
            .then((res) => setPr(res.data))
            .catch(() => showToast('Failed to load this request.', 'error'));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    if (!pr) return null;

    const stageIdx = pr.stage_index;

    return (
        <div>
            <div style={{ background: '#fff', borderRadius: 16, boxShadow: '0 2px 12px rgba(0,0,0,.06)', overflow: 'hidden', marginBottom: 20 }}>
                <div style={{ padding: '20px 24px', display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 12 }}>
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                            <h1 style={{ fontSize: 20, fontWeight: 700, color: '#0f172a', margin: 0 }}>{pr.request_number}</h1>
                            <StagePill stage={pr.stage} label={STAGE_LABELS[pr.stage] ?? pr.stage} />
                        </div>
                        <div style={{ fontSize: 13, color: '#64748b', marginTop: 6, display: 'flex', flexWrap: 'wrap', gap: 14 }}>
                            {pr.project_name && <span>📁 {pr.project_name}</span>}
                            {pr.department && <span>🏢 {pr.department}</span>}
                            {pr.requested_by_name && <span>👤 {pr.requested_by_name}</span>}
                            {pr.date && <span>📅 {pr.date}</span>}
                        </div>
                    </div>
                    <a href={`/purchase/requests/${pr.id}`} style={{ fontSize: 12, color: '#64748b', textDecoration: 'none', border: '1px solid #e2e8f0', padding: '6px 14px', borderRadius: 7 }}>
                        View Full Request →
                    </a>
                </div>
                <div style={{ height: 4, background: '#f1f5f9' }}>
                    <div style={{ height: 4, background: pr.stage === 'complete' ? '#22c55e' : '#f59e0b', width: `${pr.progress_pct}%` }} />
                </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 320px', gap: 20, alignItems: 'start' }}>
                <div style={{ background: '#fff', borderRadius: 16, boxShadow: '0 2px 12px rgba(0,0,0,.06)', padding: 24 }}>
                    <h2 style={{ fontSize: 14, fontWeight: 700, color: '#0f172a', margin: '0 0 20px' }}>Pipeline Stages</h2>
                    <div>
                        {STAGES.map((stage, i) => {
                            const done = i < stageIdx;
                            const current = i === stageIdx;
                            return (
                                <div key={stage} style={{ display: 'flex', gap: 16 }}>
                                    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', width: 20, flexShrink: 0 }}>
                                        <div style={{ width: 18, height: 18, borderRadius: '50%', background: done ? '#2563eb' : current ? '#f59e0b' : '#e2e8f0' }} />
                                        {i < STAGES.length - 1 && <div style={{ width: 2, flex: 1, minHeight: 12, background: done ? '#2563eb' : '#e2e8f0' }} />}
                                    </div>
                                    <div style={{ flex: 1, paddingBottom: 16 }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8, flexWrap: 'wrap' }}>
                                            <div>
                                                <div style={{ fontSize: 14, fontWeight: current ? 700 : done ? 600 : 400, color: done ? '#1d4ed8' : current ? '#d97706' : '#94a3b8' }}>
                                                    {STAGE_LABELS[stage]}
                                                </div>
                                                {(done || current) && <div style={{ fontSize: 12, color: '#94a3b8', marginTop: 2 }}>{stageSubtext(stage, pr)}</div>}
                                            </div>
                                            <StageAction stage={stage} current={current} pr={pr} onOpenSignature={() => {}} />
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <div style={{ background: '#fff', borderRadius: 14, boxShadow: '0 2px 10px rgba(0,0,0,.05)', padding: 20 }}>
                        <h3 style={{ fontSize: 13, fontWeight: 700, color: '#0f172a', margin: '0 0 14px' }}>Request Details</h3>
                        {pr.location && <div>Location: {pr.location}</div>}
                        {pr.required_date_text && <div>Required By: {pr.required_date_text}</div>}
                        <div>Status: {pr.status ?? '—'}</div>
                    </div>

                    {pr.rfq_invitations.length > 0 && (
                        <div style={{ background: '#fff', borderRadius: 14, boxShadow: '0 2px 10px rgba(0,0,0,.05)', padding: 20 }}>
                            <h3 style={{ fontSize: 13, fontWeight: 700, color: '#0f172a', margin: '0 0 14px' }}>Suppliers ({pr.rfq_invitations.length})</h3>
                            {pr.rfq_invitations.map((inv) => <div key={inv.id}>{inv.supplier_name} — {inv.status}</div>)}
                        </div>
                    )}

                    {pr.supplier_quotes.length > 0 && (
                        <div style={{ background: '#fff', borderRadius: 14, boxShadow: '0 2px 10px rgba(0,0,0,.05)', padding: 20 }}>
                            <h3 style={{ fontSize: 13, fontWeight: 700, color: '#0f172a', margin: '0 0 14px' }}>Quotes ({pr.supplier_quotes.length})</h3>
                            {pr.supplier_quotes.map((q) => <div key={q.id}>{q.supplier_name} — BD {q.total_amount}</div>)}
                        </div>
                    )}

                    {pr.purchase_orders.length > 0 && (
                        <div style={{ background: '#fff', borderRadius: 14, boxShadow: '0 2px 10px rgba(0,0,0,.05)', padding: 20 }}>
                            <h3 style={{ fontSize: 13, fontWeight: 700, color: '#0f172a', margin: '0 0 14px' }}>LPOs ({pr.purchase_orders.length})</h3>
                            {pr.purchase_orders.map((po) => <div key={po.id}>{po.po_number} — BD {po.total_amount}</div>)}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `npx vitest run resources/js-app/pages/desktop/purchase/PipelinePage.test.jsx`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/js-app/pages/desktop/purchase/PipelinePage.jsx resources/js-app/pages/desktop/purchase/PipelinePage.test.jsx
git commit -m "feat: add desktop Pipeline detail page (read-only shell)"
```

---

## Task 10: Mobile `PipelinePage`

**Files:**
- Create: `resources/js-app/pages/mobile/purchase/PipelinePage.jsx`
- Create: `resources/js-app/pages/mobile/purchase/PipelinePage.test.jsx`

**Interfaces:**
- Same props/behavior contract as Task 9's desktop page (same API shape, same route param). Layout differs: header stays full-width/compact, sidebar cards stack full-width below the timeline instead of beside it.

- [ ] **Step 1: Write the failing test**

Copy Task 9's test file to `resources/js-app/pages/mobile/purchase/PipelinePage.test.jsx`, changing only the import path (`from './PipelinePage'` stays the same, it's colocated) — no other changes needed since the API contract and rendered text content are identical between shells.

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx vitest run resources/js-app/pages/mobile/purchase/PipelinePage.test.jsx`
Expected: FAIL — file doesn't exist.

- [ ] **Step 3: Implement the mobile page**

Same component logic as Task 9's `PipelinePage.jsx`, with only the outer layout changed: the two-column `display:grid;grid-template-columns:1fr 320px` wrapper becomes a single-column `display:flex;flex-direction:column;gap:16px` wrapper, so the timeline card and the sidebar cards stack top-to-bottom instead of side-by-side. Copy the full file from Task 9 and make this one change:

```jsx
            <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                <div style={{ background: '#fff', borderRadius: 16, boxShadow: '0 2px 12px rgba(0,0,0,.06)', padding: 24 }}>
                    {/* ...identical Pipeline Stages timeline content from the desktop version... */}
                </div>

                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    {/* ...identical sidebar cards content from the desktop version... */}
                </div>
            </div>
```

(i.e. `gridTemplateColumns: '1fr 320px'` → removed, `display: 'grid'` → `display: 'flex'`, `flexDirection: 'column'` added; everything else in the file is byte-for-byte the same as `pages/desktop/purchase/PipelinePage.jsx`.)

- [ ] **Step 4: Run the test to verify it passes**

Run: `npx vitest run resources/js-app/pages/mobile/purchase/PipelinePage.test.jsx`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/js-app/pages/mobile/purchase/PipelinePage.jsx resources/js-app/pages/mobile/purchase/PipelinePage.test.jsx
git commit -m "feat: add mobile Pipeline detail page (read-only shell)"
```

---

## Task 11: Wire `SignaturePad` into both `PipelinePage` shells

**Files:**
- Modify: `resources/js-app/pages/desktop/purchase/PipelinePage.jsx`
- Modify: `resources/js-app/pages/desktop/purchase/PipelinePage.test.jsx`
- Modify: `resources/js-app/pages/mobile/purchase/PipelinePage.jsx`
- Modify: `resources/js-app/pages/mobile/purchase/PipelinePage.test.jsx`

**Interfaces:**
- Consumes: `SignaturePad` (Task 8).
- Produces: clicking "Sign"/"View Signature" opens `SignaturePad`; a successful sign updates `pr.signature` and `pr.stage`/`pr.stage_index`/`pr.progress_pct` in local state without a refetch (the API response from Task 2 doesn't include the new stage, so advance it client-side: `draft`/`gm_approval` → `rfq`, matching `PurchaseStageService::advance()`).

- [ ] **Step 1: Write the failing test (add to both `PipelinePage.test.jsx` files)**

```jsx
    it('opens the signature pad when Sign is clicked, and reflects a successful signature', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: { ...BASE_DETAIL, stage: 'draft', stage_index: 0, permissions: { ...BASE_DETAIL.permissions, approve: true } },
        });
        vi.spyOn(client, 'apiPost').mockResolvedValue({
            data: { signature_image: 'data:image/png;base64,abc', signed_by_name: 'Jane Doe', signed_at: '2026-08-03T10:00:00Z' },
        });

        renderAt(12);
        await waitFor(() => expect(screen.getByText('MPR26-0012')).toBeInTheDocument());

        fireEvent.click(screen.getByText('Sign'));
        expect(document.querySelector('canvas')).toBeInTheDocument();

        const canvas = document.querySelector('canvas');
        fireEvent.mouseDown(canvas, { clientX: 10, clientY: 10 });
        fireEvent.mouseMove(canvas, { clientX: 20, clientY: 20 });
        fireEvent.mouseUp(canvas);
        fireEvent.click(screen.getByText(/Confirm Signature/));

        await waitFor(() => expect(screen.getByText(/Jane Doe/)).toBeInTheDocument());
    });
```

(Add this inside the existing `describe(...)` block in both test files; both need `fireEvent` already imported from the earlier test setup in Task 9/10.)

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npx vitest run resources/js-app/pages/desktop/purchase/PipelinePage.test.jsx resources/js-app/pages/mobile/purchase/PipelinePage.test.jsx`
Expected: FAIL — clicking "Sign" does nothing yet (`onOpenSignature={() => {}}` is a no-op stub).

- [ ] **Step 3: Wire the component in (apply to both files identically)**

Add the import and state, and replace the stubbed handler:

```jsx
import SignaturePad from '../../../components/purchase/SignaturePad';
```

```jsx
    const [signOpen, setSignOpen] = useState(false);
```

Replace `<StageAction stage={stage} current={current} pr={pr} onOpenSignature={() => {}} />` with:

```jsx
                                            <StageAction stage={stage} current={current} pr={pr} onOpenSignature={() => setSignOpen(true)} />
```

And right after the closing `</div>` of the two-column/single-column layout wrapper (before the component's final closing `</div>`), add:

```jsx
            <SignaturePad
                open={signOpen}
                purchaseRequestId={pr.id}
                existingSignature={pr.signature}
                onClose={() => setSignOpen(false)}
                onSigned={(signature) => {
                    setSignOpen(false);
                    setPr((prev) => {
                        if (prev.stage !== 'draft' && prev.stage !== 'gm_approval') return { ...prev, signature };
                        const nextIdx = prev.stage_index + 1;
                        return {
                            ...prev,
                            signature,
                            stage: 'rfq',
                            stage_index: nextIdx,
                            progress_pct: Math.round((nextIdx / (STAGES.length - 1)) * 100),
                        };
                    });
                }}
            />
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `npx vitest run resources/js-app/pages/desktop/purchase/PipelinePage.test.jsx resources/js-app/pages/mobile/purchase/PipelinePage.test.jsx`
Expected: PASS (5 tests each).

- [ ] **Step 5: Commit**

```bash
git add resources/js-app/pages/desktop/purchase/PipelinePage.jsx resources/js-app/pages/desktop/purchase/PipelinePage.test.jsx resources/js-app/pages/mobile/purchase/PipelinePage.jsx resources/js-app/pages/mobile/purchase/PipelinePage.test.jsx
git commit -m "feat: wire GM signature capture into the Pipeline detail page"
```

---

## Task 12: Live updates via `purchase-request.stage-changed`

**Files:**
- Modify: `resources/js-app/pages/desktop/purchase/PipelinePage.jsx`
- Modify: `resources/js-app/pages/desktop/purchase/PipelinePage.test.jsx`
- Modify: `resources/js-app/pages/mobile/purchase/PipelinePage.jsx`
- Modify: `resources/js-app/pages/mobile/purchase/PipelinePage.test.jsx`

**Interfaces:**
- Consumes: `echo` (`resources/js-app/echo.js`), the existing `purchase-request.stage-changed` broadcast on the `purchase` private channel (payload `{id, request_number, stage}` — `app/Events/PurchaseRequestStageChanged.php:28`).
- Produces: when that event fires for this request's `id`, the page refetches `/purchase/pipeline/{id}` (the payload doesn't carry the full detail, so a merge would be wrong — Task 12's own spec section calls this out explicitly).

- [ ] **Step 1: Write the failing test (add to both `PipelinePage.test.jsx` files)**

First, the mock for `echo` needs to expose a way to fire the handler — add this at the top of both test files, replacing any existing plain import of `echo` (there wasn't one before this task, so this is new):

```jsx
let echoHandlers = {};
vi.mock('../../../echo', () => ({
    echo: {
        private: () => ({
            listen: (event, handler) => { echoHandlers[event] = handler; },
            stopListening: () => {},
        }),
    },
}));
```

Then add the test:

```jsx
    it('refetches the detail when a stage-changed broadcast arrives for this request', async () => {
        echoHandlers = {};
        const getSpy = vi.spyOn(client, 'apiGet')
            .mockResolvedValueOnce({ data: BASE_DETAIL })
            .mockResolvedValueOnce({ data: { ...BASE_DETAIL, stage: 'comparison', stage_index: 4 } });

        renderAt(12);
        await waitFor(() => expect(getSpy).toHaveBeenCalledTimes(1));

        act(() => {
            echoHandlers['.purchase-request.stage-changed']({ id: 12, request_number: 'MPR26-0012', stage: 'comparison' });
        });

        await waitFor(() => expect(getSpy).toHaveBeenCalledTimes(2));
        expect(screen.getByText('Quote Comparison')).toBeInTheDocument();
    });

    it('ignores a stage-changed broadcast for a different request', async () => {
        echoHandlers = {};
        const getSpy = vi.spyOn(client, 'apiGet').mockResolvedValue({ data: BASE_DETAIL });

        renderAt(12);
        await waitFor(() => expect(getSpy).toHaveBeenCalledTimes(1));

        act(() => {
            echoHandlers['.purchase-request.stage-changed']({ id: 999, request_number: 'MPR26-9999', stage: 'complete' });
        });

        await new Promise((r) => setTimeout(r, 0));
        expect(getSpy).toHaveBeenCalledTimes(1);
    });
```

Add `act` to the existing `@testing-library/react` import line in both files.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npx vitest run resources/js-app/pages/desktop/purchase/PipelinePage.test.jsx resources/js-app/pages/mobile/purchase/PipelinePage.test.jsx`
Expected: FAIL — no Echo subscription exists yet, `getSpy` is only called once.

- [ ] **Step 3: Add the subscription (apply to both files identically)**

Add the import:

```jsx
import { echo } from '../../../echo';
```

Extract the fetch into a named function and subscribe to the broadcast:

```jsx
    function refetch() {
        return apiGet(`/purchase/pipeline/${id}`)
            .then((res) => setPr(res.data))
            .catch(() => showToast('Failed to load this request.', 'error'));
    }

    useEffect(() => {
        refetch();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    useEffect(() => {
        const ch = echo.private('purchase');
        const handler = (payload) => {
            if (String(payload.id) === String(id)) refetch();
        };
        ch.listen('.purchase-request.stage-changed', handler);
        return () => ch.stopListening('.purchase-request.stage-changed');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);
```

Remove the old inline `useEffect(() => { apiGet(...)...}, [id])` block from Task 9/10 (it's now the `refetch()`-based effect above).

- [ ] **Step 4: Run the tests to verify they pass**

Run: `npx vitest run resources/js-app/pages/desktop/purchase/PipelinePage.test.jsx resources/js-app/pages/mobile/purchase/PipelinePage.test.jsx`
Expected: PASS (7 tests each).

- [ ] **Step 5: Commit**

```bash
git add resources/js-app/pages/desktop/purchase/PipelinePage.jsx resources/js-app/pages/desktop/purchase/PipelinePage.test.jsx resources/js-app/pages/mobile/purchase/PipelinePage.jsx resources/js-app/pages/mobile/purchase/PipelinePage.test.jsx
git commit -m "feat: live-refetch the Pipeline detail page on stage-changed broadcasts"
```

---

## Task 13: Route wiring and board link cutover

**Files:**
- Modify: `resources/js-app/App.jsx`
- Modify: `resources/js-app/pages/desktop/purchase/PipelineBoardPage.jsx`
- Modify: `resources/js-app/pages/desktop/purchase/PipelineBoardPage.test.jsx`
- Modify: `resources/js-app/pages/mobile/purchase/PipelineBoardPage.jsx`
- Modify: `resources/js-app/pages/mobile/purchase/PipelineBoardPage.test.jsx`

**Interfaces:**
- Produces: `/app/purchase/pipeline/:id` resolves to the new `PipelinePage` (desktop or mobile, via `useViewport`); the board's row/card links become React Router `<Link>`s to that route instead of full-page `<a href="/purchase/pipeline/{id}">` navigations to the now-deleted Blade page.

- [ ] **Step 1: Write the failing test for the board link change**

In `resources/js-app/pages/desktop/purchase/PipelineBoardPage.test.jsx`, find the existing test `'renders a row link that navigates to the Blade detail page via a real <a> tag'` and replace it with:

```jsx
    it('renders a row link that navigates to the React detail page', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 7, request_number: 'MPR26-0007', stage: 'draft', project_name: 'A', requested_by_name: 'Jane', department: 'Ops', date: '2026-08-01' }],
        });

        render(<ToastProvider><MemoryRouter><PipelineBoardPage /></MemoryRouter></ToastProvider>);
        await waitFor(() => expect(screen.getByText('MPR26-0007')).toBeInTheDocument());

        expect(screen.getByText('View').closest('a')).toHaveAttribute('href', '/app/purchase/pipeline/7');
    });
```

Add `import { MemoryRouter } from 'react-router-dom';` to the top of the file, and wrap every other `render(<ToastProvider>...)` call in the file with `<MemoryRouter>` too (every test in this file renders `PipelineBoardPage`, which will use `<Link>` after Step 3 — `<Link>` throws outside a Router context, so every existing test needs the same wrapper, not just the new one).

Do the identical two changes (replace the link-target test, wrap every render in `MemoryRouter`) in `resources/js-app/pages/mobile/purchase/PipelineBoardPage.test.jsx`.

- [ ] **Step 2: Run the board tests to verify they fail**

Run: `npx vitest run resources/js-app/pages/desktop/purchase/PipelineBoardPage.test.jsx resources/js-app/pages/mobile/purchase/PipelineBoardPage.test.jsx`
Expected: FAIL — the new assertion expects `/app/purchase/pipeline/7`, but the component still renders `/purchase/pipeline/7`.

- [ ] **Step 3: Update the board components**

In `resources/js-app/pages/desktop/purchase/PipelineBoardPage.jsx`, add `import { Link } from 'react-router-dom';` and replace:

```jsx
        key: 'link', label: '',
        render: (row) => <a href={`/purchase/pipeline/${row.id}`}>View</a>,
```

with:

```jsx
        key: 'link', label: '',
        render: (row) => <Link to={`/app/purchase/pipeline/${row.id}`}>View</Link>,
```

In `resources/js-app/pages/mobile/purchase/PipelineBoardPage.jsx`, add `import { Link } from 'react-router-dom';` and replace:

```jsx
                        <a
                            key={row.id}
                            href={`/purchase/pipeline/${row.id}`}
```

with:

```jsx
                        <Link
                            key={row.id}
                            to={`/app/purchase/pipeline/${row.id}`}
```

...and the matching closing `</a>` at the end of that block becomes `</Link>`.

- [ ] **Step 4: Wire the route in `App.jsx`**

```jsx
import DesktopPipelinePage from './pages/desktop/purchase/PipelinePage';
import MobilePipelinePage from './pages/mobile/purchase/PipelinePage';
```

```jsx
    const PipelinePage = viewport === 'mobile' ? MobilePipelinePage : DesktopPipelinePage;
```

```jsx
                <Route path="/app/purchase/pipeline/:id" element={<PipelinePage />} />
```

Add this route directly below the existing `/app/purchase/pipeline` route in the `<Routes>` block.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `npx vitest run resources/js-app/pages/desktop/purchase/PipelineBoardPage.test.jsx resources/js-app/pages/mobile/purchase/PipelineBoardPage.test.jsx`
Expected: PASS.

Then run the full frontend suite: `npx vitest run`
Expected: PASS (all suites, including Tasks 7-12's new files).

- [ ] **Step 6: Commit**

```bash
git add resources/js-app/App.jsx resources/js-app/pages/desktop/purchase/PipelineBoardPage.jsx resources/js-app/pages/desktop/purchase/PipelineBoardPage.test.jsx resources/js-app/pages/mobile/purchase/PipelineBoardPage.jsx resources/js-app/pages/mobile/purchase/PipelineBoardPage.test.jsx
git commit -m "feat: wire the Pipeline detail route and cut the board over to it"
```

---

## Task 14: Full-suite verification and manual smoke test

**Files:** none (verification only).

- [ ] **Step 1: Run the full backend test suite**

Run: `php artisan test`
Expected: PASS, zero failures.

- [ ] **Step 2: Run the full frontend test suite**

Run: `npx vitest run`
Expected: PASS, zero failures.

- [ ] **Step 3: Build the frontend**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 4: Manual smoke test — desktop**

Start `php artisan serve` and `npm run dev` (or use the already-running dev servers). As a user with `purchase-requests.view-all` and the relevant `manage-rfq`/`approve` permissions, in a desktop-width browser window:
1. Open `/app/purchase/pipeline`, click into a request — confirm it lands on `/app/purchase/pipeline/{id}` with no full page reload.
2. On a `draft`-stage request, click "Sign", draw a signature, confirm — the stage timeline should advance to `rfq` live, no reload.
3. On an `rfq`-stage request, click "+ Add Suppliers" — confirm it opens the new standalone select-suppliers page and can add a supplier.
4. On a `receiving`-stage request, click "Record GRN" — confirm it opens the new standalone select page.
5. Open the same request in a second browser tab and trigger a stage change (e.g. send RFQs) — confirm the first tab's timeline updates without a manual refresh.

- [ ] **Step 5: Manual smoke test — mobile**

Resize the browser window below 768px (or use device emulation) and repeat steps 1-2 of Step 4, confirming the timeline and sidebar cards stack in a single column and remain usable at mobile width.

- [ ] **Step 6: Confirm no dangling references**

Run: `grep -rn "purchase.pipeline.show\|purchase/pipeline/\${" resources/views app/Http resources/js-app/pages`
Expected: no output other than the new literal-path strings intentionally introduced in Tasks 3-13 (spot-check any hits to confirm they're the new `/app/purchase/pipeline/...` or `/purchase/pipeline/${id}`-style literals, not a missed reference to the deleted route).
