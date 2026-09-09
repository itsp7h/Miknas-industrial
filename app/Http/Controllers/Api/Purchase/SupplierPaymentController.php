<?php

namespace App\Http\Controllers\Api\Purchase;

use App\Events\SupplierInvoiceSaved;
use App\Events\SupplierPaymentDeleted;
use App\Events\SupplierPaymentRecorded;
use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierPaymentResource;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPaymentController extends Controller
{
    /** Matches the supplier_payments.payment_method enum. */
    public const METHODS = ['cash', 'bank_transfer', 'cheque', 'other'];

    public function index()
    {
        return SupplierPaymentResource::collection(
            SupplierPayment::with('supplierInvoice.supplier')->latest()->get()
        );
    }

    public function show(SupplierPayment $supplierPayment)
    {
        return new SupplierPaymentResource(
            $supplierPayment->load(['supplierInvoice.supplier', 'createdBy'])
        );
    }

    public function formOptions()
    {
        // Only invoices with something still owing, as the Blade create page did.
        $invoices = SupplierInvoice::whereIn('status', ['unpaid', 'partial'])
            ->with('supplier')->orderByDesc('id')->get();

        return response()->json([
            'invoices' => $invoices->map(fn ($invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'supplier_name' => $invoice->supplier?->name,
                'total_amount' => $invoice->total_amount,
                'paid_amount' => $invoice->paid_amount,
                'outstanding' => (string) number_format(
                    (float) $invoice->total_amount - (float) $invoice->paid_amount, 2, '.', ''
                ),
            ])->values(),
            'methods' => self::METHODS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_invoice_id' => 'required|exists:supplier_invoices,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:'.implode(',', self::METHODS),
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $invoice = SupplierInvoice::findOrFail($data['supplier_invoice_id']);
        $this->assertNotOverpaying($invoice, (float) $data['amount']);

        $payment = DB::transaction(function () use ($data, $invoice) {
            $payment = SupplierPayment::create($data + [
                // The Blade controller never set this. supplier_id is NOT NULL, so
                // every attempt to record a payment failed on the constraint — the
                // whole flow was unreachable. It belongs to the invoice.
                'supplier_id' => $invoice->supplier_id,
                'created_by' => auth()->id(),
            ]);

            $this->syncInvoice($invoice);

            return $payment;
        });

        event(new SupplierPaymentRecorded($payment));
        event(new SupplierInvoiceSaved($invoice->fresh()));

        return (new SupplierPaymentResource($payment->load('supplierInvoice.supplier')))
            ->response()->setStatusCode(201);
    }

    public function update(Request $request, SupplierPayment $supplierPayment)
    {
        $data = $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:'.implode(',', self::METHODS),
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $invoice = $supplierPayment->supplierInvoice;
        // Exclude this payment's current amount, or editing it would look like an
        // overpayment against its own contribution.
        $this->assertNotOverpaying($invoice, (float) $data['amount'], $supplierPayment);

        DB::transaction(function () use ($supplierPayment, $data, $invoice) {
            $supplierPayment->update($data);

            // The Blade controller updated the payment and stopped there, so
            // changing an amount left the invoice's paid figure stale.
            $this->syncInvoice($invoice);
        });

        event(new SupplierPaymentRecorded($supplierPayment));
        event(new SupplierInvoiceSaved($invoice->fresh()));

        return new SupplierPaymentResource($supplierPayment->load('supplierInvoice.supplier'));
    }

    public function destroy(SupplierPayment $supplierPayment)
    {
        $id = $supplierPayment->id;
        $invoice = $supplierPayment->supplierInvoice;

        DB::transaction(function () use ($supplierPayment, $invoice) {
            $supplierPayment->delete();

            // Likewise: deleting a payment used to leave the invoice still
            // showing it as paid.
            $this->syncInvoice($invoice);
        });

        event(new SupplierPaymentDeleted($id));
        event(new SupplierInvoiceSaved($invoice->fresh()));

        return response()->json(['deleted' => true]);
    }

    /**
     * Recomputes the invoice from the sum of its payments rather than adding to
     * whatever paid_amount happened to hold. That is the only way edits and
     * deletions can stay consistent, and it self-heals rows the Blade flow left
     * drifted.
     */
    private function syncInvoice(SupplierInvoice $invoice): void
    {
        $paid = (float) $invoice->payments()->sum('amount');
        $total = (float) $invoice->total_amount;

        $status = 'unpaid';
        if ($paid > 0) {
            $status = $paid >= $total ? 'paid' : 'partial';
        }

        $invoice->update(['paid_amount' => $paid, 'status' => $status]);
    }

    private function assertNotOverpaying(SupplierInvoice $invoice, float $amount, ?SupplierPayment $excluding = null): void
    {
        $alreadyPaid = (float) $invoice->payments()
            ->when($excluding, fn ($q) => $q->whereKeyNot($excluding->getKey()))
            ->sum('amount');

        $outstanding = round((float) $invoice->total_amount - $alreadyPaid, 2);

        if (round($amount, 2) > $outstanding) {
            throw ValidationException::withMessages([
                'amount' => 'That exceeds the outstanding balance of '.number_format($outstanding, 2).'.',
            ]);
        }
    }
}
