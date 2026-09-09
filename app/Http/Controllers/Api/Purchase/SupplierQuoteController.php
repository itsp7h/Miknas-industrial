<?php

namespace App\Http\Controllers\Api\Purchase;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Setting;
use App\Models\SupplierQuoteItem;
use App\Services\PurchaseStageService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The quotes workspace: every request item beside every supplier's offer for it,
 * and the award decision per item. "View quotes" and "compare & award" were two
 * names for this one view in Blade, and remain one page here.
 */
class SupplierQuoteController extends Controller
{
    public function index(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('manageQuotes', $purchaseRequest);

        return response()->json(['data' => $this->payload($purchaseRequest)]);
    }

    /** The whole workspace: items, offers, awards and totals. */
    private function payload(PurchaseRequest $purchaseRequest): array
    {
        $quotes = $this->loadQuotes($purchaseRequest);
        $user = request()->user();

        return [
            'id' => $purchaseRequest->id,
            'request_number' => $purchaseRequest->request_number,
            'project_name' => $purchaseRequest->project_name,
            'stage' => $purchaseRequest->stage,
            'quote_count' => $quotes->count(),
            'items' => $purchaseRequest->items->map(fn ($item) => $this->itemPayload($quotes, $item))->values(),
            // Flat list of awarded lines, which the Awarded Suppliers tab
            // groups by supplier.
            'awards' => $this->awards($quotes),
            'fully_awarded' => $purchaseRequest->isFullyAwarded(),
            'permissions' => [
                'award' => (bool) $user?->can('award', $purchaseRequest),
            ],
            ...$this->totals($purchaseRequest, $quotes),
        ];
    }

    public function award(Request $request, PurchaseRequest $purchaseRequest, SupplierQuoteItem $quoteItem, PurchaseStageService $stages)
    {
        $this->authorize('award', $purchaseRequest);

        abort_unless($quoteItem->quote->purchase_request_id === $purchaseRequest->id, 404);

        $validated = $request->validate([
            // A reason is on the record for good: it is what an auditor reads.
            'award_reason' => ['required', 'string', 'min:5'],
        ]);

        abort_if(
            $quoteItem->not_available || ! $quoteItem->purchase_request_item_id,
            422,
            'This item cannot be awarded.'
        );

        // One item, one supplier — awarding a second would double-order it.
        abort_if(
            SupplierQuoteItem::where('purchase_request_item_id', $quoteItem->purchase_request_item_id)
                ->where('is_awarded', true)
                ->where('id', '!=', $quoteItem->id)
                ->whereHas('quote', fn ($q) => $q->where('purchase_request_id', $purchaseRequest->id))
                ->exists(),
            422,
            'This item has already been awarded to another supplier.'
        );

        $quoteItem->update([
            'is_awarded' => true,
            'award_reason' => $validated['award_reason'],
            'awarded_at' => now(),
            'awarded_by' => auth()->id(),
        ]);

        if ($purchaseRequest->isFullyAwarded()) {
            $stages->setStageIfNotPast($purchaseRequest, 'lpo');
        }

        return response()->json([
            'data' => $this->payload($purchaseRequest->refresh()),
            'message' => $quoteItem->description.' awarded to '.$quoteItem->quote->supplier->name.'.',
        ]);
    }

    public function unaward(PurchaseRequest $purchaseRequest, SupplierQuoteItem $quoteItem, PurchaseStageService $stages)
    {
        $this->authorize('award', $purchaseRequest);

        abort_unless($quoteItem->quote->purchase_request_id === $purchaseRequest->id, 404);
        abort_unless($quoteItem->is_awarded, 422, 'This item has not been awarded.');

        $supplierName = $quoteItem->quote->supplier->name;
        $description = $quoteItem->description;

        $quoteItem->update([
            'is_awarded' => false,
            'award_reason' => null,
            'awarded_at' => null,
            'awarded_by' => null,
        ]);

        // Taking an award back means the request is no longer ready for an LPO.
        if ($purchaseRequest->stage === 'lpo') {
            $stages->setStage($purchaseRequest, 'comparison');
        }

        return response()->json([
            'data' => $this->payload($purchaseRequest->refresh()),
            'message' => $description.' unawarded from '.$supplierName.'. Pick a new supplier.',
        ]);
    }

    private function loadQuotes(PurchaseRequest $purchaseRequest): Collection
    {
        $quotes = $purchaseRequest->supplierQuotes()->with('supplier', 'items.awardedBy')->get();

        // Keyed by the request item each line quoted, so suppliers are compared
        // item-for-item rather than by array position.
        $quotes->each(function ($quote) {
            $quote->itemsByRequestItem = $quote->items->keyBy('purchase_request_item_id');
        });

        return $quotes;
    }

