<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Events\WarehouseDeleted;
use App\Events\WarehouseSaved;
use App\Http\Controllers\Controller;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function index()
    {
        return WarehouseResource::collection(Warehouse::orderBy('name')->get());
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
        // Stock levels and movements reference the warehouse; deleting one that
        // holds either would orphan the ledger, so it is deactivated instead.
        if ($warehouse->stockMovements()->exists() || $warehouse->stockLevels()->exists()) {
            $warehouse->update(['is_active' => false]);
            event(new WarehouseSaved($warehouse));

            return response()->json([
                'message' => 'Warehouse has stock records, so it was deactivated rather than deleted.',
                'deactivated' => true,
            ]);
        }

        $id = $warehouse->id;
        $warehouse->delete();

        event(new WarehouseDeleted($id));

        return response()->json(['deleted' => true]);
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
            'description' => 'nullable|string',
        ]);

        $data['is_active'] = (bool) $request->input('is_active', true);

        return $data;
    }
}
