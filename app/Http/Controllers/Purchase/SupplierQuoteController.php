<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\SupplierQuote;
use App\Services\PurchaseStageService;
use Illuminate\Http\Request;

class SupplierQuoteController extends Controller
{
    public function index(PurchaseRequest $purchaseRequest)
    {
        $quotes = $purchaseRequest->supplierQuotes()->with('supplier', 'items')->get();
        return view('purchase.quotes.index', ['request' => $purchaseRequest, 'quotes' => $quotes]);
    }

    public function compare(PurchaseRequest $purchaseRequest)
    {
        $quotes = $purchaseRequest->supplierQuotes()->with('supplier', 'items')->get();
        $items  = $purchaseRequest->items;
        return view('purchase.quotes.compare', ['request' => $purchaseRequest, 'quotes' => $quotes, 'items' => $items]);
    }

    public function award(Request $request, PurchaseRequest $purchaseRequest, SupplierQuote $quote, PurchaseStageService $stages)
    {
        $validated = $request->validate([
            'award_reason' => ['required', 'string', 'min:5'],
        ]);

        if ($purchaseRequest->awardedQuote) {
            return back()->with('error', 'A quote has already been awarded for this request.');
        }

        $quote->update([
            'is_awarded'   => true,
            'award_reason' => $validated['award_reason'],
            'awarded_at'   => now(),
            'awarded_by'   => auth()->id(),
        ]);

        $stages->setStage($purchaseRequest, 'lpo');

        return redirect()->route('purchase.pipeline.index')
            ->with('success', 'Quote awarded to ' . $quote->supplier->name . '. Ready to issue LPO.');
    }
}
