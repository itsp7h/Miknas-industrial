<?php

namespace App\Http\Controllers\Api\Purchase;

use App\Events\PurchaseOrderDeleted;
use App\Events\PurchaseOrderSaved;
use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Notifications\Purchase\PurchaseOrderConfirmedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public const STATUSES = ['draft', 'sent', 'received', 'cancelled'];

    public function index()
    {
        return PurchaseOrderResource::collection(
            PurchaseOrder::with('supplier')->latest()->get()
        );
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        return new PurchaseOrderResource($purchaseOrder->load([
            'supplier', 'items.item', 'createdBy', 'purchaseRequest', 'goodsReceiptNotes.warehouse',
        ]));
    }

    public function formOptions()
    {
        return response()->json([
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'items' => Item::orderBy('item_name')->get(['id', 'item_code', 'item_name', 'unit_of_measure', 'cost_price']),
            'purchase_requests' => PurchaseRequest::where('status', 'approved')
                ->orderByDesc('id')->get(['id', 'request_number']),
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_request_id' => 'nullable|exists:purchase_requests,id',
            'po_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:po_date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.rate' => 'required|numeric|min:0',
        ]);

        $this->authorizeOrderAccess($data['purchase_request_id'] ?? null);

        $order = DB::transaction(function () use ($data) {
            $order = PurchaseOrder::create([
                'po_number' => $this->nextPoNumber(),
                'supplier_id' => $data['supplier_id'],
                'purchase_request_id' => $data['purchase_request_id'] ?? null,
                'po_date' => $data['po_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'total_amount' => $this->totalFor($data['items']),
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            $this->replaceItems($order, $data['items']);

            return $order;
        });

        if ($order->supplier && $order->supplier->whatsapp_number) {
            $order->supplier->notify(new PurchaseOrderConfirmedNotification($order));
        }

        event(new PurchaseOrderSaved($order));

        return (new PurchaseOrderResource($order->load(['supplier', 'items.item'])))
            ->response()->setStatusCode(201);
    }

    /**
     * Header fields only — the Blade edit form had no line-item section and this
     * keeps that scope. It does persist expected_delivery_date and notes, which
     * that form collected but its controller dropped: it saved only
     * `only('supplier_id', 'po_date', 'status')`, so both fields silently
     * discarded whatever the user typed.
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->loadMissing('purchaseRequest');
        $this->authorizeOrderAccess($purchaseOrder->purchase_request_id, $purchaseOrder->purchaseRequest);

        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'po_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:po_date',
            'status' => 'nullable|in:'.implode(',', self::STATUSES),
            'notes' => 'nullable|string',
        ]);

        $purchaseOrder->update($data);

        event(new PurchaseOrderSaved($purchaseOrder));

        return new PurchaseOrderResource($purchaseOrder->load(['supplier', 'items.item']));
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->loadMissing('purchaseRequest');
        $this->authorizeOrderAccess($purchaseOrder->purchase_request_id, $purchaseOrder->purchaseRequest);

        // A received GRN has already moved stock; deleting the order behind it
        // would leave those movements pointing at nothing.
        abort_if(
            $purchaseOrder->goodsReceiptNotes()->exists(),
            422,
            'This order already has goods receipt notes and cannot be deleted.'
        );

        $id = $purchaseOrder->id;

        DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->items()->delete();
            $purchaseOrder->delete();
        });

        event(new PurchaseOrderDeleted($id));

        return response()->json(['deleted' => true]);
    }

    /**
     * Mirrors the Blade controller's guard exactly. `generateLpo` is
     * instance-scoped to a PurchaseRequest (it checks that request's stage), so
     * it cannot be used as a class-level check. When the order is linked to a
     * request we authorize against that instance; with no linked request there
     * is no stage to gate on, so we fall back to the bare permission rather
     * than letting manual CRUD skip authorization by omitting the link.
     */
    private function authorizeOrderAccess(?int $purchaseRequestId, ?PurchaseRequest $purchaseRequest = null): void
    {
        $purchaseRequest ??= $purchaseRequestId ? PurchaseRequest::findOrFail($purchaseRequestId) : null;

        if ($purchaseRequest) {
            $this->authorize('generateLpo', $purchaseRequest);

            return;
        }

        if (! auth()->user() || ! auth()->user()->can('purchase-requests.generate-lpo')) {
            abort(403);
        }
    }

    private function replaceItems(PurchaseOrder $order, array $items): void
    {
        $order->items()->delete();

        foreach ($items as $line) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id,
                'item_id' => $line['item_id'],
                'quantity' => $line['quantity'],
                'rate' => $line['rate'],
                'total_amount' => $line['quantity'] * $line['rate'],
                'quantity_received' => 0,
            ]);
        }
    }

    private function totalFor(array $items): float
    {
        return collect($items)->sum(fn ($line) => $line['quantity'] * $line['rate']);
    }

    private function nextPoNumber(): string
    {
        return 'PO-'.str_pad((string) (PurchaseOrder::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}
