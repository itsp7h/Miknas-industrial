<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockReportController extends Controller
{
    public function summary()
    {
        $rows = StockLevel::with(['item', 'warehouse'])->get()->map(function ($level) {
            $minimum = (float) ($level->item?->minimum_stock_level ?? 0);

            return [
                'id' => $level->id,
                'item_code' => $level->item?->item_code,
                'item_name' => $level->item?->item_name,
                'warehouse_name' => $level->warehouse?->name,
                'unit_of_measure' => $level->item?->unit_of_measure,
                'quantity' => $level->quantity,
                // The Blade report's whole point was flagging a line below its
                // minimum, in red with a LOW badge. Neither the minimum nor the
                // flag was being sent, so the React page could not show either.
                'minimum_stock_level' => $level->item?->minimum_stock_level,
                'is_low' => (float) $level->quantity < $minimum,
            ];
        })->values();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total_lines' => $rows->count(),
                'below_minimum' => $rows->where('is_low', true)->count(),
            ],
        ]);
    }

    /**
     * Unpaginated on purpose: the page filters client-side (CLAUDE.md gotcha
     * #6), and the date/item filters below narrow the set server-side first.
     */
    public function movement(Request $request)
    {
        $filters = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'item_id' => 'nullable|exists:items,id',
        ]);

        $query = StockMovement::with(['item', 'warehouse']);

        if (! empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        if (! empty($filters['item_id'])) {
            $query->where('item_id', $filters['item_id']);
        }

        $rows = $query->latest()->get()->map(fn ($movement) => [
            'id' => $movement->id,
            'created_at' => $movement->created_at?->toIso8601String(),
            'item_name' => $movement->item?->item_name,
            'item_code' => $movement->item?->item_code,
            'warehouse_name' => $movement->warehouse?->name,
            'type' => $movement->type,
            'quantity' => $movement->quantity,
            'notes' => $movement->notes,
        ])->values();

        return response()->json([
            'data' => $rows,
            'meta' => ['items' => Item::orderBy('item_name')->get(['id', 'item_code', 'item_name'])],
        ]);
    }

    public function lowStock()
    {
        // minimum_stock_level lives on the item, not on stock_levels, so the
        // comparison happens after loading rather than in a whereColumn.
        $rows = StockLevel::with(['item', 'warehouse'])
            ->get()
            ->filter(fn ($level) => $level->item && $level->quantity < ($level->item->minimum_stock_level ?? 0))
            ->map(fn ($level) => [
                'id' => $level->id,
                'item_code' => $level->item?->item_code,
                'item_name' => $level->item?->item_name,
                'warehouse_name' => $level->warehouse?->name,
                'quantity' => $level->quantity,
                'minimum_stock_level' => $level->item?->minimum_stock_level,
                'shortfall' => round(((float) $level->item->minimum_stock_level) - ((float) $level->quantity), 2),
            ])
            ->values();

        return response()->json([
            'data' => $rows,
            'meta' => ['below_minimum' => $rows->count()],
        ]);
    }

    public function valuation()
    {
        $rows = StockLevel::join('items', 'items.id', '=', 'stock_levels.item_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_levels.warehouse_id')
            ->select(
                'stock_levels.id as id',
                'items.item_code',
                'items.item_name',
                'warehouses.name as warehouse_name',
                'stock_levels.quantity',
                'items.cost_price',
                DB::raw('stock_levels.quantity * items.cost_price as valuation')
            )
            ->get();

        return response()->json([
            'data' => $rows,
            'meta' => ['total_valuation' => round((float) $rows->sum('valuation'), 2)],
        ]);
    }
}
