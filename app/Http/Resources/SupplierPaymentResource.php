<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_invoice_id' => $this->supplier_invoice_id,
            'invoice_number' => $this->whenLoaded('supplierInvoice', fn () => $this->supplierInvoice?->invoice_number),
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->whenLoaded('supplierInvoice', fn () => $this->supplierInvoice?->supplier?->name),
            'payment_date' => $this->payment_date?->toDateString(),
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
        ];
    }
}
