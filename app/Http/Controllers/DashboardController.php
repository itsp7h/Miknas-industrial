<?php

namespace App\Http\Controllers;

use App\Models\ProductionOrder;
use App\Models\PurchaseRequest;
use App\Models\SalesInvoice;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $totalSales = SalesInvoice::sum('total_amount');

        $inventoryValue = StockLevel::join('items', 'items.id', '=', 'stock_levels.item_id')
            ->select(DB::raw('SUM(stock_levels.quantity * items.cost_price) as value'))
            ->value('value') ?? 0;

        $productionInProgress = ProductionOrder::where('status', 'in_progress')->count();

        $purchasePending = PurchaseRequest::where('stage', '!=', 'complete')->count();

        $outstandingReceivables = SalesInvoice::whereIn('status', ['unpaid', 'partial'])
            ->sum(DB::raw('total_amount - paid_amount'));

        return view('dashboard', compact(
            'totalSales',
            'inventoryValue',
            'productionInProgress',
            'purchasePending',
            'outstandingReceivables'
        ));
    }
}
