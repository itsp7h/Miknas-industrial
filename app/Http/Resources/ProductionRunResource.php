<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'run_number' => $this->run_number,
            'item_id' => $this->item_id,
            'item_code' => $this->item?->item_code,
            'item_name' => $this->item?->item_name,
            'unit_of_measure' => $this->item?->unit_of_measure,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->warehouse?->name,
            'quantity' => $this->quantity,
            'production_date' => $this->production_date?->toDateString(),
            'total_cost' => $this->total_cost,
            'unit_cost' => $this->unit_cost,
            'notes' => $this->notes,
            'created_by_name' => $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item_code' => $line->item?->item_code,
                'item_name' => $line->item?->item_name,
                'unit_of_measure' => $line->item?->unit_of_measure,
                'warehouse_id' => $line->warehouse_id,
                'warehouse_name' => $line->warehouse?->name,
                'quantity' => $line->quantity,
                'unit_cost' => $line->unit_cost,
                'line_cost' => $line->line_cost,
            ])->values()),
        ];
    }
}
