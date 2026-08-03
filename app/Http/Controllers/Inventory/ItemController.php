<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\ItemImportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ItemController extends Controller
{
    public function index()
    {
        $mobileView = 'mobile.inventory.items.index';

        if (isMobileViewport() && view()->exists($mobileView)) {
            // Mobile's search bar filters client-side over every item (CLAUDE.md
            // gotcha #6), so it needs the full list rather than one page of it.
            $items = Item::orderBy('item_name')->get();

            return view($mobileView, compact('items'));
        }

        $items = Item::paginate(20);

        return view('inventory.items.index', compact('items'));
    }

    public function create()
    {
        return view('inventory.items.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                 => 'required|string|max:255',
            'type'                 => 'required|string|max:100',
            'unit_of_measure'      => 'required|string|max:50',
            'cost_price'           => 'nullable|numeric|min:0',
            'selling_price'        => 'nullable|numeric|min:0',
            'minimum_stock_level'  => 'nullable|numeric|min:0',
        ]);

        $data              = $request->all();
        $data['item_code'] = 'ITEM-' . str_pad(Item::max('id') + 1, 5, '0', STR_PAD_LEFT);

        Item::create($data);

        return redirect()->route('inventory.items.index')->with('success', 'Item created successfully.');
    }

    public function show(Item $item)
    {
        return view('inventory.items.show', compact('item'));
    }

    public function edit(Item $item)
    {
        return view('inventory.items.edit', compact('item'));
    }

    public function update(Request $request, Item $item)
    {
        $request->validate([
            'name'                => 'required|string|max:255',
            'type'                => 'required|string|max:100',
            'unit_of_measure'     => 'required|string|max:50',
            'cost_price'          => 'nullable|numeric|min:0',
            'selling_price'       => 'nullable|numeric|min:0',
            'minimum_stock_level' => 'nullable|numeric|min:0',
        ]);

        $item->update($request->all());

        return redirect()->route('inventory.items.index')->with('success', 'Item updated successfully.');
    }

    public function destroy(Item $item)
    {
        $item->delete();

        return redirect()->route('inventory.items.index')->with('success', 'Item deleted successfully.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        try {
            $result = app(ItemImportService::class)->import(
                $request->file('file')->getPathname()
            );

            $msg = "Import complete: {$result['imported']} item(s) added";
            if ($result['skipped'] > 0) {
                $msg .= ", {$result['skipped']} skipped (already exist).";
            } else {
                $msg .= '.';
            }

            return redirect()->route('inventory.items.index')->with('success', $msg);
        } catch (\Exception $e) {
            return redirect()->route('inventory.items.index')
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        $path = storage_path('app/items_template.xlsx');

        if (!file_exists($path)) {
            Artisan::call('items:template');
        }

        return response()->download($path, 'items_import_template.xlsx');
    }

    public function exportPdf()
    {
        $items = Item::orderBy('category')->orderBy('item_name')->get();

        $pdf = Pdf::loadView('inventory.items.pdf', compact('items'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('items_' . now()->format('Y-m-d') . '.pdf');
    }
}
