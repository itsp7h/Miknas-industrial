<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $target = (float) $this->quantity_to_produce;
        $made = (float) $this->quantity_produced;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'product_id' => $this->product_id,
            'product_name' => $this->whenLoaded('product', fn () => $this->product?->item_name),
            'quantity_to_produce' => $this->quantity_to_produce,
            'quantity_produced' => $this->quantity_produced,
            'outstanding' => round($target - $made, 2),
            'production_date' => $this->production_date?->toDateString(),
            'completion_date' => $this->completion_date?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
        ];
    }
}
