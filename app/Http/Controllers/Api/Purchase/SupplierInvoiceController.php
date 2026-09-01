<?php

namespace App\Http\Controllers\Api\Purchase;

use App\Events\SupplierInvoiceDeleted;
use App\Events\SupplierInvoiceSaved;
use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierInvoiceResource;
use App\Models\GoodsReceiptNote;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use Illuminate\Http\Request;

class SupplierInvoiceController extends Controller
{
    public const STATUSES = ['unpaid', 'partial', 'paid'];

    public function index()
    {
        return SupplierInvoiceResource::collection(
            SupplierInvoice::with(['supplier', 'purchaseOrder'])->latest()->get()
        );
    }

    public function show(SupplierInvoice $supplierInvoice)
    {
        return new SupplierInvoiceResource(
            $supplierInvoice->load(['supplier', 'purchaseOrder', 'goodsReceiptNote'])
        );
    }

    public function formOptions()
    {
        return response()->json([
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            // Same filters the Blade create page used: a received order and a
            // confirmed GRN are the only ones an invoice can reference.
            'purchase_orders' => PurchaseOrder::where('status', 'received')
                ->orderByDesc('id')->get(['id', 'po_number']),
            'grns' => GoodsReceiptNote::where('status', 'confirmed')
                ->orderByDesc('id')->get(['id', 'grn_number']),
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $invoice = SupplierInvoice::create($data + [
            'status' => 'unpaid',
            'paid_amount' => 0,
        ]);

        event(new SupplierInvoiceSaved($invoice));

        return (new SupplierInvoiceResource($invoice->load(['supplier', 'purchaseOrder', 'goodsReceiptNote'])))
            ->response()->setStatusCode(201);
    }

    /**
     * Note what is NOT accepted here: `paid_amount`. The Blade controller passed
     * `$request->all()` straight into update(), and paid_amount is fillable, so a
     * crafted request could set an invoice's paid figure directly and bypass the
     * payment flow entirely. Payments own that column.
     */
    public function update(Request $request, SupplierInvoice $supplierInvoice)
    {
        $data = $this->validated($request, $supplierInvoice);

        $supplierInvoice->update($data);

        event(new SupplierInvoiceSaved($supplierInvoice));

        return new SupplierInvoiceResource(
            $supplierInvoice->load(['supplier', 'purchaseOrder', 'goodsReceiptNote'])
        );
    }

    public function destroy(SupplierInvoice $supplierInvoice)
    {
        // Payments hang off the invoice; removing it would orphan them.
        abort_if(
            $supplierInvoice->payments()->exists(),
            422,
            'This invoice already has payments recorded and cannot be deleted.'
        );

        $id = $supplierInvoice->id;
        $supplierInvoice->delete();

        event(new SupplierInvoiceDeleted($id));

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request, ?SupplierInvoice $existing = null): array
    {
        $unique = 'unique:supplier_invoices,invoice_number'.($existing ? ','.$existing->id : '');

        return $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'invoice_number' => ['required', 'string', 'max:255', $unique],
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'goods_receipt_note_id' => 'nullable|exists:goods_receipt_notes,id',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'subtotal' => 'required|numeric|min:0',
            'vat_amount' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            // Only editable on an existing invoice, matching the Blade edit form.
            'status' => ($existing ? 'nullable|in:'.implode(',', self::STATUSES) : 'prohibited'),
        ]);
    }
}
