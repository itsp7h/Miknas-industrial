<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Customer;
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

class DeliveryNoteController extends Controller
{
    public function index()
    {
        $notes = DeliveryNote::with(['salesOrder.customer', 'warehouse'])->paginate(15);

        return view('sales.delivery-notes.index', compact('notes'));
    }

    public function create()
    {
        $salesOrders = SalesOrder::where('status', 'confirmed')->with('customer')->get();
        $warehouses  = Warehouse::all();

        return view('sales.delivery-notes.create', compact('salesOrders', 'warehouses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'sales_order_id'       => 'required|exists:sales_orders,id',
            'warehouse_id'         => 'required|exists:warehouses,id',
            'delivery_date'        => 'required|date',
            'items'                => 'required|array|min:1',
            'items.*.item_id'      => 'required|exists:items,id',
            'items.*.quantity'     => 'required|numeric|min:1',
        ]);

        $deliveryNumber = 'DN-' . str_pad(DeliveryNote::max('id') + 1, 5, '0', STR_PAD_LEFT);

        $note = DeliveryNote::create([
            'delivery_number' => $deliveryNumber,
            'sales_order_id'  => $request->sales_order_id,
            'warehouse_id'    => $request->warehouse_id,
            'delivery_date'   => $request->delivery_date,
            'status'          => 'draft',
        ]);

        $salesOrder = SalesOrder::with('items')->findOrFail($request->sales_order_id);

        foreach ($request->items as $item) {
            $soItem = $salesOrder->items->firstWhere('item_id', $item['item_id']);
            DeliveryNoteItem::create([
                'delivery_note_id'    => $note->id,
                'sales_order_item_id' => $soItem?->id ?? 0,
                'item_id'             => $item['item_id'],
                'quantity_delivered'  => $item['quantity'],
            ]);
        }

        return redirect()->route('sales.delivery-notes.show', $note)->with('success', 'Delivery note created successfully.');
    }

    public function show(DeliveryNote $deliveryNote)
    {
        $deliveryNote->load(['salesOrder.customer', 'warehouse', 'items.item']);

        return view('sales.delivery-notes.show', compact('deliveryNote'));
    }

    public function edit(DeliveryNote $deliveryNote)
    {
        $warehouses = Warehouse::all();

        return view('sales.delivery-notes.edit', compact('deliveryNote', 'warehouses'));
    }

    public function update(Request $request, DeliveryNote $deliveryNote)
    {
        $request->validate([
            'delivery_date' => 'required|date',
            'warehouse_id'  => 'required|exists:warehouses,id',
        ]);

        $deliveryNote->update($request->only('delivery_date', 'warehouse_id'));

        return redirect()->route('sales.delivery-notes.show', $deliveryNote)->with('success', 'Delivery note updated successfully.');
    }

    public function destroy(DeliveryNote $deliveryNote)
    {
        $deliveryNote->delete();

        return redirect()->route('sales.delivery-notes.index')->with('success', 'Delivery note deleted successfully.');
    }

    public function dispatch(DeliveryNote $deliveryNote)
    {
        DB::transaction(function () use ($deliveryNote) {
            $deliveryNote->load(['items', 'salesOrder.items']);

            foreach ($deliveryNote->items as $dnItem) {
                $stockLevel = StockLevel::firstOrCreate(
                    ['item_id' => $dnItem->item_id, 'warehouse_id' => $deliveryNote->warehouse_id],
                    ['quantity' => 0]
                );

                $decrement = min($dnItem->quantity_delivered, $stockLevel->quantity);
                $stockLevel->decrement('quantity', $decrement);

                StockMovement::create([
                    'item_id'        => $dnItem->item_id,
                    'warehouse_id'   => $deliveryNote->warehouse_id,
                    'type'           => 'out',
                    'quantity'       => $dnItem->quantity_delivered,
                    'reference_type' => 'DeliveryNote',
                    'reference_id'   => $deliveryNote->id,
                    'created_by'     => auth()->id(),
                ]);

                SalesOrderItem::where('sales_order_id', $deliveryNote->sales_order_id)
                    ->where('item_id', $dnItem->item_id)
                    ->increment('quantity_delivered', $dnItem->quantity_delivered);
            }

            $deliveryNote->update([
                'status'        => 'dispatched',
                'dispatched_by' => auth()->id(),
            ]);

            // Check if all sales order items are fully delivered
            $salesOrder      = $deliveryNote->salesOrder->fresh(['items']);
            $allFullyDelivered = $salesOrder->items->every(
                fn($soItem) => $soItem->quantity_delivered >= $soItem->quantity
            );

            if ($allFullyDelivered) {
                $salesOrder->update(['status' => 'dispatched']);
            }
        });

        $deliveryNote->loadMissing('salesOrder.customer');
        $customer = $deliveryNote->salesOrder?->customer;
        if ($customer && $customer->whatsapp_number) {
            $customer->notify(new DeliveryDispatchedNotification($deliveryNote));
        }

        return redirect()->back()->with('success', 'Delivery note dispatched and stock decremented.');
    }
}
