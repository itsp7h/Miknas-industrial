<?php

namespace App\Http\Controllers\Api\Sales;

use App\Events\DeliveryNoteSaved;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryNoteResource;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Notifications\Sales\DeliveryDispatchedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryNoteController extends Controller
{
    public function index()
    {
        return DeliveryNoteResource::collection(
            DeliveryNote::with(['salesOrder', 'customer', 'warehouse'])->latest()->get()
        );
    }

    public function show(DeliveryNote $deliveryNote)
    {
        return new DeliveryNoteResource(
            $deliveryNote->load(['salesOrder', 'customer', 'warehouse', 'items.item'])
        );
    }

    /**
     * Only confirmed orders can be delivered against, and each line reports how
     * much is still outstanding so the form cannot over-deliver by accident.
     */
    public function formOptions()
    {
        $orders = SalesOrder::with(['customer', 'items.item'])
            ->whereIn('status', ['confirmed', 'dispatched'])
            ->latest()
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_id' => $order->customer_id,
                'customer_name' => $order->customer?->name,
                'items' => $order->items->map(fn ($line) => [
                    'sales_order_item_id' => $line->id,
                    'item_id' => $line->item_id,
                    'item_name' => $line->item?->item_name,
                    'quantity' => $line->quantity,
                    'quantity_delivered' => $line->quantity_delivered,
                    'outstanding' => round(((float) $line->quantity) - ((float) $line->quantity_delivered), 2),
                ])->values(),
            ]);

        return response()->json([
            'orders' => $orders,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sales_order_id' => 'required|exists:sales_orders,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'delivery_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        $order = SalesOrder::with('items')->findOrFail($data['sales_order_id']);

        $this->guardAgainstOverDelivery($order, $data['items']);

        $note = DB::transaction(function () use ($data, $order) {
            $note = DeliveryNote::create([
                'delivery_number' => $this->nextDeliveryNumber(),
                'sales_order_id' => $order->id,
                // NOT NULL on delivery_notes, and never set by the Blade
                // controller — every create failed with a database error.
                // It is a property of the order, not something to re-enter.
                'customer_id' => $order->customer_id,
                'warehouse_id' => $data['warehouse_id'],
                'delivery_date' => $data['delivery_date'],
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
            ]);

            foreach ($data['items'] as $line) {
                $orderLine = $order->items->firstWhere('item_id', $line['item_id']);

                DeliveryNoteItem::create([
                    'delivery_note_id' => $note->id,
                    'sales_order_item_id' => $orderLine->id,
                    'item_id' => $line['item_id'],
                    'quantity_delivered' => $line['quantity'],
                ]);
            }

            return $note;
        });

        event(new DeliveryNoteSaved($note));

        return (new DeliveryNoteResource($note->load(['salesOrder', 'customer', 'warehouse', 'items.item'])))
            ->response()->setStatusCode(201);
    }

    public function dispatchNote(DeliveryNote $deliveryNote)
    {
        abort_unless($deliveryNote->status === 'draft', 422, 'This delivery note has already been dispatched.');

        DB::transaction(function () use ($deliveryNote) {
            $deliveryNote->load(['items']);

            foreach ($deliveryNote->items as $line) {
                $stockLevel = StockLevel::firstOrCreate(
                    ['item_id' => $line->item_id, 'warehouse_id' => $deliveryNote->warehouse_id],
                    ['quantity' => 0]
                );

                $stockLevel->decrement('quantity', min($line->quantity_delivered, $stockLevel->quantity));

                StockMovement::create([
                    'item_id' => $line->item_id,
                    'warehouse_id' => $deliveryNote->warehouse_id,
                    'type' => 'out',
                    'quantity' => $line->quantity_delivered,
                    'reference_type' => 'DeliveryNote',
                    'reference_id' => $deliveryNote->id,
                    'created_by' => auth()->id(),
                ]);

                SalesOrderItem::where('id', $line->sales_order_item_id)
                    ->increment('quantity_delivered', $line->quantity_delivered);
            }

            $deliveryNote->update(['status' => 'dispatched', 'dispatched_by' => auth()->id()]);

            $order = $deliveryNote->salesOrder->fresh(['items']);

            if ($order->items->every(fn ($line) => $line->quantity_delivered >= $line->quantity)) {
                $order->update(['status' => 'dispatched']);
            }
        });

        event(new DeliveryNoteSaved($deliveryNote));

        $customer = $deliveryNote->loadMissing('customer')->customer;

        if ($customer && $customer->whatsapp_number) {
            $customer->notify(new DeliveryDispatchedNotification($deliveryNote));
        }

        return new DeliveryNoteResource(
            $deliveryNote->load(['salesOrder', 'customer', 'warehouse', 'items.item'])
        );
    }

    /**
     * Delivering more than the order calls for silently corrupts the order's
     * delivered totals, which is what drives its dispatched status.
     */
    private function guardAgainstOverDelivery(SalesOrder $order, array $lines): void
    {
        $errors = [];

        foreach ($lines as $index => $line) {
            $orderLine = $order->items->firstWhere('item_id', $line['item_id']);

            if (! $orderLine) {
                $errors["items.{$index}.item_id"] = ['That item is not on this sales order.'];

                continue;
            }

            $outstanding = ((float) $orderLine->quantity) - ((float) $orderLine->quantity_delivered);

            if ((float) $line['quantity'] > $outstanding) {
                $errors["items.{$index}.quantity"] = ["Only {$outstanding} remain undelivered on this line."];
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function nextDeliveryNumber(): string
    {
        return 'DN-'.str_pad((string) (DeliveryNote::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}
