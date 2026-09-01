<?php

namespace App\Http\Controllers\Api\Sales;

use App\Events\SalesInvoiceDeleted;
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

    /**
     * Blade's edit page let the subtotal, VAT, total *and status* be typed in.
     * Status is derived from receipts, so hand-setting it made an invoice read
     * "paid" with no money behind it — the same bug already fixed on the
     * purchase side. It is not editable here.
     *
     * The amounts are editable, but only while nothing has been received
     * against the invoice: the customer's outstanding balance was raised by the
     * original total, so a later change has to move that balance by the same
     * delta. Blade changed the total and left the balance alone, quietly
     * desyncing the customer's account.
     */
    public function update(Request $request, SalesInvoice $salesInvoice)
    {
        $data = $request->validate([
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'subtotal' => 'nullable|numeric|min:0',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        $amountsChanged = array_key_exists('subtotal', $data) || array_key_exists('vat_rate', $data);

        if ($amountsChanged && (float) $salesInvoice->paid_amount > 0) {
            abort(422, 'Money has been received against this invoice, so its amounts can no longer be changed.');
        }

        DB::transaction(function () use ($salesInvoice, $data, $amountsChanged) {
            $update = [
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];

            if ($amountsChanged) {
                $subtotal = (float) ($data['subtotal'] ?? $salesInvoice->subtotal);
                $vatRate = (float) ($data['vat_rate'] ?? $salesInvoice->vat_rate);
                $vatAmount = round($subtotal * $vatRate / 100, 2);
                $total = round($subtotal + $vatAmount, 2);

                $update += [
                    'subtotal' => $subtotal,
                    'vat_rate' => $vatRate,
                    'vat_amount' => $vatAmount,
                    'total_amount' => $total,
                ];

                $delta = round($total - (float) $salesInvoice->total_amount, 2);

                if ($delta !== 0.0) {
                    $salesInvoice->customer?->increment('outstanding_balance', $delta);
                }
            }

            $salesInvoice->update($update);
        });

        event(new SalesInvoiceSaved($salesInvoice->fresh()));

        return new SalesInvoiceResource($salesInvoice->load(['salesOrder', 'customer']));
    }

    /**
     * Deleting an invoice has to undo what raising it did: the customer's
     * balance and the order's invoiced status. Blade deleted the row and left
     * both behind.
     */
    public function destroy(SalesInvoice $salesInvoice)
    {
        abort_if(
            (float) $salesInvoice->paid_amount > 0,
            422,
            'Money has been received against this invoice, so it cannot be deleted.'
        );

        $id = $salesInvoice->id;

        DB::transaction(function () use ($salesInvoice) {
            $salesInvoice->customer?->decrement('outstanding_balance', $salesInvoice->total_amount);

            $order = $salesInvoice->salesOrder;

            if ($order) {
                $order->loadMissing('items');
                $fullyDelivered = $order->items->isNotEmpty()
                    && $order->items->every(fn ($line) => $line->quantity_delivered >= $line->quantity);

                $order->update(['status' => $fullyDelivered ? 'dispatched' : 'confirmed']);
            }

            $salesInvoice->delete();
        });

        event(new SalesInvoiceDeleted($id));

        return response()->json(['deleted' => true]);
    }

    private function nextInvoiceNumber(): string
    {
        return 'INV-'.str_pad((string) (SalesInvoice::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}
