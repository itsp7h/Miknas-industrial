<?php

namespace App\Http\Controllers\Api\Sales;

use App\Events\SalesOrderDeleted;
use App\Events\SalesOrderSaved;
use App\Http\Controllers\Controller;
use App\Http\Resources\SalesOrderResource;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Notifications\Sales\SalesOrderConfirmedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends Controller
{
    public const STATUSES = ['draft', 'confirmed', 'dispatched', 'invoiced', 'cancelled'];

    /** Once an order is confirmed it has left the editable stage. */
    private const EDITABLE_STATUSES = ['draft'];

    public function index()
    {
        return SalesOrderResource::collection(
            SalesOrder::with('customer')->latest()->get()
        );
    }

    public function show(SalesOrder $salesOrder)
    {
        return new SalesOrderResource($salesOrder->load(['customer', 'items.item', 'deliveryNotes', 'invoices']));
    }

    public function formOptions()
    {
        return response()->json([
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            // Only finished goods are sellable, matching the Blade create page.
            'items' => Item::where('is_active', true)->where('category', 'finished_good')
                ->orderBy('item_name')->get(['id', 'item_code', 'item_name', 'unit_of_measure', 'cost_price']),
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $order = DB::transaction(function () use ($data) {
            $order = SalesOrder::create([
                'order_number' => $this->nextOrderNumber(),
                'customer_id' => $data['customer_id'],
                'order_date' => $data['order_date'],
                'delivery_date' => $data['delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'total_amount' => $this->totalFor($data['items']),
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            $this->replaceItems($order, $data['items']);

            return $order;
        });

        event(new SalesOrderSaved($order));

        return (new SalesOrderResource($order->load(['customer', 'items.item'])))
            ->response()->setStatusCode(201);
    }

    public function update(Request $request, SalesOrder $salesOrder)
    {
        $this->abortUnlessEditable($salesOrder);

        $data = $this->validated($request);

        DB::transaction(function () use ($salesOrder, $data) {
            $salesOrder->update([
                'customer_id' => $data['customer_id'],
                'order_date' => $data['order_date'],
                'delivery_date' => $data['delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'total_amount' => $this->totalFor($data['items']),
            ]);

            $this->replaceItems($salesOrder, $data['items']);
        });

        event(new SalesOrderSaved($salesOrder));

        return new SalesOrderResource($salesOrder->load(['customer', 'items.item']));
    }

    public function confirm(SalesOrder $salesOrder)
    {
        abort_unless($salesOrder->status === 'draft', 422, 'Only a draft order can be confirmed.');

        $salesOrder->update(['status' => 'confirmed']);

        event(new SalesOrderSaved($salesOrder));

        $customer = $salesOrder->customer;

        if ($customer && $customer->whatsapp_number) {
            $customer->notify(new SalesOrderConfirmedNotification($salesOrder));
        }

        return new SalesOrderResource($salesOrder->load(['customer', 'items.item']));
    }

    public function destroy(SalesOrder $salesOrder)
    {
        // Deliveries and invoices hang off the order; only a draft with neither
        // can be removed outright.
        $this->abortUnlessEditable($salesOrder);
        abort_if(
            $salesOrder->deliveryNotes()->exists() || $salesOrder->invoices()->exists(),
            422,
            'This order already has deliveries or invoices and cannot be deleted.'
        );

        $id = $salesOrder->id;

        DB::transaction(function () use ($salesOrder) {
            $salesOrder->items()->delete();
            $salesOrder->delete();
        });

        event(new SalesOrderDeleted($id));

        return response()->json(['deleted' => true]);
    }

    private function abortUnlessEditable(SalesOrder $salesOrder): void
    {
        abort_unless(
            in_array($salesOrder->status, self::EDITABLE_STATUSES, true),
            422,
            'Only a draft order can be changed.'
        );
    }

    /**
     * Line fields are `price` and `total_amount` — the column names. The Blade
     * controller validated `unit_price` and wrote `unit_price`/`amount`, none
     * of which its own form posted or the model allowed, so no sales order
     * could ever be created.
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'order_date' => 'required|date',
            'delivery_date' => 'nullable|date|after_or_equal:order_date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
        ]);
    }

    private function replaceItems(SalesOrder $order, array $items): void
    {
        $order->items()->delete();

        foreach ($items as $line) {
            SalesOrderItem::create([
                'sales_order_id' => $order->id,
                'item_id' => $line['item_id'],
                'quantity' => $line['quantity'],
                'price' => $line['price'],
                'total_amount' => $line['quantity'] * $line['price'],
                'quantity_delivered' => 0,
            ]);
        }
    }

    private function totalFor(array $items): float
    {
        return collect($items)->sum(fn ($line) => $line['quantity'] * $line['price']);
    }

    private function nextOrderNumber(): string
    {
        return 'SO-'.str_pad((string) (SalesOrder::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}
