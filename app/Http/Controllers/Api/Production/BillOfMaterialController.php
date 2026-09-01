<?php

namespace App\Http\Controllers\Api\Production;

use App\Http\Controllers\Controller;
use App\Http\Resources\BillOfMaterialResource;
use App\Models\BillOfMaterial;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillOfMaterialController extends Controller
{
    public function index()
    {
        // The page groups lines under one card per product, so the rows have to
        // arrive grouped — an unordered list would open a new card every time
        // the product changed and repeat products further down.
        return BillOfMaterialResource::collection(
            BillOfMaterial::with(['product', 'rawMaterial'])
                ->join('items', 'items.id', '=', 'bill_of_materials.product_id')
                ->orderBy('items.item_name')
                ->orderBy('bill_of_materials.id')
                ->select('bill_of_materials.*')
                ->get()
        );
    }

    public function formOptions()
    {
        return response()->json([
            'products' => Item::where('is_active', true)
                ->whereIn('category', ['finished_good', 'wip'])
                ->orderBy('item_name')->get(['id', 'item_code', 'item_name', 'unit_of_measure']),
            'raw_materials' => Item::where('is_active', true)
                ->orderBy('item_name')->get(['id', 'item_code', 'item_name', 'unit_of_measure']),
        ]);
    }

    public function store(Request $request)
    {
        $entry = BillOfMaterial::create($this->validated($request));

        return (new BillOfMaterialResource($entry->load(['product', 'rawMaterial'])))
            ->response()->setStatusCode(201);
    }

    public function update(Request $request, BillOfMaterial $bom)
    {
        $bom->update($this->validated($request, $bom));

        return new BillOfMaterialResource($bom->load(['product', 'rawMaterial']));
    }

    public function destroy(BillOfMaterial $bom)
    {
        $bom->delete();

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request, ?BillOfMaterial $bom = null): array
    {
        return $request->validate([
            'product_id' => [
                'required', 'exists:items,id',
                // The same raw material must not appear twice for one product,
                // or the requirement is ambiguous.
                Rule::unique('bill_of_materials', 'product_id')
                    ->where(fn ($query) => $query->where('raw_material_id', $request->input('raw_material_id')))
                    ->ignore($bom?->id),
            ],
            // A product built from itself would recurse forever.
            'raw_material_id' => ['required', 'exists:items,id', 'different:product_id'],
            'quantity_required' => 'required|numeric|min:0.01',
            'unit_of_measure' => 'required|string|max:50',
            'notes' => 'nullable|string',
        ]);
    }
}