    private function awards(Collection $quotes): array
    {
        return $quotes->flatMap(fn ($quote) => $quote->items)
            ->filter(fn ($line) => $line->is_awarded)
            ->map(fn ($line) => [
                'id' => $line->id,
                'item' => $line->description,
                'quantity' => $line->quantity,
                'unit' => $line->unit,
                'supplier' => $line->quote->supplier?->name,
                'unit_price' => (float) $line->unit_price,
                'total_price' => (float) $line->total_price,
                'reason' => $line->award_reason,
                'awarded_at' => $line->awarded_at?->format('d M Y, H:i'),
                'awarded_by' => $line->awardedBy?->name,
            ])->values()->all();
    }

    /**
     * A per-supplier total is misleading once items split across suppliers: each
     * supplier's own total only covers what they were asked for. The real
     * subtotal sums, across all items, whichever price wins each one — the
     * awarded price where decided, the lowest offer otherwise — with VAT added
     * for the winning lines marked vatable.
     */
    private function totals(PurchaseRequest $purchaseRequest, Collection $quotes): array
    {
        $vatRate = (float) Setting::get('vat_rate', 0);
        $subtotal = 0;
        $vatAmount = 0;
        $unresolved = 0;

        foreach ($purchaseRequest->items as $item) {
            $offers = $quotes->map(fn ($q) => $q->itemsByRequestItem->get($item->id))
                ->filter(fn ($line) => $line && ! $line->not_available);

            if ($offers->isEmpty()) {
                $unresolved++;

                continue;
            }

            $winner = $offers->firstWhere('is_awarded', true) ?? $offers->sortBy('unit_price')->first();
            $subtotal += $winner->total_price;

            if ($winner->is_vatable && $vatRate > 0) {
                $vatAmount += round($winner->total_price * $vatRate / 100, 3);
            }
        }

        return [
            'subtotal' => round($subtotal, 3),
            'vat_rate' => $vatRate,
            'vat_amount' => round($vatAmount, 3),
            'grand_total' => round($subtotal + $vatAmount, 3),
            'unresolved_items' => $unresolved,
        ];
    }

    private function itemPayload(Collection $quotes, PurchaseRequestItem $item): array
    {
        $rows = $quotes->map(fn ($q) => ['quote' => $q, 'line' => $q->itemsByRequestItem->get($item->id)]);
        $valid = $rows->filter(fn ($row) => $row['line'] && ! $row['line']->not_available);
        $awarded = $valid->firstWhere('line.is_awarded', true);
        $minPrice = $valid->count() ? $valid->min(fn ($row) => (float) $row['line']->unit_price) : null;

        return [
            'id' => $item->id,
            'description' => $item->description,
            'quantity' => $item->quantity_required,
            'unit' => $item->unit,
            'badge' => $this->badge($awarded, $valid->count()),
            'has_award' => (bool) $awarded,
            'rows' => $rows->map(function ($row) use ($minPrice, $valid) {
                $line = $row['line'];
                $isMin = $line && ! $line->not_available && $minPrice !== null
                    && (float) $line->unit_price === $minPrice && $valid->count() > 1;

                return [
                    'supplier' => $row['quote']->supplier?->name,
                    'lead_time_days' => $row['quote']->lead_time_days,
                    'payment_terms' => $row['quote']->payment_terms,
                    'notes' => $row['quote']->notes,
                    'is_min' => $isMin,
                    'line' => $line ? [
                        'id' => $line->id,
                        'unit_price' => (float) $line->unit_price,
                        'total_price' => (float) $line->total_price,
                        'not_available' => (bool) $line->not_available,
                        'is_vatable' => (bool) $line->is_vatable,
                        'supplier_description' => $line->supplier_description,
                        'is_awarded' => (bool) $line->is_awarded,
                        'award_reason' => $line->award_reason,
                        'awarded_at' => $line->awarded_at?->format('d M Y, H:i'),
                        'awarded_by' => $line->awardedBy?->name,
                    ] : null,
                ];
            })->values(),
        ];
    }

    private function badge(?array $awarded, int $validCount): array
    {
        if ($awarded) {
            return ['background' => '#dcfce7', 'colour' => '#15803d', 'label' => '✓ Awarded to '.$awarded['quote']->supplier?->name];
        }

        if ($validCount >= 2) {
            return ['background' => '#dbeafe', 'colour' => '#1d4ed8', 'label' => $validCount.' suppliers competing'];
        }

        if ($validCount === 1) {
            return ['background' => '#fef3c7', 'colour' => '#92400e', 'label' => 'Sole-sourced'];
        }

        return ['background' => '#f1f5f9', 'colour' => '#64748b', 'label' => 'No quotes yet'];
    }
}
