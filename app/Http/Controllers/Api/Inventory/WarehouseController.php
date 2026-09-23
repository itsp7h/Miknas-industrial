<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Events\WarehouseDeleted;
use App\Events\WarehouseSaved;
use App\Http\Controllers\Controller;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function index()
    {
        return WarehouseResource::collection(Warehouse::orderBy('name')->get());
    }

    /**
     * One warehouse and everything stocked in it.
     *
     * Zero-quantity lines are included: a warehouse's inventory sheet lists
     * what it carries, and an item assigned here but not yet delivered is
     * still something this warehouse is responsible for.
     */
    public function show(Warehouse $warehouse)
    {
        $lines = $warehouse->stockLevels()
            ->with('item.itemCategory')
            ->get()
            ->filter(fn ($level) => (bool) $level->item)
            ->map(function ($level) {
                $item = $level->item;
                $quantity = (float) $level->quantity;
                $cost = (float) $item->cost_price;

                return [
                    'id' => $level->id,
                    'item_id' => $item->id,
                    'item_code' => $item->item_code,
                    'item_name' => $item->item_name,
                    'category' => $item->category,
                    'category_path' => $item->categoryPath(),
                    'item_category_name' => $item->itemCategory?->name,
                    'unit_of_measure' => $item->unit_of_measure,
                    'quantity' => $quantity,
                    'minimum_stock_level' => (float) $item->minimum_stock_level,
                    'cost_price' => $cost,
                    'total_value' => round($quantity * $cost, 3),
                    'is_active' => (bool) $item->is_active,
                ];
            })
            ->sortBy('item_name')
            ->values();

        return (new WarehouseResource($warehouse))->additional([
            'items' => $lines,
            'meta' => [
                'total_items' => $lines->count(),
                'raw_material_count' => $lines->where('category', 'raw_material')->count(),
                'finished_good_count' => $lines->where('category', 'finished_good')->count(),
                'total_value' => round($lines->sum('total_value'), 3),
                // What the list already flags in red, counted for the header.
                'below_minimum' => $lines
                    ->filter(fn ($line) => $line['minimum_stock_level'] > 0
                        && $line['quantity'] < $line['minimum_stock_level'])
                    ->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $warehouse = Warehouse::create($this->validated($request));

        event(new WarehouseSaved($warehouse));

        return (new WarehouseResource($warehouse))->response()->setStatusCode(201);
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $warehouse->update($this->validated($request, $warehouse));

        event(new WarehouseSaved($warehouse));

        return new WarehouseResource($warehouse);
    }

    public function destroy(Warehouse $warehouse)
    {
        // Six tables reference a warehouse. Deleting one that is used would
        // orphan the ledger (stock levels and movements cascade) or be refused
        // outright (the rest restrict), so a used warehouse is deactivated.
        if ($used = $warehouse->dependentCounts()) {
            return $this->deactivate($warehouse, $used);
        }

        $id = $warehouse->id;

        try {
            $warehouse->delete();
        } catch (QueryException) {
            // A reference this list does not know about yet. Deactivating is
            // still the right answer, and it keeps the SQL — and the database
            // path — out of the user's face.
            return $this->deactivate($warehouse, []);
        }

        event(new WarehouseDeleted($id));

        return response()->json(['deleted' => true]);
    }

    /** Retires a warehouse that cannot go, saying what is holding it. */
    private function deactivate(Warehouse $warehouse, array $used)
    {
        $warehouse->update(['is_active' => false]);
        event(new WarehouseSaved($warehouse));

        return response()->json([
            'message' => $used
                ? 'Warehouse is used by '.$this->listCounts($used).', so it was deactivated rather than deleted.'
                : 'Warehouse is still in use, so it was deactivated rather than deleted.',
            'deactivated' => true,
            'data' => new WarehouseResource($warehouse),
        ]);
    }

    /** ['delivery note' => 2, 'stock level' => 1] → "2 delivery notes and 1 stock level". */
    private function listCounts(array $used): string
    {
        $parts = [];
        foreach ($used as $label => $count) {
            $parts[] = $count.' '.$label.($count === 1 ? '' : 's');
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        $last = array_pop($parts);

        return implode(', ', $parts).' and '.$last;
    }

    /**
     * `code` is NOT NULL with no default. The Blade controller this replaces
     * validated only name/location, so submitting the form with an empty code
     * reached the insert and failed with a database error rather than a
     * validation message.
     */
    private function validated(Request $request, ?Warehouse $warehouse = null): array
    {
        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('warehouses', 'code')->ignore($warehouse?->id),
            ],
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            // A point is both numbers or neither — one on its own would put the
            // pin on the equator or the prime meridian rather than nowhere.
            'latitude' => 'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude' => 'nullable|numeric|between:-180,180|required_with:latitude',
            'description' => 'nullable|string',
        ]);

        $data['is_active'] = (bool) $request->input('is_active', true);

        return $data;
    }
}
