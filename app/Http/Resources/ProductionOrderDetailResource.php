<?php

namespace App\Http\Resources;

use App\Models\BillOfMaterial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything the production order detail page renders.
 *
 * The Blade page laid out four sections — Order Details, Bill of Materials,
 * Material Issues and Production Output — but its controller passed only
 * `$productionOrder` while the view read `$bom`, `$materialIssues` and
 * `$outputs`. All three `isset()` guards were false, so the page permanently
 * showed "No BOM defined for this product." and neither table at all.
 */
class ProductionOrderDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $target = (float) $this->quantity_to_produce;
        $made = (float) $this->quantity_produced;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number ?? 'PRD-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT),
            'product_id' => $this->product_id,
            'product_name' => $this->product?->item_name,
            'quantity_to_produce' => $this->quantity_to_produce,
            'quantity_produced' => $this->quantity_produced,
            'outstanding' => round($target - $made, 2),
            'production_date' => $this->production_date?->toDateString(),
            'completion_date' => $this->completion_date?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,

            // The BOM belongs to the product, not the order.
            'bom' => BillOfMaterial::with('rawMaterial')
                ->where('product_id', $this->product_id)
                ->get()
                ->map(fn ($line) => [
                    'id' => $line->id,
                    'material_name' => $line->rawMaterial?->item_name,
                    'quantity_required' => $line->quantity_required,
                    'unit_of_measure' => $line->unit_of_measure,
                ])->values(),

            'material_issues' => $this->whenLoaded('materialIssues', fn () => $this->materialIssues->map(fn ($issue) => [
                'id' => $issue->id,
                'item_name' => $issue->item?->item_name,
                'warehouse_name' => $issue->warehouse?->name,
                'quantity' => $issue->quantity,
                'issue_date' => $issue->issue_date?->toDateString(),
            ])->values()),

            'outputs' => $this->whenLoaded('outputs', fn () => $this->outputs->map(fn ($output) => [
                'id' => $output->id,
                'item_name' => $output->item?->item_name,
                'warehouse_name' => $output->warehouse?->name,
                'quantity' => $output->quantity,
                'output_date' => $output->output_date?->toDateString(),
            ])->values()),
        ];
    }
}
