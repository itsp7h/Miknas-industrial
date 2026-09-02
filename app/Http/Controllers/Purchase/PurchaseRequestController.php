<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;

/**
 * All that is left of the Blade request controller: the DomPDF MPR document.
 * The create and edit forms are a React modal and the request sheet a React
 * page, so their writes live in Api\Purchase\PurchaseRequestController; GM
 * approval is the signature action on Api\Purchase\PurchasePipelineController,
 * which records the approval as it saves the signature.
 */
class PurchaseRequestController extends Controller
{
    public function print(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('view', $purchaseRequest);

        $purchaseRequest->load(['items', 'requestedBy', 'approvedBy', 'signature.signedBy']);

        return view('purchase.requests.print', compact('purchaseRequest'));
    }
}
