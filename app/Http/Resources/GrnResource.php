<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grn_number' => $this->grn_number ?? 'GRN-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT),
            'purchase_order_id' => $this->purchase_order_id,
            'po_number' => $this->whenLoaded('purchaseOrder', fn () => $this->purchaseOrder
                ? ($this->purchaseOrder->po_number ?? 'PO-'.str_pad((string) $this->purchase_order_id, 5, '0', STR_PAD_LEFT))
                : null),
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->whenLoaded('purchaseOrder', fn () => $this->purchaseOrder?->supplier?->name),
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->whenLoaded('warehouse', fn () => $this->warehouse?->name),
            'received_date' => $this->received_date?->toDateString(),
            'status' => $this->status ?? 'draft',
            'notes' => $this->notes,
            'received_by_name' => $this->whenLoaded('receivedBy', fn () => $this->receivedBy?->name),
            'items' => GrnItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
