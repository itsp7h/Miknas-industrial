<?php

namespace App\Http\Resources;

use App\Models\Settings\ProjectSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Defensive parity with the Blade index, which rendered this same
            // fallback inline. The column is NOT NULL UNIQUE, so the right-hand
            // side is unreachable in practice.
            'po_number' => $this->po_number ?? 'PO-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT),
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->whenLoaded('supplier', fn () => $this->supplier?->name),
            'purchase_request_id' => $this->purchase_request_id,
            'po_date' => $this->po_date?->toDateString(),
            'expected_delivery_date' => $this->expected_delivery_date?->toDateString(),
            'total_amount' => $this->total_amount,
            'status' => $this->status ?? 'draft',
            'notes' => $this->notes,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            // The detail page renders the supplier block of the printed LPO, so it
            // needs the contact fields rather than just the name.
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
                'contact_person' => $this->supplier->contact_person,
                'address' => $this->supplier->address,
                'phone' => $this->supplier->phone,
                'email' => $this->supplier->email,
            ] : null),
            // The LPO sheet is headed by the project's company, resolved the same
            // way the Blade show page did. Gated on purchaseRequest being loaded
            // so the index listing never runs this lookup per row.
            'company_name' => $this->whenLoaded(
                'purchaseRequest',
                fn () => $this->purchaseRequest
                    ? ProjectSetting::where('name', $this->purchaseRequest->project_name)
                        ->with('company')->first()?->company?->name
                    : null
            ),
            'purchase_request' => $this->whenLoaded('purchaseRequest', fn () => $this->purchaseRequest ? [
                'id' => $this->purchaseRequest->id,
                'request_number' => $this->purchaseRequest->request_number,
                'project_name' => $this->purchaseRequest->project_name,
            ] : null),
            'goods_receipt_notes' => $this->whenLoaded('goodsReceiptNotes', fn () => $this->goodsReceiptNotes->map(fn ($grn) => [
                'id' => $grn->id,
                'grn_number' => $grn->grn_number ?? 'GRN-'.str_pad((string) $grn->id, 5, '0', STR_PAD_LEFT),
                'warehouse_name' => $grn->warehouse?->name,
                'received_date' => $grn->received_date ? $grn->received_date->toDateString() : null,
                'status' => $grn->status ?? 'received',
            ])),
        ];
    }
}
