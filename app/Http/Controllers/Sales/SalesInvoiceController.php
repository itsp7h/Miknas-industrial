<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Notifications\Sales\InvoiceCreatedNotification;
use Illuminate\Http\Request;

class SalesInvoiceController extends Controller
{
    public function index()
    {
        $invoices = SalesInvoice::with('customer')->paginate(15);

        return view('sales.invoices.index', compact('invoices'));
    }

    public function create()
    {
        $salesOrders = SalesOrder::where('status', 'dispatched')->with('customer')->get();
        $customers = Customer::all();

        return view('sales.invoices.create', compact('salesOrders', 'customers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'sales_order_id' => 'required|exists:sales_orders,id',
            'customer_id' => 'required|exists:customers,id',
            'invoice_date' => 'required|date',
            'subtotal' => 'required|numeric|min:0',
            'vat_rate' => 'required|numeric|min:0',
            'vat_amount' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
        ]);

        $invoiceNumber = 'INV-'.str_pad(SalesInvoice::max('id') + 1, 5, '0', STR_PAD_LEFT);

        $invoice = SalesInvoice::create(array_merge($request->all(), [
            'invoice_number' => $invoiceNumber,
            'status' => 'unpaid',
            'paid_amount' => 0,
            'created_by' => auth()->id(),
        ]));

        SalesOrder::where('id', $request->sales_order_id)->update(['status' => 'invoiced']);

        if ($invoice->customer && $invoice->customer->whatsapp_number) {
            $invoice->customer->notify(new InvoiceCreatedNotification($invoice));
        }

        return redirect()->route('sales.invoices.index')->with('success', 'Invoice created successfully.');
    }

    public function show(SalesInvoice $salesInvoice)
    {
        $salesInvoice->load(['customer', 'salesOrder', 'paymentReceipts']);

        return view('sales.invoices.show', compact('salesInvoice'));
    }

    public function edit(SalesInvoice $salesInvoice)
    {
        $customers = Customer::all();

        return view('sales.invoices.edit', compact('salesInvoice', 'customers'));
    }

    public function update(Request $request, SalesInvoice $salesInvoice)
    {
        $request->validate([
            'invoice_date' => 'required|date',
            'subtotal' => 'required|numeric|min:0',
            'vat_rate' => 'required|numeric|min:0',
            'vat_amount' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
        ]);

        $salesInvoice->update($request->all());

        return redirect()->route('sales.invoices.show', $salesInvoice)->with('success', 'Invoice updated successfully.');
    }

    public function destroy(SalesInvoice $salesInvoice)
    {
        $salesInvoice->delete();

        return redirect()->route('sales.invoices.index')->with('success', 'Invoice deleted successfully.');
    }
}
