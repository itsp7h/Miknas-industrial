<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceiptNote;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use Illuminate\Http\Request;

class SupplierInvoiceController extends Controller
{
    public function index()
    {
        $invoices = SupplierInvoice::with('supplier')->paginate(15);

        return view('purchase.invoices.index', compact('invoices'));
    }

    public function create()
    {
        $suppliers = Supplier::all();
        $purchaseOrders = PurchaseOrder::where('status', 'received')->with('supplier')->get();
        $grns = GoodsReceiptNote::where('status', 'confirmed')->with('purchaseOrder.supplier')->get();

        return view('purchase.invoices.create', compact('suppliers', 'purchaseOrders', 'grns'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'invoice_number' => 'required|string|max:255',
            'invoice_date' => 'required|date',
            'subtotal' => 'required|numeric|min:0',
            'vat_amount' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
        ]);

        SupplierInvoice::create(array_merge($request->all(), [
            'status' => 'unpaid',
            'paid_amount' => 0,
        ]));

        return redirect()->route('purchase.invoices.index')->with('success', 'Supplier invoice created successfully.');
    }

    public function show(SupplierInvoice $supplierInvoice)
    {
        $supplierInvoice->load(['supplier', 'purchaseOrder']);

        return view('purchase.invoices.show', compact('supplierInvoice'));
    }

    public function edit(SupplierInvoice $supplierInvoice)
    {
        $suppliers = Supplier::all();

        return view('purchase.invoices.edit', compact('supplierInvoice', 'suppliers'));
    }

    public function update(Request $request, SupplierInvoice $supplierInvoice)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'invoice_number' => 'required|string|max:255',
            'invoice_date' => 'required|date',
            'subtotal' => 'required|numeric|min:0',
            'vat_amount' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
        ]);

        $supplierInvoice->update($request->all());

        return redirect()->route('purchase.invoices.index')->with('success', 'Supplier invoice updated successfully.');
    }

    public function destroy(SupplierInvoice $supplierInvoice)
    {
        $supplierInvoice->delete();

        return redirect()->route('purchase.invoices.index')->with('success', 'Supplier invoice deleted successfully.');
    }
}
