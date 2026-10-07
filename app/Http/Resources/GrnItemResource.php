<?php

namespace App\Http\Resources;

use App\Models\UnitConversion;
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
            'project_id' => $this->project_id,
            'project_name' => $this->whenLoaded('project', fn () => $this->project?->name),
            // Received in the supplier's unit: what arrived in theirs and, once
            // set, what one holds in ours. `suggested_factor` is where the
            // converter starts — the order's own factor, else the last one used
            // for this item in this unit.
            'supplier_unit' => $this->supplier_unit,
            'supplier_quantity' => $this->supplier_quantity,
            'supplier_quantity_ordered' => $this->when($this->inSupplierUnit(), fn () => $this->whenLoaded(
                'purchaseOrderItem', fn () => $this->purchaseOrderItem?->supplier_quantity,
            )),
            'supplier_rate' => $this->supplier_rate,
            'unit_factor' => $this->unit_factor,
            'conversion_pending' => $this->conversionPending(),
            'suggested_factor' => $this->when($this->conversionPending(), fn () => $this->suggestedFactor()),
            'converted_by_name' => $this->whenLoaded('convertedBy', fn () => $this->convertedBy?->name),
            'converted_at' => $this->converted_at?->toIso8601String(),
        ];
    }

    private function suggestedFactor(): ?float
    {
        $ordered = $this->relationLoaded('purchaseOrderItem') ? $this->purchaseOrderItem : $this->purchaseOrderItem()->first();

        if ($ordered?->unit_factor > 0) {
            return (float) $ordered->unit_factor;
        }

        return UnitConversion::factorFor($this->item_id, $this->supplier_unit);
    }
}
