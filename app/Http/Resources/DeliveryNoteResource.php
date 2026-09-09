<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'delivery_number' => $this->delivery_number,
            'sales_order_id' => $this->sales_order_id,
            'order_number' => $this->whenLoaded('salesOrder', fn () => $this->salesOrder?->order_number),
            'customer_id' => $this->customer_id,
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->whenLoaded('warehouse', fn () => $this->warehouse?->name),
            'delivery_date' => $this->delivery_date?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($line) => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item_name' => $line->item?->item_name,
                'quantity_delivered' => $line->quantity_delivered,
            ])),
        ];
    }
}
