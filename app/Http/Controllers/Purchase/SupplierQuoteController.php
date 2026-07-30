<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Setting;
use App\Models\SupplierQuoteItem;
use App\Services\PurchaseStageService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SupplierQuoteController extends Controller
{
    /**
     * Both routes render the same workspace — "view submitted quotes" and
     * "compare & award" are just two names for the same comparison view.
     */
    public function index(PurchaseRequest $purchaseRequest)
    {
        return $this->workspace($purchaseRequest);
    }

    public function compare(PurchaseRequest $purchaseRequest)
    {
        return $this->workspace($purchaseRequest);
    }

    private function workspace(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('manageQuotes', $purchaseRequest);

        $quotes = $this->loadQuotes($purchaseRequest);

        return view('purchase.quotes.workspace', [
            'request'          => $purchaseRequest,
            'quotes'           => $quotes,
            'items'            => $purchaseRequest->items,
            'fullyAwarded'     => $purchaseRequest->isFullyAwarded(),
            ...$this->totals($purchaseRequest, $quotes),
        ]);
    }

    /**
     * Award a single request item to the supplier who quoted it — items on the
     * same request can go to different suppliers, so awarding happens per item
     * rather than per whole quote.
     */
    public function awardItem(Request $request, PurchaseRequest $purchaseRequest, SupplierQuoteItem $quoteItem, PurchaseStageService $stages)
    {
        $this->authorize('award', $purchaseRequest);

        abort_unless($quoteItem->quote->purchase_request_id === $purchaseRequest->id, 404);

        $validated = $request->validate([
            'award_reason' => ['required', 'string', 'min:5'],
        ]);

        if ($quoteItem->not_available || !$quoteItem->purchase_request_item_id) {
            return response()->json(['message' => 'This item cannot be awarded.'], 422);
        }

        $alreadyAwardedElsewhere = SupplierQuoteItem::where('purchase_request_item_id', $quoteItem->purchase_request_item_id)
            ->where('is_awarded', true)
            ->where('id', '!=', $quoteItem->id)
            ->whereHas('quote', fn ($q) => $q->where('purchase_request_id', $purchaseRequest->id))
            ->exists();

        if ($alreadyAwardedElsewhere) {
            return response()->json(['message' => 'This item has already been awarded to another supplier.'], 422);
        }

        $quoteItem->update([
            'is_awarded'   => true,
            'award_reason' => $validated['award_reason'],
            'awarded_at'   => now(),
            'awarded_by'   => auth()->id(),
        ]);

        if ($purchaseRequest->isFullyAwarded()) {
            $stages->setStageIfNotPast($purchaseRequest, 'lpo');
        }

        return $this->itemResponse(
            $purchaseRequest,
            $quoteItem->purchaseRequestItem,
            $quoteItem->description . ' awarded to ' . $quoteItem->quote->supplier->name . '.'
        );
    }

    /**
     * Revoke an item's award so a different supplier can be chosen instead.
     */
    public function unawardItem(PurchaseRequest $purchaseRequest, SupplierQuoteItem $quoteItem, PurchaseStageService $stages)
    {
        $this->authorize('award', $purchaseRequest);

        abort_unless($quoteItem->quote->purchase_request_id === $purchaseRequest->id, 404);

        if (!$quoteItem->is_awarded) {
            return response()->json(['message' => 'This item has not been awarded.'], 422);
        }

        $supplierName = $quoteItem->quote->supplier->name;

        $quoteItem->update([
            'is_awarded'   => false,
            'award_reason' => null,
            'awarded_at'   => null,
            'awarded_by'   => null,
        ]);

        if ($purchaseRequest->stage === 'lpo') {
            $stages->setStage($purchaseRequest, 'comparison');
        }

        return $this->itemResponse(
            $purchaseRequest,
            $quoteItem->purchaseRequestItem,
            $quoteItem->description . ' unawarded from ' . $supplierName . '. Pick a new supplier.'
        );
    }

    private function loadQuotes(PurchaseRequest $purchaseRequest): Collection
    {
        $quotes = $purchaseRequest->supplierQuotes()->with('supplier', 'items.awardedBy')->get();

        // Key each quote's line items by the actual purchase-request item they quoted,
        // so suppliers are compared item-for-item rather than by array position.
        $quotes->each(function ($quote) {
            $quote->itemsByRequestItem = $quote->items->keyBy('purchase_request_item_id');
        });

        return $quotes;
    }

    /**
     * A per-supplier "total" is misleading once items are split across suppliers —
     * each supplier's total only covers what they were asked to quote. The real
     * subtotal is the sum, across all items, of whichever price wins each one:
     * the awarded price if decided, otherwise the current lowest offer. VAT is
     * added on top for whichever winning items are marked vatable, same as how
     * a supplier's own quote total is computed when they submit it.
     */
    private function totals(PurchaseRequest $purchaseRequest, Collection $quotes): array
    {
        $vatRate         = (float) Setting::get('vat_rate', 0);
        $subtotal        = 0;
        $vatAmount       = 0;
        $unresolvedItems = 0;

        foreach ($purchaseRequest->items as $item) {
            $validPrices = $quotes->map(fn ($q) => $q->itemsByRequestItem->get($item->id))
                ->filter(fn ($qi) => $qi && !$qi->not_available);

            if ($validPrices->isEmpty()) {
                $unresolvedItems++;
                continue;
            }

            $winner = $validPrices->firstWhere('is_awarded', true) ?? $validPrices->sortBy('unit_price')->first();
            $subtotal += $winner->total_price;

            if ($winner->is_vatable && $vatRate > 0) {
                $vatAmount += round($winner->total_price * $vatRate / 100, 3);
            }
        }

        return [
            'subtotal'        => $subtotal,
            'vatRate'         => $vatRate,
            'vatAmount'       => $vatAmount,
            'grandTotal'      => $subtotal + $vatAmount,
            'unresolvedItems' => $unresolvedItems,
        ];
    }

    /**
     * Build the JSON payload the compare page needs to redraw one item's card
     * and the page-wide totals, without a full page reload.
     */
    private function itemPayload(Collection $quotes, PurchaseRequestItem $item): array
    {
        $rowEntries   = $quotes->map(fn ($q) => ['quote' => $q, 'item' => $q->itemsByRequestItem->get($item->id)]);
        $validEntries = $rowEntries->filter(fn ($e) => $e['item'] && !$e['item']->not_available);
        $awardedEntry = $validEntries->firstWhere('item.is_awarded', true);
        $minPrice     = $validEntries->count() ? $validEntries->min(fn ($e) => (float) $e['item']->unit_price) : null;

        if ($awardedEntry) {
            $badge = ['bg' => '#dcfce7', 'fg' => '#15803d', 'label' => '✓ Awarded to ' . $awardedEntry['quote']->supplier->name];
        } elseif ($validEntries->count() >= 2) {
            $badge = ['bg' => '#dbeafe', 'fg' => '#1d4ed8', 'label' => $validEntries->count() . ' suppliers competing'];
        } elseif ($validEntries->count() === 1) {
            $badge = ['bg' => '#fef3c7', 'fg' => '#92400e', 'label' => 'Sole-sourced'];
        } else {
            $badge = ['bg' => '#f1f5f9', 'fg' => '#64748b', 'label' => 'No quotes yet'];
        }

        return [
            'id'          => $item->id,
            'description' => $item->description,
            'quantity'    => $item->quantity_required,
            'unit'        => $item->unit,
            'badge'       => $badge,
            'rows'        => $rowEntries->map(function ($entry) use ($minPrice, $validEntries) {
                $qi = $entry['item'];
                $isMin = $qi && !$qi->not_available && $minPrice !== null
                    && (float) $qi->unit_price === $minPrice && $validEntries->count() > 1;

                return [
                    'supplier'     => $entry['quote']->supplier->name,
                    'leadTimeDays' => $entry['quote']->lead_time_days,
                    'paymentTerms' => $entry['quote']->payment_terms,
                    'notes'        => $entry['quote']->notes,
                    'isMin'        => $isMin,
                    'item'         => $qi ? [
                        'id'                  => $qi->id,
                        'unitPrice'           => number_format($qi->unit_price, 3),
                        'totalPrice'          => number_format($qi->total_price, 3),
                        'notAvailable'        => (bool) $qi->not_available,
                        'isVatable'           => (bool) $qi->is_vatable,
                        'supplierDescription' => $qi->supplier_description,
                        'isAwarded'           => (bool) $qi->is_awarded,
                        'awardReason'         => $qi->award_reason,
                        'awardedAt'           => $qi->awarded_at?->format('d M Y, H:i'),
                        'awardedBy'           => $qi->awardedBy?->name,
                    ] : null,
                ];
            })->values(),
            'hasAward'    => (bool) $awardedEntry,
        ];
    }

    private function itemResponse(PurchaseRequest $purchaseRequest, ?PurchaseRequestItem $item, string $message)
    {
        $quotes = $this->loadQuotes($purchaseRequest);

        return response()->json([
            'message'      => $message,
            'item'         => $item ? $this->itemPayload($quotes, $item) : null,
            'fullyAwarded' => $purchaseRequest->isFullyAwarded(),
            ...$this->totals($purchaseRequest, $quotes),
        ]);
    }
}
