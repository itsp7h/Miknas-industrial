<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\MaterialIssue;
use App\Models\ProductionOrder;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaterialIssueController extends Controller
{
    public function index()
    {
        $issues = MaterialIssue::with(['productionOrder', 'item', 'warehouse'])->paginate(15);

        return view('production.material-issues.index', compact('issues'));
    }

    public function create()
    {
        $productionOrders = ProductionOrder::whereIn('status', ['planned', 'in_progress'])->get();
        $items = Item::where('category', 'raw_material')->get();
        $warehouses = Warehouse::all();

        return view('production.material-issues.create', compact('productionOrders', 'items', 'warehouses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'production_order_id' => 'required|exists:production_orders,id',
            'item_id' => 'required|exists:items,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.01',
            'issue_date' => 'required|date',
        ]);

        DB::transaction(function () use ($request) {
            $issueNumber = 'MI-'.str_pad(MaterialIssue::max('id') + 1, 5, '0', STR_PAD_LEFT);

            $stockLevel = StockLevel::firstOrCreate(
                ['item_id' => $request->item_id, 'warehouse_id' => $request->warehouse_id],
                ['quantity' => 0]
            );

            // Decrement stock, floor at zero
            $decrement = min($request->quantity, $stockLevel->quantity);
            $stockLevel->decrement('quantity', $decrement);

            StockMovement::create([
                'item_id' => $request->item_id,
                'warehouse_id' => $request->warehouse_id,
                'type' => 'out',
                'quantity' => $request->quantity,
                'reference_type' => 'MaterialIssue',
                'reference_id' => null, // will be updated after create
                'created_by' => auth()->id(),
            ]);

            $issue = MaterialIssue::create([
                'issue_number' => $issueNumber,
                'production_order_id' => $request->production_order_id,
                'item_id' => $request->item_id,
                'warehouse_id' => $request->warehouse_id,
                'quantity' => $request->quantity,
                'issue_date' => $request->issue_date,
                'issued_by' => auth()->id(),
            ]);

            // Backfill reference_id on the movement just created
            StockMovement::where('item_id', $request->item_id)
                ->where('warehouse_id', $request->warehouse_id)
                ->where('type', 'out')
                ->where('reference_type', 'MaterialIssue')
                ->whereNull('reference_id')
                ->latest('id')
                ->first()
                ?->update(['reference_id' => $issue->id]);
        });

        return redirect()->back()->with('success', 'Material issued successfully.');
    }

    public function show(MaterialIssue $materialIssue)
    {
        $materialIssue->load(['productionOrder', 'item', 'warehouse']);

        return view('production.material-issues.show', compact('materialIssue'));
    }

    public function edit(MaterialIssue $materialIssue)
    {
        return view('production.material-issues.edit', compact('materialIssue'));
    }

    public function update(Request $request, MaterialIssue $materialIssue)
    {
        $request->validate([
            'issue_date' => 'required|date',
        ]);

        $materialIssue->update($request->only('issue_date', 'notes'));

        return redirect()->route('production.material-issues.index')->with('success', 'Material issue updated successfully.');
    }

    public function destroy(MaterialIssue $materialIssue)
    {
        $materialIssue->delete();

        return redirect()->route('production.material-issues.index')->with('success', 'Material issue deleted successfully.');
    }
}
