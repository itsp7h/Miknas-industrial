<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index()
    {
        $movements = StockMovement::with(['item', 'warehouse'])->latest()->paginate(20);

        return view('inventory.movements.index', compact('movements'));
    }

    public function create()
    {
        $items      = Item::all();
        $warehouses = Warehouse::all();

        return view('inventory.movements.create', compact('items', 'warehouses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'item_id'      => 'required|exists:items,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'type'         => 'required|in:in,out,adjustment',
            'quantity'     => 'required|numeric|min:0.01',
            'notes'        => 'nullable|string',
        ]);

        $stockLevel = StockLevel::firstOrCreate(
            ['item_id' => $request->item_id, 'warehouse_id' => $request->warehouse_id],
            ['quantity' => 0]
        );

        if ($request->type === 'in' || $request->type === 'adjustment') {
            $stockLevel->increment('quantity', $request->quantity);
        } else {
            // Decrement but do not go below zero
            $decrement = min($request->quantity, $stockLevel->quantity);
            $stockLevel->decrement('quantity', $decrement);
        }

        StockMovement::create([
            'item_id'       => $request->item_id,
            'warehouse_id'  => $request->warehouse_id,
            'type'          => $request->type,
            'quantity'      => $request->quantity,
            'notes'         => $request->notes,
            'created_by'    => auth()->id(),
        ]);

        return redirect()->route('inventory.movements.index')->with('success', 'Stock movement recorded successfully.');
    }

    public function show(StockMovement $stockMovement)
    {
        $stockMovement->load(['item', 'warehouse']);

        return view('inventory.movements.show', compact('stockMovement'));
    }

    public function edit(StockMovement $stockMovement)
    {
        return view('inventory.movements.edit', compact('stockMovement'));
    }

    public function update(Request $request, StockMovement $stockMovement)
    {
        $request->validate([
            'notes' => 'nullable|string',
        ]);

        $stockMovement->update($request->only('notes'));

        return redirect()->route('inventory.movements.index')->with('success', 'Stock movement updated successfully.');
    }

    public function destroy(StockMovement $stockMovement)
    {
        $stockMovement->delete();

        return redirect()->route('inventory.movements.index')->with('success', 'Stock movement deleted successfully.');
    }
}
