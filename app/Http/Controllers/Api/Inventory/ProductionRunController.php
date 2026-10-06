<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Events\ItemSaved;
use App\Events\ProductionRunRecorded;
use App\Events\RecipeSaved;
use App\Events\StockMovementRecorded;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductionRunResource;
use App\Models\BillOfMaterial;
use App\Models\Item;
use App\Models\ProductionRun;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Inventory → Production: make a quantity of a finished good out of raw
 * materials, at cost.
 *
 * A run is one transaction: the finished good goes into its warehouse, each
 * raw material comes out of the warehouse it was taken from, every change is a
 * stock movement, and the finished good's cost price becomes what one unit of
 * this run cost. Posted, never edited — a correction is a stock movement.
 *
 * The recipe each run starts from is the product's bill of materials, the same
 * table the parked Production module's BOM page writes.
 */
class ProductionRunController extends Controller
{
    private const RELATIONS = ['item', 'warehouse', 'creator', 'lines.item', 'lines.warehouse'];

    public function index()
    {
        return ProductionRunResource::collection(
            ProductionRun::with(self::RELATIONS)->latest('id')->get()
        );
    }

    /**
     * What the run form and the recipe editor need, in one trip: the products
     * with their recipes, the materials with their cost, and what is on hand
     * where — so the form can say "12 on hand" before anyone presses Save.
     */
    public function formOptions()
    {
        $recipes = BillOfMaterial::orderBy('id')->get()->groupBy('product_id');

        return response()->json([
            'finished_goods' => Item::where('category', 'finished_good')->where('is_active', true)
                ->orderBy('item_name')->get(['id', 'item_code', 'item_name', 'unit_of_measure', 'cost_price'])
                ->map(fn (Item $item) => $item->toArray() + [
                    'recipe' => $this->recipeOf($recipes->get($item->id, collect())),
                ]),
            'raw_materials' => Item::where('category', 'raw_material')->where('is_active', true)
                ->orderBy('item_name')->get(['id', 'item_code', 'item_name', 'unit_of_measure', 'cost_price']),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'stock' => StockLevel::where('quantity', '>', 0)
                ->whereHas('item', fn ($q) => $q->where('category', 'raw_material'))
                ->get(['item_id', 'warehouse_id', 'quantity']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_id' => ['required', Rule::exists('items', 'id')->where('category', 'finished_good')],
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.01',
            'production_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => ['required', 'distinct', Rule::exists('items', 'id')->where('category', 'raw_material')],
            'lines.*.warehouse_id' => 'required|exists:warehouses,id',
            'lines.*.quantity' => 'required|numeric|min:0.01',
        ], [
            'item_id.exists' => 'Choose a finished good to make.',
            'lines.required' => 'Add at least one raw material.',
            'lines.min' => 'Add at least one raw material.',
            'lines.*.item_id.distinct' => 'Each raw material can be listed only once.',
            'lines.*.item_id.exists' => 'Choose a raw material.',
            'lines.*.warehouse_id.required' => 'Choose the warehouse it comes from.',
            'lines.*.quantity.min' => 'The quantity used must be more than zero.',
        ]);

        [$run, $movements] = DB::transaction(function () use ($data) {
            $materials = Item::whereIn('id', array_column($data['lines'], 'item_id'))->get()->keyBy('id');

            $lines = [];
            foreach (array_values($data['lines']) as $index => $line) {
                $material = $materials[$line['item_id']];
                $level = StockLevel::where('item_id', $line['item_id'])
                    ->where('warehouse_id', $line['warehouse_id'])->first();
                $onHand = (float) ($level?->quantity ?? 0);

                if ($onHand < (float) $line['quantity']) {
                    $where = Warehouse::find($line['warehouse_id'])?->name ?? 'that warehouse';

                    throw ValidationException::withMessages([
                        "lines.{$index}.quantity" => sprintf(
                            'Only %s %s of %s on hand in %s.',
                            number_format($onHand, 2), $material->unit_of_measure, $material->item_name, $where
                        ),
                    ]);
                }

                $unitCost = (float) $material->cost_price;
                $lines[] = $line + [
                    'level' => $level,
                    'unit_cost' => $unitCost,
                    'line_cost' => round((float) $line['quantity'] * $unitCost, 3),
                ];
            }

            $total = round(array_sum(array_column($lines, 'line_cost')), 3);

            $run = ProductionRun::create([
                'run_number' => $this->nextRunNumber(),
                'item_id' => $data['item_id'],
                'warehouse_id' => $data['warehouse_id'],
                'quantity' => $data['quantity'],
                'production_date' => $data['production_date'],
                'total_cost' => $total,
                'unit_cost' => $total / (float) $data['quantity'],
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $movements = [];
            foreach ($lines as $line) {
                $run->lines()->create([
                    'item_id' => $line['item_id'],
                    'warehouse_id' => $line['warehouse_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'line_cost' => $line['line_cost'],
                ]);
                $line['level']->decrement('quantity', $line['quantity']);
                $movements[] = $this->movement($run, $line['item_id'], $line['warehouse_id'], 'out', $line['quantity']);
            }

            StockLevel::firstOrCreate(
                ['item_id' => $data['item_id'], 'warehouse_id' => $data['warehouse_id']],
                ['quantity' => 0]
            )->increment('quantity', $data['quantity']);
            $movements[] = $this->movement($run, $data['item_id'], $data['warehouse_id'], 'in', $data['quantity']);

            // A run whose materials carry no cost price says nothing about what
            // the product costs, so it must not zero a price someone set.
            if ($total > 0) {
                Item::whereKey($data['item_id'])->update(['cost_price' => round((float) $run->unit_cost, 3)]);
            }

            return [$run, $movements];
        });

        foreach ($movements as $movement) {
            event(new StockMovementRecorded($movement));
        }
        // Their stock changed, and the product's cost price may have: the item
        // lists show both.
        Item::whereIn('id', [$run->item_id, ...array_column($data['lines'], 'item_id')])->get()
            ->each(fn (Item $item) => event(new ItemSaved($item)));
        event(new ProductionRunRecorded($run->load(self::RELATIONS)));

        return (new ProductionRunResource($run))
            ->additional(['message' => "{$run->run_number} recorded — stock updated."])
            ->response()->setStatusCode(201);
    }

    /** Replaces a finished good's recipe: what one unit of it takes. */
    public function updateRecipe(Request $request, Item $item)
    {
        abort_unless($item->category === 'finished_good', 422, 'Only a finished good has a recipe.');

        $data = $request->validate([
            'lines' => 'present|array',
            'lines.*.raw_material_id' => ['required', 'distinct', Rule::exists('items', 'id')->where('category', 'raw_material')],
            'lines.*.quantity_required' => 'required|numeric|min:0.01',
        ], [
            'lines.*.raw_material_id.distinct' => 'Each raw material can be listed only once.',
            'lines.*.raw_material_id.exists' => 'Choose a raw material.',
            'lines.*.quantity_required.min' => 'The quantity per unit must be more than zero.',
        ]);

        $recipe = DB::transaction(function () use ($item, $data) {
            BillOfMaterial::where('product_id', $item->id)->delete();

            $units = Item::whereIn('id', array_column($data['lines'], 'raw_material_id'))->pluck('unit_of_measure', 'id');

            foreach ($data['lines'] as $line) {
                BillOfMaterial::create([
                    'product_id' => $item->id,
                    'raw_material_id' => $line['raw_material_id'],
                    'quantity_required' => $line['quantity_required'],
                    'unit_of_measure' => $units[$line['raw_material_id']] ?? null,
                ]);
            }

            return $this->recipeOf(BillOfMaterial::where('product_id', $item->id)->orderBy('id')->get());
        });

        event(new RecipeSaved($item->id, $recipe));

        return response()->json([
            'data' => ['product_id' => $item->id, 'recipe' => $recipe],
            'message' => "Recipe for {$item->item_name} saved.",
        ]);
    }

    private function recipeOf($lines): array
    {
        return $lines->map(fn (BillOfMaterial $line) => [
            'raw_material_id' => $line->raw_material_id,
            'quantity_required' => $line->quantity_required,
        ])->values()->all();
    }

    private function movement(ProductionRun $run, int $itemId, int $warehouseId, string $type, $quantity): StockMovement
    {
        return StockMovement::create([
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'type' => $type,
            'quantity' => $quantity,
            'reference_type' => 'ProductionRun',
            'reference_id' => $run->id,
            'notes' => $run->run_number,
            'created_by' => auth()->id(),
        ]);
    }

    private function nextRunNumber(): string
    {
        return 'PRD-'.str_pad((string) (ProductionRun::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}
