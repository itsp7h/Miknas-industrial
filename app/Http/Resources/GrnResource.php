<?php

namespace App\Http\Resources;

use App\Models\GrnDocument;
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
            // The LPO, GRN and tax invoice, in that order. A receipt recorded
            // before uploads were asked for has none, and the page says so.
            'documents' => $this->whenLoaded('documents', fn () => collect(GrnDocument::KINDS)
                ->map(function ($label, $kind) {
                    $document = $this->documents->firstWhere('kind', $kind);

                    return [
                        'kind' => $kind,
                        'label' => $label,
                        'name' => $document?->original_name,
                        'size' => $document?->size,
                        'url' => $document ? route('purchase.grns.documents', [$this->id, $kind], false) : null,
                    ];
                })->values()),
            // What the receipt still needs, by label ("Tax Invoice"), for the
            // "Needs …" badge. Empty once all three are in.
            'missing_documents' => $this->whenLoaded('documents', fn () => collect(GrnDocument::KINDS)
                ->reject(fn ($label, $kind) => $this->documents->contains('kind', $kind))
                ->values()),
            // Whatever else came with the delivery, in upload order.
            'other_documents' => $this->whenLoaded('documents', fn () => $this->documents
                ->where('kind', GrnDocument::OTHER)->sortBy('id')
                ->map(fn ($document) => [
                    'id' => $document->id,
                    'name' => $document->original_name,
                    'size' => $document->size,
                    'url' => route('purchase.grns.documents.other', [$this->id, $document->id], false),
                ])->values()),
        ];
    }
}
