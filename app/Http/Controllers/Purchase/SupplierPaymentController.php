<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;

class SupplierPaymentController extends Controller
{
    public function index()
    {
        $payments = SupplierPayment::with('supplierInvoice.supplier')->paginate(15);

        return view('purchase.payments.index', compact('payments'));
    }

    public function create()
    {
        $invoices = SupplierInvoice::whereIn('status', ['unpaid', 'partial'])->with('supplier')->get();

        return view('purchase.payments.create', compact('invoices'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_invoice_id' => 'required|exists:supplier_invoices,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:255',
        ]);

        $payment = SupplierPayment::create(array_merge($request->all(), [
            'created_by' => auth()->id(),
        ]));

        // Refresh invoice and recompute paid status
        $invoice = SupplierInvoice::find($request->supplier_invoice_id);
        $newPaidAmount = $invoice->paid_amount + $request->amount;
        $status = $newPaidAmount >= $invoice->total_amount ? 'paid' : 'partial';

        $invoice->update([
            'paid_amount' => $newPaidAmount,
            'status' => $status,
        ]);

        return redirect()->route('purchase.payments.index')->with('success', 'Payment recorded successfully.');
    }

    public function show(SupplierPayment $supplierPayment)
    {
        $supplierPayment->load('supplierInvoice.supplier');

        return view('purchase.payments.show', compact('supplierPayment'));
    }

    public function edit(SupplierPayment $supplierPayment)
    {
        $invoices = SupplierInvoice::whereIn('status', ['unpaid', 'partial'])->with('supplier')->get();

        return view('purchase.payments.edit', compact('supplierPayment', 'invoices'));
    }

    public function update(Request $request, SupplierPayment $supplierPayment)
    {
        $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:255',
        ]);

        $supplierPayment->update($request->all());

        return redirect()->route('purchase.payments.index')->with('success', 'Payment updated successfully.');
    }

    public function destroy(SupplierPayment $supplierPayment)
    {
        $supplierPayment->delete();

        return redirect()->route('purchase.payments.index')->with('success', 'Payment deleted successfully.');
    }
}
