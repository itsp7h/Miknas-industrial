<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;

/**
 * What is left of the Blade request controller: the GM approve/reject actions
 * and the DomPDF MPR document. The create and edit pages are a React modal now
 * and the request sheet a React page, so their writes live in
 * Api\Purchase\PurchaseRequestController.
 */
class PurchaseRequestController extends Controller
{
    public function approve(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('approve', $purchaseRequest);

        $purchaseRequest->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Purchase request approved.');
    }

    public function reject(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('approve', $purchaseRequest);

        $purchaseRequest->update(['status' => 'rejected']);

        return redirect()->back()->with('success', 'Purchase request rejected.');
    }

    public function print(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('view', $purchaseRequest);

        $purchaseRequest->load(['items', 'requestedBy', 'approvedBy', 'signature.signedBy']);

        return view('purchase.requests.print', compact('purchaseRequest'));
    }
}
