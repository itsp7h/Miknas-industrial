<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'sales_order_id' => $this->sales_order_id,
            'order_number' => $this->whenLoaded('salesOrder', fn () => $this->salesOrder?->order_number),
            'customer_id' => $this->customer_id,
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'invoice_date' => $this->invoice_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'subtotal' => $this->subtotal,
            'vat_rate' => $this->vat_rate,
            'vat_amount' => $this->vat_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'balance_due' => round(((float) $this->total_amount) - ((float) $this->paid_amount), 2),
            'status' => $this->status,
            'notes' => $this->notes,
        ];
    }
}
