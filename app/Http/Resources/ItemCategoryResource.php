<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'parent_type' => $this->parent_type,
            'parent_label' => $this->parentLabel(),
            // "Raw Materials / Chemical Materials" — the one string the
            // dropdown, the table cell and the filter all render.
            'path' => $this->path(),
            'sort_order' => $this->sort_order,
            'items_count' => $this->whenCounted('items'),
        ];
    }
}
