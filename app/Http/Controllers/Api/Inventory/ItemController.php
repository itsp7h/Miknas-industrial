<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Events\ItemDeleted;
use App\Events\ItemSaved;
use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Services\ItemImportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ItemController extends Controller
{
    /** Mirrors the enum check constraint on items.category. */
    public const CATEGORIES = ['raw_material', 'wip', 'finished_good'];

    /**
     * The whole list, unpaginated: search is client-side over every item
     * (CLAUDE.md gotcha #6), so the page needs all of them.
     */
    public function index()
    {
        return ItemResource::collection(Item::orderBy('item_name')->get())
            ->additional(['meta' => ['categories' => self::CATEGORIES]]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        // Codes are generated, never supplied by the client.
        $data['item_code'] = $this->nextItemCode();

        $item = Item::create($data);

        event(new ItemSaved($item));

        return (new ItemResource($item))->response()->setStatusCode(201);
    }

    public function update(Request $request, Item $item)
    {
        $item->update($this->validated($request));

        event(new ItemSaved($item));

        return new ItemResource($item);
    }

    public function destroy(Item $item)
    {
        // Stock history must not be orphaned — an item that has ever moved is
        // deactivated instead of deleted, so reports keep resolving its name.
        if ($item->stockMovements()->exists()) {
            $item->update(['is_active' => false]);
            event(new ItemSaved($item));

            return response()->json([
                'message' => 'Item has stock movements, so it was deactivated rather than deleted.',
                'deactivated' => true,
            ]);
        }

        $id = $item->id;
        $item->delete();

        event(new ItemDeleted($id));

        return response()->json(['deleted' => true]);
    }

    public function import(Request $request, ItemImportService $service)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls']);

        $result = $service->import($request->file('file')->getRealPath());

        return response()->json($result);
    }

    public function downloadTemplate()
    {
        $path = storage_path('app/items_template.xlsx');
        Artisan::call('items:template', ['--output' => $path]);

        return response()->download($path);
    }

    public function exportPdf()
    {
        $items = Item::orderBy('item_name')->get();

        return Pdf::loadView('inventory.items.pdf', compact('items'))->download('items.pdf');
    }

    /**
     * Field names match the items table. The Blade controller this replaces
     * validated `name`/`type`/`selling_price`, none of which exist on the
     * model or were ever posted by its own form — so every create failed
     * validation and no item was created.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'item_name' => 'required|string|max:255',
            'category' => 'required|in:'.implode(',', self::CATEGORIES),
            'unit_of_measure' => 'required|string|max:50',
            'minimum_stock_level' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        // Both are NOT NULL with a 0 default in the schema; an omitted field
        // must become 0 rather than a null that fails the insert.
        $data['minimum_stock_level'] = $data['minimum_stock_level'] ?? 0;
        $data['cost_price'] = $data['cost_price'] ?? 0;
        $data['is_active'] = (bool) $request->input('is_active', true);

        return $data;
    }

    private function nextItemCode(): string
    {
        return 'ITEM-'.str_pad((string) (Item::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}
