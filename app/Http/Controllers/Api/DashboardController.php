<?php

namespace App\Http\Controllers\Api;

use App\Events\DashboardPinged;
use App\Http\Controllers\Controller;
use App\Models\ProductionOrder;
use App\Models\PurchaseRequest;
use App\Models\SalesInvoice;
use App\Models\StockLevel;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function ping(Request $request)
    {
        event(new DashboardPinged($request->user()->id, 'Live update check'));

        return response()->noContent();
    }

    /**
     * The five KPIs the Blade dashboard shows, computed with the same queries as
     * the Blade DashboardController so the React page cannot drift from it.
     * `suppliers_total` predates them and stays for existing callers.
     */
    public function summary()
    {
        $inventoryValue = StockLevel::join('items', 'items.id', '=', 'stock_levels.item_id')
            ->select(DB::raw('SUM(stock_levels.quantity * items.cost_price) as value'))
            ->value('value') ?? 0;

        return response()->json([
            'suppliers_total' => Supplier::count(),
            'total_sales' => (float) SalesInvoice::sum('total_amount'),
            'inventory_value' => (float) $inventoryValue,
            'production_in_progress' => ProductionOrder::where('status', 'in_progress')->count(),
            'purchase_pending' => PurchaseRequest::where('stage', '!=', 'complete')->count(),
            'outstanding_receivables' => (float) SalesInvoice::whereIn('status', ['unpaid', 'partial'])
                ->sum(DB::raw('total_amount - paid_amount')),
        ]);
    }
}
