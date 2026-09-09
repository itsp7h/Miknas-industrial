<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_code' => $this->item_code,
            'item_name' => $this->item_name,
            'category' => $this->category,
            'unit_of_measure' => $this->unit_of_measure,
            'minimum_stock_level' => $this->minimum_stock_level,
            'cost_price' => $this->cost_price,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
