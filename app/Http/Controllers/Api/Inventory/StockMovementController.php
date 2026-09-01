<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Events\StockMovementRecorded;
use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Models\Item;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\Inventory\LowStockAlertNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class StockMovementController extends Controller
{
    /**
     * The stock_movements.type enum is ['in', 'out', 'transfer'].
     *
     * 'adjustment' used to be offered here and is NOT in that enum, so choosing
     * it passed validation and then died on a CHECK constraint — recording one
     * was impossible. A manual adjustment is an 'in' or an 'out' recorded by
     * hand, which is what the page's button does.
     *
     * 'transfer' is a valid enum value but is deliberately not offered: store()
     * increments for anything that is not 'out', and the form has a single
     * warehouse, so a "transfer" would add stock with no source to take it
     * from. Offering it would silently invent inventory. Supporting transfers
     * properly needs a destination warehouse on the model.
     */
    public const TYPES = ['in', 'out'];

    public function index()
    {
        return StockMovementResource::collection(
            StockMovement::with(['item', 'warehouse'])->latest()->get()
        );
    }

    /**
     * Reference data for the movement form — items and warehouses in one round
     * trip, so the form does not need three separate requests to render.
     */
    public function formOptions()
    {
        return response()->json([
            'items' => Item::where('is_active', true)->orderBy('item_name')
                ->get(['id', 'item_code', 'item_name', 'unit_of_measure']),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'types' => self::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_id' => 'required|exists:items,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'type' => 'required|in:'.implode(',', self::TYPES),
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
        ]);

        // The movement and the stock level it adjusts must move together —
        // a failure between them would leave the ledger disagreeing with the
        // level, which the Blade controller did not guard against.
        [$movement, $stockLevel] = DB::transaction(function () use ($data) {
            $stockLevel = StockLevel::firstOrCreate(
                ['item_id' => $data['item_id'], 'warehouse_id' => $data['warehouse_id']],
                ['quantity' => 0]
            );

            if ($data['type'] === 'out') {
                // Never go below zero.
                $stockLevel->decrement('quantity', min($data['quantity'], $stockLevel->quantity));
            } else {
                $stockLevel->increment('quantity', $data['quantity']);
            }

            $movement = StockMovement::create($data + ['created_by' => auth()->id()]);

            return [$movement, $stockLevel->refresh()];
        });

        event(new StockMovementRecorded($movement));

        $this->alertOnLowStock($data, $stockLevel);

        return (new StockMovementResource($movement->load(['item', 'warehouse'])))
            ->response()->setStatusCode(201);
    }

    private function alertOnLowStock(array $data, StockLevel $stockLevel): void
    {
        if ($data['type'] !== 'out') {
            return;
        }

        $item = Item::find($data['item_id']);

        if (! $item || ! $item->minimum_stock_level || $stockLevel->quantity > $item->minimum_stock_level) {
            return;
        }

        Notification::send(
            User::role('Store Manager')->whereNotNull('whatsapp_number')->get(),
            new LowStockAlertNotification($item, $stockLevel)
        );
    }
}
