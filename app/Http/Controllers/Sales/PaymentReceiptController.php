<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PaymentReceipt;
use App\Models\SalesInvoice;
use Illuminate\Http\Request;

class PaymentReceiptController extends Controller
{
    public function index()
    {
        $receipts = PaymentReceipt::with('salesInvoice.customer')->paginate(15);

        return view('sales.payments.index', compact('receipts'));
    }

    public function create()
    {
        $invoices = SalesInvoice::whereIn('status', ['unpaid', 'partial'])->with('customer')->get();
        $customers = Customer::all();

        return view('sales.payments.create', compact('invoices', 'customers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'sales_invoice_id' => 'required|exists:sales_invoices,id',
            'receipt_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:255',
        ]);

        $receipt = PaymentReceipt::create(array_merge($request->all(), [
            'created_by' => auth()->id(),
        ]));

        $invoice = SalesInvoice::find($request->sales_invoice_id);
        $newPaidAmount = $invoice->paid_amount + $request->amount;
        $status = $newPaidAmount >= $invoice->total_amount ? 'paid' : 'partial';

        $invoice->update([
            'paid_amount' => $newPaidAmount,
            'status' => $status,
        ]);

        // Reduce the customer's outstanding balance by the amount received
        $invoice->customer()->decrement('outstanding_balance', $request->amount);

        return redirect()->route('sales.payments.index')->with('success', 'Payment receipt recorded successfully.');
    }

    public function show(PaymentReceipt $paymentReceipt)
    {
        $paymentReceipt->load('salesInvoice.customer');

        return view('sales.payments.show', compact('paymentReceipt'));
    }

    public function edit(PaymentReceipt $paymentReceipt)
    {
        return view('sales.payments.edit', compact('paymentReceipt'));
    }

    public function update(Request $request, PaymentReceipt $paymentReceipt)
    {
        $request->validate([
            'receipt_date' => 'required|date',
            'payment_method' => 'required|string|max:255',
        ]);

        $paymentReceipt->update($request->only('receipt_date', 'payment_method', 'notes'));

        return redirect()->route('sales.payments.index')->with('success', 'Payment receipt updated successfully.');
    }

    public function destroy(PaymentReceipt $paymentReceipt)
    {
        $paymentReceipt->delete();

        return redirect()->route('sales.payments.index')->with('success', 'Payment receipt deleted successfully.');
    }
}
