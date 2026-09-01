<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrnItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'item_name' => $this->whenLoaded('item', fn () => $this->item?->item_name),
            'unit_of_measure' => $this->whenLoaded('item', fn () => $this->item?->unit_of_measure),
            // The Blade show page printed $item->quantity_ordered, which is not a
            // column on grn_items and so always rendered 0.00. The ordered figure
            // lives on the linked purchase order line.
            'quantity_ordered' => $this->whenLoaded('purchaseOrderItem', fn () => $this->purchaseOrderItem?->quantity),
            'quantity_received' => $this->quantity_received,
            'unit_cost' => $this->unit_cost,
            'type' => $this->type,
        ];
    }
}
