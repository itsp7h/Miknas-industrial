<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceiptNote;
use App\Models\GrnItem;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Notifications\Purchase\GoodsReceiptConfirmedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoodsReceiptNoteController extends Controller
{
    public function index()
    {
        $grns = GoodsReceiptNote::with(['purchaseOrder.supplier', 'warehouse'])->paginate(15);

        return view('purchase.grns.index', compact('grns'));
    }

    public function create()
    {
        $purchaseOrders = PurchaseOrder::whereIn('status', ['sent', 'partial'])->with('supplier')->get();
        $warehouses     = Warehouse::all();

        return view('purchase.grns.create', compact('purchaseOrders', 'warehouses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_order_id'    => 'required|exists:purchase_orders,id',
            'warehouse_id'         => 'required|exists:warehouses,id',
            'received_date'        => 'required|date',
            'items'                => 'required|array|min:1',
            'items.*.item_id'      => 'required|exists:items,id',
            'items.*.quantity'     => 'required|numeric|min:1',
        ]);

        $grnNumber = 'GRN-' . str_pad(GoodsReceiptNote::max('id') + 1, 5, '0', STR_PAD_LEFT);

        $po = PurchaseOrder::findOrFail($request->purchase_order_id);

        $grn = GoodsReceiptNote::create([
            'grn_number'        => $grnNumber,
            'purchase_order_id' => $request->purchase_order_id,
            'supplier_id'       => $po->supplier_id,
            'warehouse_id'      => $request->warehouse_id,
            'received_date'     => $request->received_date,
            'status'            => 'draft',
            'received_by'       => auth()->id(),
        ]);

        foreach ($request->items as $item) {
            GrnItem::create([
                'goods_receipt_note_id'  => $grn->id,
                'purchase_order_item_id' => $item['po_item_id'] ?? $po->items()->where('item_id', $item['item_id'])->first()?->id ?? 0,
                'item_id'                => $item['item_id'],
                'quantity_received'      => $item['quantity'],
                'unit_cost'              => $item['unit_cost'] ?? 0,
                'type'                   => in_array($item['type'] ?? '', ['inventory', 'consumable']) ? $item['type'] : 'inventory',
            ]);
        }

        return redirect()->route('purchase.grns.show', $grn)->with('success', 'GRN created successfully.');
    }

    public function show(GoodsReceiptNote $grn)
    {
        $grn->load(['purchaseOrder.supplier', 'warehouse', 'items.item']);

        return view('purchase.grns.show', compact('grn'));
    }

    public function edit(GoodsReceiptNote $grn)
    {
        $warehouses = Warehouse::all();

        return view('purchase.grns.edit', compact('grn', 'warehouses'));
    }

    public function update(Request $request, GoodsReceiptNote $grn)
    {
        $request->validate([
            'received_date' => 'required|date',
            'warehouse_id'  => 'required|exists:warehouses,id',
        ]);

        $grn->update($request->only('received_date', 'warehouse_id'));

        return redirect()->route('purchase.grns.show', $grn)->with('success', 'GRN updated successfully.');
    }

    public function destroy(GoodsReceiptNote $grn)
    {
        $grn->delete();

        return redirect()->route('purchase.grns.index')->with('success', 'GRN deleted successfully.');
    }

    public function confirm(GoodsReceiptNote $grn)
    {
        if ($grn->status === 'confirmed') {
            return redirect()->back()->with('error', 'GRN is already confirmed.');
        }

        DB::transaction(function () use ($grn) {
            $grn->load('items');

            foreach ($grn->items as $grnItem) {
                $stockLevel = StockLevel::firstOrCreate(
                    ['item_id' => $grnItem->item_id, 'warehouse_id' => $grn->warehouse_id],
                    ['quantity' => 0]
                );
                $stockLevel->increment('quantity', $grnItem->quantity_received);

                StockMovement::create([
                    'item_id'        => $grnItem->item_id,
                    'warehouse_id'   => $grn->warehouse_id,
                    'type'           => 'in',
                    'quantity'       => $grnItem->quantity_received,
                    'reference_type' => 'GoodsReceiptNote',
                    'reference_id'   => $grn->id,
                    'created_by'     => auth()->id(),
                ]);

                $grn->purchaseOrder->items()
                    ->where('item_id', $grnItem->item_id)
                    ->increment('quantity_received', $grnItem->quantity_received);
            }

            $grn->update(['status' => 'confirmed']);

            // Check if all PO items have been fully received
            $po              = $grn->purchaseOrder->fresh(['items']);
            $allFullyReceived = $po->items->every(
                fn($poItem) => $poItem->quantity_received >= $poItem->quantity
            );

            if ($allFullyReceived) {
                $po->update(['status' => 'received']);
            }
        });

        $storeManagers = \App\Models\User::role('Store Manager')->whereNotNull('whatsapp_number')->get();
        \Illuminate\Support\Facades\Notification::send($storeManagers, new GoodsReceiptConfirmedNotification($grn));

        return redirect()->back()->with('success', 'GRN confirmed and stock updated.');
    }
}
