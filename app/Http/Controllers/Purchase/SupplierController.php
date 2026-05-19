<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\SupplierImportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::orderBy('name')->get();

        $stats = [
            'total'      => $suppliers->count(),
            'active'     => $suppliers->where('is_active', true)->count(),
            'inactive'   => $suppliers->where('is_active', false)->count(),
            'with_email' => $suppliers->filter(fn($s) => !empty($s->email))->count(),
            'categories' => $suppliers->whereNotNull('category')->groupBy('category')->count(),
        ];

        $categories = Supplier::whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('purchase.suppliers.index', compact('suppliers', 'stats', 'categories'));
    }

    public function create()
    {
        return view('purchase.suppliers.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'             => 'required|string|max:255',
            'credit_days'      => 'nullable|integer|min:0',
            'whatsapp_number'  => ['nullable', 'string', 'max:20'],
        ]);

        Supplier::create(array_merge(
            $request->only([
                'supplier_code', 'name', 'category', 'contact_person',
                'email', 'secondary_email', 'phone', 'phone2', 'whatsapp', 'whatsapp_number',
                'address', 'website', 'tax_number', 'credit_terms', 'credit_days', 'remarks',
            ]),
            ['is_active' => (bool) $request->input('is_active', 1)]
        ));

        return redirect()->route('purchase.suppliers.index')->with('success', 'Supplier created successfully.');
    }

    public function show(Supplier $supplier)
    {
        return view('purchase.suppliers.show', compact('supplier'));
    }

    public function edit(Supplier $supplier)
    {
        return view('purchase.suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $request->validate([
            'name'             => 'required|string|max:255',
            'credit_days'      => 'nullable|integer|min:0',
            'is_active'        => 'nullable',
            'whatsapp_number'  => ['nullable', 'string', 'max:20'],
        ]);

        $supplier->update(array_merge(
            $request->only([
                'supplier_code', 'name', 'category', 'contact_person',
                'email', 'secondary_email', 'phone', 'phone2', 'whatsapp', 'whatsapp_number',
                'address', 'website', 'tax_number', 'credit_terms', 'credit_days', 'remarks',
            ]),
            ['is_active' => (bool) $request->input('is_active', 0)]
        ));

        return redirect()->route('purchase.suppliers.index')->with('success', 'Supplier updated successfully.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return redirect()->route('purchase.suppliers.index')->with('success', 'Supplier deleted successfully.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        try {
            $result = app(SupplierImportService::class)->import(
                $request->file('file')->getPathname()
            );

            $msg = "Import complete: {$result['imported']} added, {$result['updated']} updated"
                 . ($result['skipped'] > 0 ? ", {$result['skipped']} skipped." : '.');

            return redirect()->route('purchase.suppliers.index')->with('success', $msg);
        } catch (\Exception $e) {
            return redirect()->route('purchase.suppliers.index')
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        $path = storage_path('app/suppliers_template.xlsx');

        if (!file_exists($path)) {
            Artisan::call('suppliers:template');
        }

        return response()->download($path, 'suppliers_import_template.xlsx');
    }

    public function exportPdf()
    {
        $suppliers = Supplier::orderBy('name')->get();

        $pdf = Pdf::loadView('purchase.suppliers.pdf', compact('suppliers'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('suppliers_' . now()->format('Y-m-d') . '.pdf');
    }
}
