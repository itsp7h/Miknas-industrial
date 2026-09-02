<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The full MPR sheet — the read-only view of a request, reached from the
 * pipeline detail page's "View Full Request". Its own payload rather than the
 * pipeline detail one: that shapes items for the timeline (quote counts, award
 * flags) and never carries remarks or the approval record.
 */
class PurchaseRequestSheetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'status' => $this->status,
            'stage' => $this->stage,
            'date' => $this->date?->toDateString(),
            'project_name' => $this->project_name,
            'requested_by_name' => $this->requested_by_name ?: $this->requestedBy?->name,
            'required_date_text' => $this->required_date_text,
            'location' => $this->location,
            'department' => $this->department,
            'remarks' => $this->remarks,

            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'unit' => $item->unit,
                'quantity_required' => $item->quantity_required,
                'purpose_use' => $item->purpose_use,
                'required_date' => $item->required_date?->toDateString(),
            ])->values()),

            // Only rendered when the request has actually been approved, as in
            // Blade.
            'approval' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy ? [
                'approved_by_name' => $this->approvedBy->name,
                'approved_at' => $this->approved_at?->toIso8601String(),
            ] : null),

            // The MPR document is DomPDF-backed and stays server-rendered.
            'print_url' => route('purchase.requests.print', $this->resource),

            'permissions' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
            ],
        ];
    }
}
