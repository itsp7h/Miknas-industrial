<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'item_name' => $this->whenLoaded('item', fn () => $this->item?->item_name),
            'item_code' => $this->whenLoaded('item', fn () => $this->item?->item_code),
            'quantity' => $this->quantity,
            'price' => $this->price,
            'total_amount' => $this->total_amount,
            'quantity_delivered' => $this->quantity_delivered,
        ];
    }
}
