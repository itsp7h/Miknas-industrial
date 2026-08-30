<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillOfMaterialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->whenLoaded('product', fn () => $this->product?->item_name),
            'raw_material_id' => $this->raw_material_id,
            'raw_material_name' => $this->whenLoaded('rawMaterial', fn () => $this->rawMaterial?->item_name),
            'quantity_required' => $this->quantity_required,
            'unit_of_measure' => $this->unit_of_measure,
            'notes' => $this->notes,
        ];
    }
}
