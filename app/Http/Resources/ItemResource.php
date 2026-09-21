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
            'item_category_id' => $this->item_category_id,
            'item_category_name' => $this->itemCategory?->name,
            // What every surface shows: "Raw Materials / Chemical Materials".
            'category_path' => $this->categoryPath(),
            'unit_of_measure' => $this->unit_of_measure,
            'minimum_stock_level' => $this->minimum_stock_level,
            'quantity' => $this->totalQuantity(),
            'warehouses' => $this->stockByWarehouse(),
            // What the form's Warehouse select shows. An item in exactly one
            // place has an answer; one spread across several does not, and the
            // form must not offer to collapse that silently.
            'warehouse_id' => count($this->stockByWarehouse()) === 1
                ? $this->stockByWarehouse()[0]['id']
                : null,
            'cost_price' => $this->cost_price,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
