<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Events\ItemDeleted;
use App\Events\ItemSaved;
use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\ItemImportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        return ItemResource::collection(
            Item::with(['stockLevels.warehouse', 'itemCategory'])->orderBy('item_name')->get()
        )
            ->additional(['meta' => [
                'categories' => self::CATEGORIES,
                'category_options' => self::categoryOptions(),
                'warehouses' => Warehouse::where('is_active', true)->orderBy('name')
                    ->get(['id', 'name'])->all(),
            ]]);
    }

    /**
     * Raw Materials and Finished Goods are two tabs over one table, so the
     * route can only ask for "either". Which one this item belongs to decides
     * which permission actually applies — otherwise someone granted Raw
     * Materials could edit the company's own product.
     */
    private function authorizeTab(string $category, string $action): void
    {
        $tab = $category === 'finished_good' ? 'finished-goods' : 'raw-materials';

        abort_unless(auth()->user()?->can("{$tab}.{$action}"), 403);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->authorizeTab($data['category'], 'create');

        // Codes are generated, never supplied by the client.
        $data['item_code'] = $this->nextItemCode();

        $item = DB::transaction(function () use ($data, $request) {
            $item = Item::create($data);
            $this->assignWarehouse($item, $request, creating: true);

            return $item;
        });

        event(new ItemSaved($item));

        return (new ItemResource($item))->response()->setStatusCode(201);
    }

    public function update(Request $request, Item $item)
    {
        // Both sides: you may not move an item out of a tab you cannot write,
        // nor into one you cannot.
        $this->authorizeTab($item->category, 'edit');
        $data = $this->validated($request);
        $this->authorizeTab($data['category'], 'edit');

        DB::transaction(function () use ($item, $request, $data) {
            $item->update($data);
            $this->assignWarehouse($item, $request, creating: false);
        });

        $item = $item->fresh();

        event(new ItemSaved($item));

        return new ItemResource($item);
    }

    public function destroy(Item $item)
    {
        $this->authorizeTab($item->category, 'delete');

        // An item anything else points at is deactivated rather than deleted,
        // so every document that names it keeps resolving.
        //
        // This used to ask about stock movements alone, which let an item held
        // only by, say, a purchase order reach the delete — where the RESTRICT
        // foreign key refused it and the client got a 500 with a raw
        // QueryException instead of a reason. `blockingReferences()` asks about
        // all of them.
        $used = $item->blockingReferences();

        if ($used !== []) {
            $item->update(['is_active' => false]);
            event(new ItemSaved($item));

            return response()->json([
                'message' => 'Item is used by '.Arr::join($used, ', ', ' and ')
                    .', so it was deactivated rather than deleted.',
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
    /**
     * Put the item in the warehouse the form chose.
     *
     * Stock levels were only ever created by a stock movement, which demands a
     * quantity of at least 0.01 — so an item could not be said to live anywhere
     * until stock had been moved into it. That is backwards for a warehouse
     * whose inventory sheet lists what it carries before any of it arrives, so
     * the assignment creates the level itself, at zero.
     *
     * An opening quantity is offered only when creating, and goes in through a
     * real stock movement: the ledger has to explain every figure the reports
     * show, and a level conjured with a balance would explain nothing.
     */
    private function assignWarehouse(Item $item, Request $request, bool $creating): void
    {
        $warehouseId = $request->input('warehouse_id');

        if (! $warehouseId) {
            return;
        }

        $existing = $item->stockLevels()->get();

        // Changing where an item lives must not move physical stock behind the
        // storekeeper's back; that is what a transfer movement is for.
        $held = $existing->firstWhere(fn ($level) => (float) $level->quantity != 0.0
            && (int) $level->warehouse_id !== (int) $warehouseId);

        if ($held) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'This item still holds stock in '
                    .($held->warehouse?->name ?? 'another warehouse')
                    .'. Record a stock movement to move it.',
            ]);
        }

        // An empty level elsewhere is just a stale assignment; drop it so the
        // item reads as being in one place.
        $existing->where('warehouse_id', '!=', $warehouseId)->each->delete();

        $level = StockLevel::firstOrCreate(
            ['item_id' => $item->id, 'warehouse_id' => $warehouseId],
            ['quantity' => 0]
        );

        $opening = (float) $request->input('opening_quantity', 0);

        if ($creating && $opening > 0) {
            StockMovement::create([
                'item_id' => $item->id,
                'warehouse_id' => $warehouseId,
                'type' => 'in',
                'quantity' => $opening,
                'reference_type' => 'opening_stock',
                'notes' => 'Opening stock',
                'created_by' => $request->user()?->id,
            ]);

            $level->increment('quantity', $opening);
        }
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'item_name' => 'required|string|max:255',
            'category' => 'required|in:'.implode(',', self::CATEGORIES),
            'item_category_id' => 'nullable|exists:item_categories,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'opening_quantity' => 'nullable|numeric|min:0',
            'unit_of_measure' => 'required|string|max:50',
            'minimum_stock_level' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        // A section belongs to one type, so a mismatched pair — "Finished
        // Goods" with a raw-material section — is rejected rather than stored
        // and rendered as a path that contradicts itself.
        if (! empty($data['item_category_id'])) {
            $section = ItemCategory::find($data['item_category_id']);

            if ($section && $section->parent_type !== $data['category']) {
                throw ValidationException::withMessages([
                    'item_category_id' => 'That section belongs to '.$section->parentLabel().'.',
                ]);
            }
        }

        // Neither belongs to the items table; they place the item in a
        // warehouse, which is a stock fact.
        unset($data['warehouse_id'], $data['opening_quantity']);

        // Both are NOT NULL with a 0 default in the schema; an omitted field
        // must become 0 rather than a null that fails the insert.
        $data['minimum_stock_level'] = $data['minimum_stock_level'] ?? 0;
        $data['cost_price'] = $data['cost_price'] ?? 0;
        $data['is_active'] = (bool) $request->input('is_active', true);

        return $data;
    }

    /**
     * Every choice the one category dropdown offers, as a flat list.
     *
     * The form shows a single control reading "Raw Materials / Chemical
     * Materials" but posts the two columns behind it, so each option carries
     * both. A bare type is offered too: not everything is in a section, and
     * finished goods are in none.
     */
    public static function categoryOptions(): array
    {
        $options = [];

        foreach (self::CATEGORIES as $type) {
            $label = ItemCategory::PARENT_LABELS[$type] ?? $type;

            $options[] = [
                'value' => $type.':',
                'label' => $label,
                'category' => $type,
                'item_category_id' => null,
            ];

            foreach (ItemCategory::where('parent_type', $type)->ordered()->get() as $section) {
                $options[] = [
                    'value' => $type.':'.$section->id,
                    'label' => $section->path(),
                    'category' => $type,
                    'item_category_id' => $section->id,
                ];
            }
        }

        return $options;
    }

    private function nextItemCode(): string
    {
        return 'ITEM-'.str_pad((string) (Item::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}
