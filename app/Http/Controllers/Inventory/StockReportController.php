<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockReportController extends Controller
{
    public function summary()
    {
        $stockLevels = StockLevel::with(['item', 'warehouse'])->get();

        return view('inventory.reports.summary', compact('stockLevels'));
    }

    public function movement(Request $request)
    {
        $query = StockMovement::with(['item', 'warehouse']);

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        $movements = $query->latest()->paginate(50)->withQueryString();
        $items = Item::orderBy('item_name')->get();

        return view('inventory.reports.movement', compact('movements', 'items'));
    }

    public function lowStock()
    {
        // Items whose total stock across all warehouses is below their minimum_stock_level
        $items = Item::withSum('stockLevels', 'quantity')
            ->having(DB::raw('COALESCE(stock_levels_sum_quantity, 0)'), '<', DB::raw('minimum_stock_level'))
            ->get();

        return view('inventory.reports.low-stock', compact('items'));
    }

    public function valuation()
    {
        $valuations = StockLevel::join('items', 'items.id', '=', 'stock_levels.item_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_levels.warehouse_id')
            ->select(
                'items.id as item_id',
                'items.item_name',
                'items.item_code',
                'warehouses.name as warehouse_name',
                'stock_levels.quantity',
                'items.cost_price',
                DB::raw('stock_levels.quantity * items.cost_price as valuation')
            )
            ->get();

        $totalValuation = $valuations->sum('valuation');

        return view('inventory.reports.valuation', compact('valuations', 'totalValuation'));
    }
}
