<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'order_date' => $this->order_date?->toDateString(),
            'delivery_date' => $this->delivery_date?->toDateString(),
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'items' => SalesOrderItemResource::collection($this->whenLoaded('items')),
            // Summaries only — the detail page links onward rather than
            // duplicating the delivery-note and invoice pages.
            'delivery_notes' => $this->whenLoaded('deliveryNotes', fn () => $this->deliveryNotes->map(fn ($note) => [
                'id' => $note->id,
                'delivery_number' => $note->delivery_number,
                'delivery_date' => $note->delivery_date?->toDateString(),
                'status' => $note->status,
            ])),
            'invoices' => $this->whenLoaded('invoices', fn () => $this->invoices->map(fn ($invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date?->toDateString(),
                'total_amount' => $invoice->total_amount,
                'status' => $invoice->status,
            ])),
        ];
    }
}
