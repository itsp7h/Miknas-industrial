<?php

namespace App\Http\Resources;

use App\Services\PurchaseStageService;
use App\Services\RfqInvitationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything the pipeline detail page renders. The stage list and its labels
 * are sent from PurchaseStageService rather than mirrored in JS, so the
 * timeline cannot drift from the stage machine. Policy gates are resolved here
 * too — the page must not re-implement authorization client-side.
 */
class PurchaseRequestDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stages = app(PurchaseStageService::class);
        $all = PurchaseStageService::STAGES;
        $index = $stages->stageIndex($this->stage);
        $user = $request->user();

        $invitations = $this->whenLoaded('rfqInvitations', fn () => $this->rfqInvitations, collect());
        $quotes = $this->whenLoaded('supplierQuotes', fn () => $this->supplierQuotes, collect());
        $minTotal = $quotes->min('total_amount');

        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'stage' => $this->stage,
            'stage_index' => $index,
            // The Blade progress bar's own maths.
            'progress_pct' => count($all) > 1 ? (int) round($index / (count($all) - 1) * 100) : 100,
            'is_done' => $this->stage === 'complete',
            'stages' => $all,
            'stage_labels' => collect($all)->mapWithKeys(fn ($s) => [$s => $stages->stageLabel($s)]),

            'status' => $this->status,
            'project_name' => $this->project_name,
            'department' => $this->department,
            'requested_by_name' => $this->requested_by_name ?: $this->requestedBy?->name,
            'date' => $this->date ? $this->date->toDateString() : null,
            'created_at' => $this->created_at?->toDateString(),
            'location' => $this->location,
            'required_date_text' => $this->required_date_text,
            'verified_by_name' => $this->verified_by_name,

            'signature' => $this->whenLoaded('signature', fn () => $this->signature ? [
                'signed_by_name' => $this->signature->signedBy?->name,
                'signed_at' => $this->signature->signed_at?->toDateString(),
            ] : null),

            'rfq_invitations' => $invitations->map(fn ($inv) => [
                'id' => $inv->id,
                'supplier_name' => $inv->supplier?->name,
                'channel' => $inv->channel,
                'status' => $inv->status,
                // Only surfaced where the Blade sidebar surfaced it: a pending
                // invitation on a request still in the RFQ/quoting stages.
                'whatsapp_link' => ($inv->status === 'pending'
                    && in_array($this->stage, ['rfq', 'quoting'], true)
                    && $inv->supplier?->phone)
                        ? app(RfqInvitationService::class)->whatsappLink($inv)
                        : null,
            ])->values(),
            'pending_invitation_count' => $invitations->where('status', 'pending')->count(),
            'sent_invitation_count' => $invitations->where('status', '!=', 'pending')->count(),

            'items' => $this->whenLoaded('items', fn () => $this->items->map(function ($item) use ($quotes) {
                $entries = $quotes->map(function ($q) use ($item) {
                    $qi = $q->items->firstWhere('purchase_request_item_id', $item->id);

                    return ($qi && ! $qi->not_available) ? ['quote' => $q, 'item' => $qi] : null;
                })->filter()->values();

                return [
                    'id' => $item->id,
                    'description' => $item->description,
                    'quote_count' => $entries->count(),
                    'quote_supplier_names' => $entries->map(fn ($e) => $e['quote']->supplier?->name)->filter()->values(),
                    'is_awarded' => $entries->contains(fn ($e) => $e['item']->is_awarded),
                ];
            })->values()),

            'supplier_quotes' => $quotes->sortBy('total_amount')->values()->map(function ($quote) use ($quotes, $minTotal) {
                $awarded = $quote->hasAwardedItems();

                return [
                    'id' => $quote->id,
                    'supplier_name' => $quote->supplier?->name,
                    'total_amount' => $quote->total_amount,
                    'has_awarded_items' => $awarded,
                    'awarded_item_count' => $quote->awardedItems()->count(),
                    'is_lowest' => ! $awarded && $quotes->count() > 1
                        && (float) $quote->total_amount === (float) $minTotal,
                ];
            }),
            'awarded_supplier_names' => $quotes->filter(fn ($q) => $q->hasAwardedItems())
                ->map(fn ($q) => $q->supplier?->name)->filter()->values(),

            'purchase_orders' => $this->whenLoaded('purchaseOrders', fn () => $this->purchaseOrders->map(fn ($po) => [
                'id' => $po->id,
                'po_number' => $po->po_number ?? 'PO-'.str_pad((string) $po->id, 5, '0', STR_PAD_LEFT),
                'supplier_name' => $po->supplier?->name,
                'total_amount' => $po->total_amount,
                'status' => $po->status ?? 'draft',
            ])->values()),

            'permissions' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'approve' => (bool) $user?->can('approve', $this->resource),
                'manageRfq' => (bool) $user?->can('manageRfq', $this->resource),
                'manageQuotes' => (bool) $user?->can('manageQuotes', $this->resource),
                'award' => (bool) $user?->can('award', $this->resource),
                'generateLpo' => (bool) $user?->can('generateLpo', $this->resource),
            ],
        ];
    }
}
