<?php

namespace App\Http\Controllers\Api\Sales;

use App\Events\SalesInvoiceSaved;
use App\Http\Controllers\Controller;
use App\Http\Resources\SalesInvoiceResource;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Notifications\Sales\InvoiceCreatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesInvoiceController extends Controller
{
    public function index()
    {
        return SalesInvoiceResource::collection(
            SalesInvoice::with(['salesOrder', 'customer'])->latest()->get()
        );
    }

    public function show(SalesInvoice $salesInvoice)
    {
        return new SalesInvoiceResource($salesInvoice->load(['salesOrder', 'customer']));
    }

    public function formOptions()
    {
        // An order can only be invoiced once, so already-invoiced orders drop
        // out of the picker rather than allowing a duplicate invoice.
        $invoicedOrderIds = SalesInvoice::pluck('sales_order_id');

        $orders = SalesOrder::with('customer')
            ->whereIn('status', ['confirmed', 'dispatched'])
            ->whereNotIn('id', $invoicedOrderIds)
            ->latest()
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_id' => $order->customer_id,
                'customer_name' => $order->customer?->name,
                'total_amount' => $order->total_amount,
            ]);

        return response()->json([
            'orders' => $orders,
            'vat_rate' => (float) Setting::get('vat_rate', '0'),
        ]);
    }

    /**
     * Amounts are derived from the order and the configured VAT rate rather
     * than accepted from the client. The Blade controller took subtotal, VAT
     * and total straight off the request, so a crafted form could invoice any
     * figure it liked.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'sales_order_id' => 'required|exists:sales_orders,id',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'notes' => 'nullable|string',
        ]);

        $order = SalesOrder::findOrFail($data['sales_order_id']);

        abort_if(
            SalesInvoice::where('sales_order_id', $order->id)->exists(),
            422,
            'That sales order has already been invoiced.'
        );

        $subtotal = (float) $order->total_amount;
        $vatRate = (float) Setting::get('vat_rate', '0');
        $vatAmount = round($subtotal * $vatRate / 100, 2);

        $invoice = DB::transaction(function () use ($data, $order, $subtotal, $vatRate, $vatAmount) {
            $invoice = SalesInvoice::create([
                'invoice_number' => $this->nextInvoiceNumber(),
                'sales_order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'subtotal' => $subtotal,
                'vat_rate' => $vatRate,
                'vat_amount' => $vatAmount,
                'total_amount' => round($subtotal + $vatAmount, 2),
                'paid_amount' => 0,
                'status' => 'unpaid',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $order->update(['status' => 'invoiced']);

            // Invoicing puts the money on the customer's account.
            $order->customer?->increment('outstanding_balance', $invoice->total_amount);

            return $invoice;
        });

        event(new SalesInvoiceSaved($invoice));

        $customer = $invoice->loadMissing('customer')->customer;

        if ($customer && $customer->whatsapp_number) {
            $customer->notify(new InvoiceCreatedNotification($invoice));
        }

        return (new SalesInvoiceResource($invoice->load(['salesOrder', 'customer'])))
            ->response()->setStatusCode(201);
    }

    private function nextInvoiceNumber(): string
    {
        return 'INV-'.str_pad((string) (SalesInvoice::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}
