<?php

namespace App\Services;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\SupplierQuoteItem;
use Illuminate\Support\Collection;
use RuntimeException;

class LpoGenerationService
{
    /**
     * Turn a request's awarded items into one LPO per winning supplier.
     * Items on the same request can be awarded to different suppliers, so
     * this can create several purchase orders from a single request.
     *
     * If awards changed after LPOs were already issued, the stale LPO(s) are
     * cancelled and replaced — unless goods or an invoice were already
     * recorded against one, in which case that has to be resolved manually
     * first rather than silently voided.
     */
    public function generate(PurchaseRequest $purchaseRequest): Collection
    {
        $staleOrders = $purchaseRequest->purchaseOrders()->where('status', '!=', 'cancelled')->get();

        foreach ($staleOrders as $stale) {
            if ($stale->goodsReceiptNotes()->exists() || $stale->supplierInvoices()->exists()) {
                throw new RuntimeException(
                    $stale->po_number . ' already has goods received or an invoice recorded — resolve that before re-issuing.'
                );
            }
        }

        $awardedItems = SupplierQuoteItem::where('is_awarded', true)
            ->whereHas('quote', fn ($q) => $q->where('purchase_request_id', $purchaseRequest->id))
            ->with('quote.supplier', 'purchaseRequestItem')
            ->get();

        if ($awardedItems->isEmpty()) {
            throw new RuntimeException('No awarded items to generate an LPO from.');
        }

        foreach ($staleOrders as $stale) {
            $stale->update(['status' => 'cancelled']);
        }

        return $awardedItems->groupBy('quote.supplier_id')->map(
            fn ($items, $supplierId) => $this->createOrderForSupplier($purchaseRequest, (int) $supplierId, $items)
        )->values();
    }

    private function createOrderForSupplier(PurchaseRequest $purchaseRequest, int $supplierId, Collection $items): PurchaseOrder
    {
        $order = PurchaseOrder::create([
            'po_number'           => 'PO-' . str_pad((PurchaseOrder::max('id') ?? 0) + 1, 5, '0', STR_PAD_LEFT),
            'supplier_id'         => $supplierId,
            'purchase_request_id' => $purchaseRequest->id,
            'po_date'             => now()->toDateString(),
            'total_amount'        => $items->sum('total_price'),
            'status'              => 'sent',
            'created_by'          => auth()->id(),
        ]);

        foreach ($items as $quoteItem) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id,
                'item_id'           => $this->resolveCatalogItem($quoteItem)->id,
                'quantity'          => $quoteItem->quantity,
                'rate'              => $quoteItem->unit_price,
                'total_amount'      => $quoteItem->total_price,
            ]);
        }

        return $order;
    }

    /**
     * MPR lines are free-text descriptions with no link to the Inventory
     * catalog. Match an existing Item by name, or create one so the LPO
     * has something concrete to reference.
     */
    private function resolveCatalogItem(SupplierQuoteItem $quoteItem): Item
    {
        $name = $quoteItem->purchaseRequestItem->description ?? $quoteItem->description;

        $item = Item::whereRaw('LOWER(item_name) = ?', [strtolower($name)])->first();
        if ($item) {
            return $item;
        }

        return Item::create([
            'item_code'       => 'ITEM-' . str_pad((Item::max('id') ?? 0) + 1, 5, '0', STR_PAD_LEFT),
            'item_name'       => $name,
            'category'        => 'raw_material',
            'unit_of_measure' => $quoteItem->unit ?: ($quoteItem->purchaseRequestItem->unit ?? 'PCS'),
            'cost_price'      => $quoteItem->unit_price,
            'is_active'       => true,
        ]);
    }
}
