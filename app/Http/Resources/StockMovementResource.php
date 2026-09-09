<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'item_name' => $this->whenLoaded('item', fn () => $this->item?->item_name),
            'item_code' => $this->whenLoaded('item', fn () => $this->item?->item_code),
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->whenLoaded('warehouse', fn () => $this->warehouse?->name),
            'type' => $this->type,
            'quantity' => $this->quantity,
            'notes' => $this->notes,
            // The Blade table had a "Reference" column reading $movement->reference,
            // which is neither a column nor an accessor — it always printed "-".
            // The real link is reference_type + reference_id.
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'reference' => $this->referenceLabel(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** Every reference_type written by the app, plus null for a manual adjustment. */
    private function referenceLabel(): ?string
    {
        if (! $this->reference_type) {
            return null;
        }

        $label = match ($this->reference_type) {
            'GoodsReceiptNote' => 'Goods Receipt',
            'DeliveryNote' => 'Delivery Note',
            'MaterialIssue' => 'Material Issue',
            'ProductionOutput' => 'Production Output',
            default => $this->reference_type,
        };

        return $this->reference_id ? $label.' #'.$this->reference_id : $label;
    }
}
