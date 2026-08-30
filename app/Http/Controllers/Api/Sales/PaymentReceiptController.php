<?php

namespace App\Http\Controllers\Api\Sales;

use App\Events\PaymentReceiptRecorded;
use App\Events\SalesInvoiceSaved;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentReceiptResource;
use App\Models\PaymentReceipt;
use App\Models\SalesInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentReceiptController extends Controller
{
    /** Mirrors the enum check constraint on payment_receipts.payment_method. */
    public const METHODS = ['cash', 'bank_transfer', 'cheque', 'other'];

    public function index()
    {
        return PaymentReceiptResource::collection(
            PaymentReceipt::with(['salesInvoice', 'customer'])->latest()->get()
        );
    }

    public function formOptions()
    {
        $invoices = SalesInvoice::with('customer')
            ->whereIn('status', ['unpaid', 'partial'])
            ->latest()
            ->get()
            ->map(fn ($invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => $invoice->customer_id,
                'customer_name' => $invoice->customer?->name,
                'total_amount' => $invoice->total_amount,
                'paid_amount' => $invoice->paid_amount,
                'balance_due' => round(((float) $invoice->total_amount) - ((float) $invoice->paid_amount), 2),
            ]);

        return response()->json([
            'invoices' => $invoices,
            'payment_methods' => self::METHODS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sales_invoice_id' => 'required|exists:sales_invoices,id',
            'receipt_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            // An enum in the schema. The Blade controller validated this as a
            // free string, so anything outside the four values reached the
            // database and failed a CHECK constraint as a 500.
            'payment_method' => 'required|in:'.implode(',', self::METHODS),
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $invoice = SalesInvoice::findOrFail($data['sales_invoice_id']);
        $balanceDue = round(((float) $invoice->total_amount) - ((float) $invoice->paid_amount), 2);

        if ((float) $data['amount'] > $balanceDue) {
            // Overpaying would push the invoice past 'paid' and drive the
            // customer's outstanding balance negative.
            throw ValidationException::withMessages([
                'amount' => ["Only {$balanceDue} is outstanding on this invoice."],
            ]);
        }

        // Receipt, invoice and customer balance move together or not at all.
        $receipt = DB::transaction(function () use ($data, $invoice) {
            $receipt = PaymentReceipt::create([
                'sales_invoice_id' => $invoice->id,
                // NOT NULL, and a property of the invoice rather than a field
                // to re-enter.
                'customer_id' => $invoice->customer_id,
                'receipt_date' => $data['receipt_date'],
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $paid = round(((float) $invoice->paid_amount) + ((float) $data['amount']), 2);

            $invoice->update([
                'paid_amount' => $paid,
                'status' => $paid >= (float) $invoice->total_amount ? 'paid' : 'partial',
            ]);

            $invoice->customer?->decrement('outstanding_balance', $data['amount']);

            return $receipt;
        });

        event(new PaymentReceiptRecorded($receipt));
        event(new SalesInvoiceSaved($invoice->fresh()));

        return (new PaymentReceiptResource($receipt->load(['salesInvoice', 'customer'])))
            ->response()->setStatusCode(201);
    }
}
