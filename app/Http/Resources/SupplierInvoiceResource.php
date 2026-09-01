<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->whenLoaded('supplier', fn () => $this->supplier?->name),
            'purchase_order_id' => $this->purchase_order_id,
            'po_number' => $this->whenLoaded('purchaseOrder', fn () => $this->purchaseOrder?->po_number),
            'goods_receipt_note_id' => $this->goods_receipt_note_id,
            'grn_number' => $this->whenLoaded('goodsReceiptNote', fn () => $this->goodsReceiptNote?->grn_number),
            'invoice_date' => $this->invoice_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'subtotal' => $this->subtotal,
            'vat_amount' => $this->vat_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            // The index computed this inline; sending it keeps the two viewports
            // from each doing their own arithmetic.
            'outstanding' => (string) number_format((float) $this->total_amount - (float) $this->paid_amount, 2, '.', ''),
            'status' => $this->status ?? 'unpaid',
            'notes' => $this->notes,
        ];
    }
}
