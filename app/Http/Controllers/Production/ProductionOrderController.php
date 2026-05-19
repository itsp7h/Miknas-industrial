<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ProductionCost;
use App\Models\ProductionOrder;
use Illuminate\Http\Request;

class ProductionOrderController extends Controller
{
    public function index()
    {
        $orders = ProductionOrder::with('product')->paginate(15);

        return view('production.orders.index', compact('orders'));
    }

    public function create()
    {
        // Only finished_good type items can be produced
        $items = Item::where('category', 'finished_good')->get();

        return view('production.orders.create', compact('items'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id'          => 'required|exists:items,id',
            'quantity_to_produce' => 'required|numeric|min:1',
            'production_date'     => 'required|date',
        ]);

        $data                  = $request->all();
        $data['order_number']  = 'PRO-' . str_pad(ProductionOrder::max('id') + 1, 5, '0', STR_PAD_LEFT);
        $data['created_by']    = auth()->id();
        $data['status']        = 'planned';

        $order = ProductionOrder::create($data);

        return redirect()->route('production.orders.show', $order)->with('success', 'Production order created successfully.');
    }

    public function show(ProductionOrder $productionOrder)
    {
        $productionOrder->load(['product', 'materialIssues.item', 'outputs.item', 'cost']);

        return view('production.orders.show', compact('productionOrder'));
    }

    public function edit(ProductionOrder $productionOrder)
    {
        $items = Item::where('category', 'finished_good')->get();

        return view('production.orders.edit', compact('productionOrder', 'items'));
    }

    public function update(Request $request, ProductionOrder $productionOrder)
    {
        $request->validate([
            'production_date' => 'required|date',
        ]);

        $productionOrder->update($request->only('production_date', 'notes'));

        return redirect()->route('production.orders.show', $productionOrder)->with('success', 'Production order updated successfully.');
    }

    public function destroy(ProductionOrder $productionOrder)
    {
        $productionOrder->delete();

        return redirect()->route('production.orders.index')->with('success', 'Production order deleted successfully.');
    }

    public function start(ProductionOrder $productionOrder)
    {
        $productionOrder->update(['status' => 'in_progress']);

        return redirect()->back()->with('success', 'Production order started.');
    }

    public function complete(ProductionOrder $productionOrder)
    {
        $productionOrder->update([
            'status'          => 'completed',
            'completion_date' => now(),
        ]);

        return redirect()->back()->with('success', 'Production order marked as completed.');
    }
}
