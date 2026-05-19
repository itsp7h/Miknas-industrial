<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\BillOfMaterial;
use App\Models\Item;
use Illuminate\Http\Request;

class BillOfMaterialController extends Controller
{
    public function index()
    {
        $boms = BillOfMaterial::with(['product', 'rawMaterial'])
            ->get()
            ->groupBy('product_id');

        return view('production.bom.index', compact('boms'));
    }

    public function create()
    {
        $finishedGoods = Item::where('category', 'finished_good')->get();
        $rawMaterials  = Item::where('category', 'raw_material')->get();

        return view('production.bom.create', compact('finishedGoods', 'rawMaterials'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id'        => 'required|exists:items,id',
            'raw_material_id'   => 'required|exists:items,id',
            'quantity_required' => 'required|numeric|min:0.01',
            'unit_of_measure'   => 'required|string|max:50',
        ]);

        BillOfMaterial::create($request->all());

        return redirect()->route('production.bom.index')->with('success', 'BOM entry created successfully.');
    }

    public function show(BillOfMaterial $billOfMaterial)
    {
        $billOfMaterial->load(['product', 'rawMaterial']);

        return view('production.bom.show', compact('billOfMaterial'));
    }

    public function edit(BillOfMaterial $billOfMaterial)
    {
        $finishedGoods = Item::where('category', 'finished_good')->get();
        $rawMaterials  = Item::where('category', 'raw_material')->get();

        return view('production.bom.edit', compact('billOfMaterial', 'finishedGoods', 'rawMaterials'));
    }

    public function update(Request $request, BillOfMaterial $billOfMaterial)
    {
        $request->validate([
            'product_id'        => 'required|exists:items,id',
            'raw_material_id'   => 'required|exists:items,id',
            'quantity_required' => 'required|numeric|min:0.01',
            'unit_of_measure'   => 'required|string|max:50',
        ]);

        $billOfMaterial->update($request->all());

        return redirect()->route('production.bom.index')->with('success', 'BOM entry updated successfully.');
    }

    public function destroy(BillOfMaterial $billOfMaterial)
    {
        $billOfMaterial->delete();

        return redirect()->route('production.bom.index')->with('success', 'BOM entry deleted successfully.');
    }
}
