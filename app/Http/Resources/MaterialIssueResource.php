<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialIssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'issue_number' => $this->issue_number,
            'production_order_id' => $this->production_order_id,
            'production_order_number' => $this->whenLoaded('productionOrder', fn () => $this->productionOrder?->order_number),
            'item_id' => $this->item_id,
            'item_name' => $this->whenLoaded('item', fn () => $this->item?->item_name),
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->whenLoaded('warehouse', fn () => $this->warehouse?->name),
            'quantity' => $this->quantity,
            'issue_date' => $this->issue_date?->toDateString(),
            'notes' => $this->notes,
        ];
    }
}
