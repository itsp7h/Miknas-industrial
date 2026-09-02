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

            // Keyed off the status, not merely off the relation: a request that
            // was approved and then rejected still carries approved_by, and this
            // block must not print "Approved By" over a refusal.
            'approval' => $this->whenLoaded('approvedBy', fn () => ($this->approvedBy && $this->status === 'approved') ? [
                'approved_by_name' => $this->approvedBy->name,
                'approved_at' => $this->approved_at?->toIso8601String(),
            ] : null),

            // Same rule as the approval block, for the same reason: a request
            // approved after a refusal keeps both records, and only the one
            // matching the current status may be shown.
            'rejection' => $this->status === 'rejected' ? [
                'reason' => $this->rejection_reason,
                'rejected_by_name' => $this->whenLoaded('rejectedBy', fn () => $this->rejectedBy?->name),
                'rejected_at' => $this->rejected_at?->toIso8601String(),
            ] : null,

            // The MPR document is DomPDF-backed and stays server-rendered.
            'print_url' => route('purchase.requests.print', $this->resource),

            'permissions' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
            ],
        ];
    }
}
