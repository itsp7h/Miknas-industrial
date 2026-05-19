<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Notifications\Sales\SalesOrderConfirmedNotification;
use Illuminate\Http\Request;

class SalesOrderController extends Controller
{
    public function index()
    {
        $orders = SalesOrder::with('customer')->paginate(15);

        return view('sales.orders.index', compact('orders'));
    }

    public function create()
    {
        $customers = Customer::all();
        $items     = Item::where('category', 'finished_good')->get();

        return view('sales.orders.create', compact('customers', 'items'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id'          => 'required|exists:customers,id',
            'order_date'           => 'required|date',
            'items'                => 'required|array|min:1',
            'items.*.item_id'      => 'required|exists:items,id',
            'items.*.quantity'     => 'required|numeric|min:1',
            'items.*.unit_price'   => 'required|numeric|min:0',
        ]);

        $orderNumber = 'SO-' . str_pad(SalesOrder::max('id') + 1, 5, '0', STR_PAD_LEFT);
        $totalAmount = collect($request->items)->sum(fn($item) => $item['quantity'] * $item['unit_price']);

        $order = SalesOrder::create([
            'order_number' => $orderNumber,
            'customer_id'  => $request->customer_id,
            'order_date'   => $request->order_date,
            'total_amount' => $totalAmount,
            'status'       => 'draft',
            'created_by'   => auth()->id(),
        ]);

        foreach ($request->items as $item) {
            SalesOrderItem::create([
                'sales_order_id'    => $order->id,
                'item_id'           => $item['item_id'],
                'quantity'          => $item['quantity'],
                'unit_price'        => $item['unit_price'],
                'amount'            => $item['quantity'] * $item['unit_price'],
                'quantity_delivered' => 0,
            ]);
        }

        return redirect()->route('sales.orders.show', $order)->with('success', 'Sales order created successfully.');
    }

    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load(['customer', 'items.item', 'deliveryNotes', 'invoices']);

        return view('sales.orders.show', compact('salesOrder'));
    }

    public function edit(SalesOrder $salesOrder)
    {
        $customers = Customer::all();
        $items     = Item::where('category', 'finished_good')->get();

        return view('sales.orders.edit', compact('salesOrder', 'customers', 'items'));
    }

    public function update(Request $request, SalesOrder $salesOrder)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'order_date'  => 'required|date',
        ]);

        $salesOrder->update($request->only('customer_id', 'order_date', 'notes'));

        return redirect()->route('sales.orders.show', $salesOrder)->with('success', 'Sales order updated successfully.');
    }

    public function destroy(SalesOrder $salesOrder)
    {
        $salesOrder->delete();

        return redirect()->route('sales.orders.index')->with('success', 'Sales order deleted successfully.');
    }

    public function confirm(SalesOrder $salesOrder)
    {
        $salesOrder->update(['status' => 'confirmed']);

        if ($salesOrder->customer && $salesOrder->customer->whatsapp_number) {
            $salesOrder->customer->notify(new SalesOrderConfirmedNotification($salesOrder));
        }

        return redirect()->back()->with('success', 'Sales order confirmed.');
    }
}
