<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        $orders = PurchaseOrder::with('supplier')->paginate(15);

        return view('purchase.orders.index', compact('orders'));
    }

    public function create()
    {
        $suppliers        = Supplier::all();
        $items            = Item::all();
        $purchaseRequests = PurchaseRequest::where('status', 'approved')->get();

        return view('purchase.orders.create', compact('suppliers', 'items', 'purchaseRequests'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id'          => 'required|exists:suppliers,id',
            'po_date'              => 'required|date',
            'items'                => 'required|array|min:1',
            'items.*.item_id'      => 'required|exists:items,id',
            'items.*.quantity'     => 'required|numeric|min:1',
            'items.*.rate'         => 'required|numeric|min:0',
        ]);

        $poNumber = 'PO-' . str_pad(PurchaseOrder::max('id') + 1, 5, '0', STR_PAD_LEFT);

        $totalAmount = collect($request->items)->sum(fn($item) => $item['quantity'] * $item['rate']);

        $order = PurchaseOrder::create([
            'po_number'    => $poNumber,
            'supplier_id'  => $request->supplier_id,
            'po_date'      => $request->po_date,
            'total_amount' => $totalAmount,
            'status'       => 'draft',
            'created_by'   => auth()->id(),
        ]);

        foreach ($request->items as $item) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id,
                'item_id'           => $item['item_id'],
                'quantity'          => $item['quantity'],
                'rate'              => $item['rate'],
                'amount'            => $item['quantity'] * $item['rate'],
            ]);
        }

        return redirect()->route('purchase.orders.show', $order)->with('success', 'Purchase order created successfully.');
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'items.item']);

        return view('purchase.orders.show', compact('purchaseOrder'));
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        $suppliers = Supplier::all();
        $items     = Item::all();

        return view('purchase.orders.edit', compact('purchaseOrder', 'suppliers', 'items'));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'po_date'     => 'required|date',
        ]);

        $purchaseOrder->update($request->only('supplier_id', 'po_date', 'status'));

        return redirect()->route('purchase.orders.show', $purchaseOrder)->with('success', 'Purchase order updated successfully.');
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->delete();

        return redirect()->route('purchase.orders.index')->with('success', 'Purchase order deleted successfully.');
    }
}
