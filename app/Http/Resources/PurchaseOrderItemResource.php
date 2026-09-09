<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'item_code' => $this->whenLoaded('item', fn () => $this->item?->item_code),
            'item_name' => $this->whenLoaded('item', fn () => $this->item?->item_name),
            'unit_of_measure' => $this->whenLoaded('item', fn () => $this->item?->unit_of_measure),
            'quantity' => $this->quantity,
            'rate' => $this->rate,
            'total_amount' => $this->total_amount,
            'quantity_received' => $this->quantity_received,
        ];
    }
}
