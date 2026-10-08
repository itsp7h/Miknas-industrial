<?php

namespace App\Http\Controllers\Api;

use App\Events\DashboardPinged;
use App\Http\Controllers\Api\Purchase\PurchasePipelineController;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceiptNote;
use App\Models\GrnDocument;
use App\Models\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SalesInvoice;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\Warehouse;
use App\Services\PurchaseStageService;
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

    /**
     * What the mobile Home and More screens show beyond the KPIs: the pipeline
     * split by stage, the things waiting on this person, and the counts beside
     * each More entry.
     *
     * Every block is answered only for someone who could open the page it
     * summarises, so the figures never say more than the pages would. A block
     * this person cannot see comes back null, and the screen leaves it out.
     */
    public function overview(Request $request)
    {
        $user = $request->user();
        $can = fn (string $permission) => $user->can($permission);

        $pipeline = null;
        if ($can('pipeline.view')) {
            $byStage = PurchasePipelineController::visibleTo(PurchaseRequest::query(), $user)
                ->select('stage', DB::raw('COUNT(*) as total'))
                ->groupBy('stage')
                ->pluck('total', 'stage');

            $pipeline = [
                'active' => (int) $byStage->except('complete')->sum(),
                'completed' => (int) ($byStage['complete'] ?? 0),
                'stages' => collect(PurchaseStageService::STAGES)
                    ->reject(fn ($stage) => $stage === 'complete')
                    ->map(fn ($stage) => [
                        'key' => $stage,
                        'count' => (int) ($byStage[$stage] ?? 0),
                    ])
                    ->values(),
            ];
        }

        return response()->json([
            'pipeline' => $pipeline,
            'actions' => $this->actions($user),
            'low_stock' => $can('low-stock.view') ? $this->lowStockCount() : null,
            'counts' => array_filter([
                'suppliers' => $can('suppliers.view') ? Supplier::count() : null,
                'purchase_orders' => $can('purchase-orders.view') ? PurchaseOrder::count() : null,
                'grns' => $can('goods-receipts.view') ? GoodsReceiptNote::count() : null,
                'supplier_invoices' => $can('supplier-invoices.view') ? SupplierInvoice::count() : null,
                'unpaid_invoices' => $can('supplier-invoices.view')
                    ? SupplierInvoice::whereIn('status', ['unpaid', 'partial'])->count()
                    : null,
                'warehouses' => $can('warehouses.view') ? Warehouse::count() : null,
            ], fn ($value) => $value !== null),
        ]);
    }

    /** "Needs your action": only what this person is the one to do. */
    private function actions($user): array
    {
        $actions = [];

        if ($user->can('pipeline.approve')) {
            $unsigned = PurchaseRequest::where('stage', 'draft')
                ->where('status', 'pending')
                ->whereDoesntHave('signature')
                ->oldest()
                ->get(['id', 'request_number', 'department']);

            if ($unsigned->isNotEmpty()) {
                $first = $unsigned->first();
                $actions[] = [
                    'kind' => 'gm_signature',
                    'count' => $unsigned->count(),
                    'request_id' => $first->id,
                    'reference' => $first->request_number,
                    'detail' => $first->department,
                ];
            }
        }

        if ($user->can('goods-receipts.view')) {
            $drafts = GoodsReceiptNote::where('status', 'draft')->with('documents:id,goods_receipt_note_id,kind')
                ->oldest()->get(['id', 'grn_number']);

            if ($drafts->isNotEmpty()) {
                $first = $drafts->first();
                $have = $first->documents->pluck('kind')->all();
                $actions[] = [
                    'kind' => 'draft_grns',
                    'count' => $drafts->count(),
                    'grn_id' => $first->id,
                    'reference' => $first->grn_number,
                    'missing' => collect(GrnDocument::KINDS)
                        ->reject(fn ($label, $kind) => in_array($kind, $have, true))
                        ->values(),
                ];
            }
        }

        if ($user->can('supplier-invoices.view')) {
            $unpaid = SupplierInvoice::whereIn('status', ['unpaid', 'partial']);
            $count = (clone $unpaid)->count();

            if ($count > 0) {
                $actions[] = [
                    'kind' => 'unpaid_invoices',
                    'count' => $count,
                    'outstanding' => (float) $unpaid->sum(DB::raw('total_amount - paid_amount')),
                ];
            }
        }

        return $actions;
    }

    /** Same rule as the Low Stock report: below the item's minimum, per warehouse line. */
    private function lowStockCount(): int
    {
        return StockLevel::join('items', 'items.id', '=', 'stock_levels.item_id')
            ->whereColumn('stock_levels.quantity', '<', 'items.minimum_stock_level')
            ->count();
    }
}
