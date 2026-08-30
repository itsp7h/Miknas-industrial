<?php

namespace App\Http\Controllers\Api\Production;

use App\Events\ProductionOrderDeleted;
use App\Events\ProductionOrderSaved;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductionOrderResource;
use App\Models\Item;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Notifications\Production\ProductionOrderCompletedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class ProductionOrderController extends Controller
{
    public const STATUSES = ['planned', 'in_progress', 'completed', 'cancelled'];

    public function index()
    {
        return ProductionOrderResource::collection(
            ProductionOrder::with('product')->latest()->get()
        );
    }

    public function show(ProductionOrder $productionOrder)
    {
        return new ProductionOrderResource($productionOrder->load('product'));
    }

    public function formOptions()
    {
        return response()->json([
            // You produce finished goods or work in progress, not raw material.
            'products' => Item::where('is_active', true)
                ->whereIn('category', ['finished_good', 'wip'])
                ->orderBy('item_name')
                ->get(['id', 'item_code', 'item_name', 'unit_of_measure']),
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $order = ProductionOrder::create($this->validated($request) + [
            'order_number' => $this->nextOrderNumber(),
            'quantity_produced' => 0,
            'status' => 'planned',
            'created_by' => auth()->id(),
        ]);

        event(new ProductionOrderSaved($order));

        return (new ProductionOrderResource($order->load('product')))->response()->setStatusCode(201);
    }

    public function update(Request $request, ProductionOrder $productionOrder)
    {
        // Once production starts, the target and product are what the floor is
        // already working to.
        abort_unless($productionOrder->status === 'planned', 422, 'Only a planned order can be changed.');

        $productionOrder->update($this->validated($request));

        event(new ProductionOrderSaved($productionOrder));

        return new ProductionOrderResource($productionOrder->load('product'));
    }

    public function start(ProductionOrder $productionOrder)
    {
        abort_unless($productionOrder->status === 'planned', 422, 'Only a planned order can be started.');

        $productionOrder->update(['status' => 'in_progress']);

        event(new ProductionOrderSaved($productionOrder));

        return new ProductionOrderResource($productionOrder->load('product'));
    }

    /**
     * Guarded, unlike the Blade version: completing an order twice re-sent the
     * WhatsApp alert to every production manager each time.
     */
    public function complete(ProductionOrder $productionOrder)
    {
        abort_unless(
            $productionOrder->status === 'in_progress',
            422,
            'Only an order that is in progress can be completed.'
        );

        $productionOrder->update(['status' => 'completed', 'completion_date' => now()]);

        event(new ProductionOrderSaved($productionOrder));

        Notification::send(
            User::role('Production Manager')->whereNotNull('whatsapp_number')->get(),
            new ProductionOrderCompletedNotification($productionOrder)
        );

        return new ProductionOrderResource($productionOrder->load('product'));
    }

    public function destroy(ProductionOrder $productionOrder)
    {
        abort_unless($productionOrder->status === 'planned', 422, 'Only a planned order can be deleted.');
        abort_if(
            $productionOrder->materialIssues()->exists() || $productionOrder->outputs()->exists(),
            422,
            'This order already has material issues or output and cannot be deleted.'
        );

        $id = $productionOrder->id;
        $productionOrder->delete();

        event(new ProductionOrderDeleted($id));

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'product_id' => 'required|exists:items,id',
            'quantity_to_produce' => 'required|numeric|min:0.01',
            'production_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);
    }

    private function nextOrderNumber(): string
    {
        return 'PO-'.str_pad((string) (ProductionOrder::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}
