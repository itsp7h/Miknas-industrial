<?php

namespace App\Http\Controllers\Api\Production;

use App\Events\ProductionFlowRecorded;
use App\Events\ProductionOrderSaved;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductionOutputResource;
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
        return ProductionOutputResource::collection(
            ProductionOutput::with(['productionOrder', 'item', 'warehouse'])->latest()->get()
        );
    }

    public function formOptions()
    {
        return response()->json([
            'production_orders' => ProductionOrder::with('product')
                ->whereIn('status', ['planned', 'in_progress'])
                ->latest()
                ->get()
                ->map(fn ($order) => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'product_id' => $order->product_id,
                    'product_name' => $order->product?->item_name,
                    'quantity_to_produce' => $order->quantity_to_produce,
                    'quantity_produced' => $order->quantity_produced,
                ]),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            // Blade let the item be chosen explicitly — normally the order's own
            // product, but a run can yield a by-product or a different grade.
            'products' => Item::where('is_active', true)
                ->whereIn('category', ['finished_good', 'wip'])
                ->orderBy('item_name')
                ->get(['id', 'item_code', 'item_name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'production_order_id' => 'required|exists:production_orders,id',
            'item_id' => 'required|exists:items,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.01',
            'output_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $order = ProductionOrder::findOrFail($data['production_order_id']);

        abort_if($order->status === 'completed', 422, 'That production order is already complete.');
        abort_if($order->status === 'cancelled', 422, 'That production order was cancelled.');

        $output = DB::transaction(function () use ($data, $order) {
            $stockLevel = StockLevel::firstOrCreate(
                ['item_id' => $data['item_id'], 'warehouse_id' => $data['warehouse_id']],
                ['quantity' => 0]
            );
            $stockLevel->increment('quantity', $data['quantity']);

            $output = ProductionOutput::create($data + ['recorded_by' => auth()->id()]);

            StockMovement::create([
                'item_id' => $data['item_id'],
                'warehouse_id' => $data['warehouse_id'],
                'type' => 'in',
                'quantity' => $data['quantity'],
                'reference_type' => 'ProductionOutput',
                'reference_id' => $output->id,
                'created_by' => auth()->id(),
            ]);

            $order->increment('quantity_produced', $data['quantity']);

            return $output;
        });

        $output->load(['productionOrder', 'item', 'warehouse']);

        event(new ProductionFlowRecorded('production-output', (new ProductionOutputResource($output))->resolve()));
        // The order's produced quantity changed, so its row updates live too.
        event(new ProductionOrderSaved($order->fresh()));

        return (new ProductionOutputResource($output))->response()->setStatusCode(201);
    }
}
