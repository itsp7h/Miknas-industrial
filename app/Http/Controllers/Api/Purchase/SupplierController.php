<?php

namespace App\Http\Controllers\Api\Purchase;

use App\Events\SupplierDeleted;
use App\Events\SupplierSaved;
use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\SupplierImportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    public function index()
    {
        return SupplierResource::collection(Supplier::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'supplier_code' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'secondary_email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'phone2' => 'nullable|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'website' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:255',
            'credit_terms' => 'nullable|string|max:10',
            'credit_days' => 'nullable|integer|min:0',
            'remarks' => 'nullable|string',
        ]);

        $supplier = Supplier::create(array_merge($data, [
            'is_active' => (bool) $request->input('is_active', true),
        ]));

        event(new SupplierSaved($supplier));

        return (new SupplierResource($supplier))->response()->setStatusCode(201);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'supplier_code' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'secondary_email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'phone2' => 'nullable|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'website' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:255',
            'credit_terms' => 'nullable|string|max:10',
            'credit_days' => 'nullable|integer|min:0',
            'remarks' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $supplier->update($data);

        event(new SupplierSaved($supplier));

        return new SupplierResource($supplier);
    }

    /**
     * Empty the supplier directory.
     *
     * A supplier the paperwork points at cannot go — `purchase_orders`,
     * `supplier_invoices`, `supplier_payments`, `goods_receipt_notes`,
     * `rfq_invitations` and `supplier_quotes` are all `restrict` on
     * `supplier_id`, because an order has to keep saying who it was placed
     * with. So this deletes the ones that can go and says plainly how many it
     * kept and why, rather than refusing the lot because one is in use or —
     * worse — half-emptying the table and reporting success.
     *
     * Which suppliers are in use is answered in one query per table rather
     * than `hasRelatedRecords()` per supplier, which would be six queries each.
     */
    public function destroyAll()
    {
        $inUse = $this->suppliersInUse();

        $deletable = Supplier::whereNotIn('id', $inUse)->pluck('id');
        $kept = $inUse->count();

        if ($deletable->isEmpty()) {
            return response()->json([
                'deleted' => 0,
                'kept' => $kept,
                'message' => $kept === 0
                    ? 'There are no suppliers to delete.'
                    : 'Nothing was deleted: every supplier has purchase orders, invoices or other records.',
            ]);
        }

        DB::transaction(fn () => Supplier::whereIn('id', $deletable)->delete());

        // The same per-supplier event a single delete fires, so every open
        // board drops exactly the rows that went rather than being told to
        // reload.
        foreach ($deletable as $id) {
            event(new SupplierDeleted($id));
        }

        return response()->json([
            'deleted' => $deletable->count(),
            'kept' => $kept,
            'message' => $kept === 0
                ? $deletable->count().' supplier(s) deleted.'
                : $deletable->count().' supplier(s) deleted. '.$kept
                    .' kept, because they have purchase orders, invoices or other records.',
        ]);
    }

    /** Ids named by anything that refuses to let a supplier go. */
    private function suppliersInUse(): Collection
    {
        $tables = [
            'purchase_orders', 'supplier_invoices', 'supplier_payments',
            'goods_receipt_notes', 'rfq_invitations', 'supplier_quotes',
        ];

        return collect($tables)
            ->flatMap(fn (string $table) => DB::table($table)
                ->whereNotNull('supplier_id')->distinct()->pluck('supplier_id'))
            ->unique()
            ->values();
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->hasRelatedRecords()) {
            return response()->json([
                'message' => 'Cannot delete a supplier that has purchase orders, invoices, or payments.',
            ], 422);
        }

        $id = $supplier->id;
        $supplier->delete();

        event(new SupplierDeleted($id));

        return response()->noContent();
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

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Import failed: '.$e->getMessage()], 422);
        }
    }

    public function downloadTemplate()
    {
        $path = storage_path('app/suppliers_template.xlsx');

        if (! file_exists($path)) {
            Artisan::call('suppliers:template');
        }

        return response()->download($path, 'suppliers_import_template.xlsx');
    }

    public function exportPdf()
    {
        $suppliers = Supplier::orderBy('name')->get();

        $pdf = Pdf::loadView('purchase.suppliers.pdf', compact('suppliers'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('suppliers_'.now()->format('Y-m-d').'.pdf');
    }
}
