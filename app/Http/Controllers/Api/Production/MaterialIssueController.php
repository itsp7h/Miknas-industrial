<?php

namespace App\Http\Controllers\Api\Production;

use App\Events\ProductionFlowRecorded;
use App\Http\Controllers\Controller;
use App\Http\Resources\MaterialIssueResource;
use App\Models\MaterialIssue;
use App\Models\ProductionOrder;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterialIssueController extends Controller
{
    public function index()
    {
        return MaterialIssueResource::collection(
            MaterialIssue::with(['productionOrder', 'item', 'warehouse'])->latest()->get()
        );
    }

    public function formOptions()
    {
        return response()->json([
            // Blade labelled each option "order number - product", so the
            // product has to come along.
            'production_orders' => ProductionOrder::with('product')
                ->whereIn('status', ['planned', 'in_progress'])
                ->latest()
                ->get()
                ->map(fn ($order) => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'product_name' => $order->product?->item_name,
                    'status' => $order->status,
                ])->values(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            // Stock on hand, so the form can show what is actually available.
            'stock' => StockLevel::with('item')->get()->map(fn ($level) => [
                'item_id' => $level->item_id,
                'item_name' => $level->item?->item_name,
                'warehouse_id' => $level->warehouse_id,
                'quantity' => $level->quantity,
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'production_order_id' => 'required|exists:production_orders,id',
            'item_id' => 'required|exists:items,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.01',
            'issue_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $stockLevel = StockLevel::firstOrCreate(
            ['item_id' => $data['item_id'], 'warehouse_id' => $data['warehouse_id']],
            ['quantity' => 0]
        );

        // The Blade version clamped the decrement at zero and issued anyway, so
        // the ledger recorded material that was never there. Refuse instead.
        if ((float) $data['quantity'] > (float) $stockLevel->quantity) {
            throw ValidationException::withMessages([
                'quantity' => ["Only {$stockLevel->quantity} of that item is on hand in this warehouse."],
            ]);
        }

        $issue = DB::transaction(function () use ($data, $stockLevel) {
            $stockLevel->decrement('quantity', $data['quantity']);

            $issue = MaterialIssue::create($data + [
                'issue_number' => $this->nextIssueNumber(),
                'issued_by' => auth()->id(),
            ]);

            // Created after the issue so the reference points at a real row —
            // the Blade version wrote the movement first with a null reference
            // and then tried to find it again to backfill.
            StockMovement::create([
                'item_id' => $data['item_id'],
                'warehouse_id' => $data['warehouse_id'],
                'type' => 'out',
                'quantity' => $data['quantity'],
                'reference_type' => 'MaterialIssue',
                'reference_id' => $issue->id,
                'created_by' => auth()->id(),
            ]);

            return $issue;
        });

        $issue->load(['productionOrder', 'item', 'warehouse']);

        event(new ProductionFlowRecorded('material-issue', (new MaterialIssueResource($issue))->resolve()));

        return (new MaterialIssueResource($issue))->response()->setStatusCode(201);
    }

    private function nextIssueNumber(): string
    {
        return 'MI-'.str_pad((string) (MaterialIssue::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}
