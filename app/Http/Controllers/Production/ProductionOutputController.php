<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ProductionOrder;
use App\Models\ProductionOutput;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionOutputController extends Controller
{
    public function index()
    {
        $outputs = ProductionOutput::with(['productionOrder', 'item', 'warehouse'])->paginate(15);
        $productionOrders = ProductionOrder::whereIn('status', ['in_progress'])->with('product')->get();
        $products = Item::where('category', 'finished_good')->get();
        $warehouses = Warehouse::all();

        return view('production.outputs.index', compact('outputs', 'productionOrders', 'products', 'warehouses'));
    }

    public function create()
    {
        $productionOrders = ProductionOrder::whereIn('status', ['in_progress'])->with('product')->get();
        $warehouses       = Warehouse::all();

        return view('production.outputs.create', compact('productionOrders', 'warehouses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'production_order_id' => 'required|exists:production_orders,id',
            'item_id'             => 'required|exists:items,id',
            'warehouse_id'        => 'required|exists:warehouses,id',
            'quantity'            => 'required|numeric|min:0.01',
            'output_date'         => 'required|date',
        ]);

        DB::transaction(function () use ($request) {
            $stockLevel = StockLevel::firstOrCreate(
                ['item_id' => $request->item_id, 'warehouse_id' => $request->warehouse_id],
                ['quantity' => 0]
            );
            $stockLevel->increment('quantity', $request->quantity);

            $output = ProductionOutput::create([
                'production_order_id' => $request->production_order_id,
                'item_id'             => $request->item_id,
                'warehouse_id'        => $request->warehouse_id,
                'quantity'            => $request->quantity,
                'output_date'         => $request->output_date,
                'recorded_by'         => auth()->id(),
            ]);

            StockMovement::create([
                'item_id'        => $request->item_id,
                'warehouse_id'   => $request->warehouse_id,
                'type'           => 'in',
                'quantity'       => $request->quantity,
                'reference_type' => 'ProductionOutput',
                'reference_id'   => $output->id,
                'created_by'     => auth()->id(),
            ]);

            // Accumulate quantity_produced on the production order
            ProductionOrder::where('id', $request->production_order_id)
                ->increment('quantity_produced', $request->quantity);
        });

        return redirect()->back()->with('success', 'Production output recorded and stock updated.');
    }

    public function show(ProductionOutput $productionOutput)
    {
        $productionOutput->load(['productionOrder', 'item', 'warehouse']);

        return view('production.outputs.show', compact('productionOutput'));
    }

    public function edit(ProductionOutput $productionOutput)
    {
        return view('production.outputs.edit', compact('productionOutput'));
    }

    public function update(Request $request, ProductionOutput $productionOutput)
    {
        $request->validate([
            'output_date' => 'required|date',
        ]);

        $productionOutput->update($request->only('output_date', 'notes'));

        return redirect()->route('production.outputs.index')->with('success', 'Production output updated successfully.');
    }

    public function destroy(ProductionOutput $productionOutput)
    {
        $productionOutput->delete();

        return redirect()->route('production.outputs.index')->with('success', 'Production output deleted successfully.');
    }
}
