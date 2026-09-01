<?php

namespace App\Http\Controllers\Api\Purchase;

use App\Events\GrnDeleted;
use App\Events\GrnSaved;
use App\Http\Controllers\Controller;
use App\Http\Resources\GrnResource;
use App\Models\GoodsReceiptNote;
use App\Models\GrnItem;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\Purchase\GoodsReceiptConfirmedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class GoodsReceiptNoteController extends Controller
{
    public const TYPES = ['inventory', 'consumable'];

    public function index()
    {
        return GrnResource::collection(
            GoodsReceiptNote::with(['purchaseOrder.supplier', 'warehouse'])->latest()->get()
        );
    }

    public function show(GoodsReceiptNote $grn)
    {
        return new GrnResource($grn->load([
            'purchaseOrder.supplier', 'warehouse', 'receivedBy',
            'items.item', 'items.purchaseOrderItem',
        ]));
    }

    /**
     * Receivable orders and their lines, so the form can populate its item rows
     * the way the Blade page's data-items JSON blob did.
     */
    public function formOptions()
    {
        $orders = PurchaseOrder::whereIn('status', ['sent', 'partial'])
            ->with(['supplier', 'items.item'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'purchase_orders' => $orders->map(fn ($po) => [
                'id' => $po->id,
                'po_number' => $po->po_number ?? 'PO-'.str_pad((string) $po->id, 5, '0', STR_PAD_LEFT),
                'supplier_name' => $po->supplier?->name,
                'items' => $po->items->map(fn ($line) => [
                    'purchase_order_item_id' => $line->id,
                    'item_id' => $line->item_id,
                    'item_name' => $line->item?->item_name ?? "Item #{$line->item_id}",
                    'unit_of_measure' => $line->item?->unit_of_measure,
                    'quantity' => $line->quantity,
                    'quantity_received' => $line->quantity_received,
                    'rate' => $line->rate,
                ])->values(),
            ])->values(),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'types' => self::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'received_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.purchase_order_item_id' => 'nullable|exists:purchase_order_items,id',
            'items.*.quantity_received' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.type' => 'nullable|in:'.implode(',', self::TYPES),
        ]);

        $po = PurchaseOrder::with('items')->findOrFail($data['purchase_order_id']);

        $grn = DB::transaction(function () use ($data, $po) {
            $grn = GoodsReceiptNote::create([
                'grn_number' => $this->nextGrnNumber(),
                'purchase_order_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'warehouse_id' => $data['warehouse_id'],
                'received_date' => $data['received_date'],
                // The Blade controller validated no notes field and omitted it
                // from create(), so whatever the user typed was discarded.
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'received_by' => auth()->id(),
            ]);

            foreach ($data['items'] as $line) {
                $poItemId = $line['purchase_order_item_id']
                    ?? $po->items->firstWhere('item_id', $line['item_id'])?->id;

                GrnItem::create([
                    'goods_receipt_note_id' => $grn->id,
                    'purchase_order_item_id' => $poItemId,
                    'item_id' => $line['item_id'],
                    'quantity_received' => $line['quantity_received'],
                    'unit_cost' => $line['unit_cost'] ?? 0,
                    'type' => $line['type'] ?? 'inventory',
                ]);
            }

            return $grn;
        });

        event(new GrnSaved($grn));

        return (new GrnResource($grn->load(['purchaseOrder.supplier', 'warehouse', 'items.item', 'items.purchaseOrderItem'])))
            ->response()->setStatusCode(201);
    }

    /**
     * Receives the goods: raises stock, writes the movements, advances the PO's
     * received quantities, and marks the PO received once every line is met.
     * Same logic as the Blade controller — which no page ever linked to, so
     * confirming was unreachable and stock never actually moved.
     */
    public function confirm(GoodsReceiptNote $grn)
    {
        abort_if($grn->status === 'confirmed', 422, 'This GRN is already confirmed.');

        DB::transaction(function () use ($grn) {
            $grn->load('items', 'purchaseOrder.items');

            foreach ($grn->items as $grnItem) {
                $stockLevel = StockLevel::firstOrCreate(
                    ['item_id' => $grnItem->item_id, 'warehouse_id' => $grn->warehouse_id],
                    ['quantity' => 0]
                );
                $stockLevel->increment('quantity', $grnItem->quantity_received);

                StockMovement::create([
                    'item_id' => $grnItem->item_id,
                    'warehouse_id' => $grn->warehouse_id,
                    'type' => 'in',
                    'quantity' => $grnItem->quantity_received,
                    'reference_type' => 'GoodsReceiptNote',
                    'reference_id' => $grn->id,
                    'created_by' => auth()->id(),
                ]);

                $grn->purchaseOrder->items()
                    ->where('item_id', $grnItem->item_id)
                    ->increment('quantity_received', $grnItem->quantity_received);
            }

            $grn->update(['status' => 'confirmed']);

            $po = $grn->purchaseOrder->fresh(['items']);
            $allReceived = $po->items->every(fn ($line) => $line->quantity_received >= $line->quantity);

            if ($allReceived) {
                $po->update(['status' => 'received']);
            }
        });

        $storeManagers = User::role('Store Manager')->whereNotNull('whatsapp_number')->get();
        Notification::send($storeManagers, new GoodsReceiptConfirmedNotification($grn));

        event(new GrnSaved($grn));

        return new GrnResource($grn->fresh(['purchaseOrder.supplier', 'warehouse', 'items.item', 'items.purchaseOrderItem']));
    }

    public function destroy(GoodsReceiptNote $grn)
    {
        // A confirmed GRN has already moved stock; deleting it would leave those
        // movements referencing nothing.
        abort_if($grn->status === 'confirmed', 422, 'A confirmed GRN cannot be deleted.');

        $id = $grn->id;

        DB::transaction(function () use ($grn) {
            $grn->items()->delete();
            $grn->delete();
        });

        event(new GrnDeleted($id));

        return response()->json(['deleted' => true]);
    }

    private function nextGrnNumber(): string
    {
        return 'GRN-'.str_pad((string) (GoodsReceiptNote::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}
