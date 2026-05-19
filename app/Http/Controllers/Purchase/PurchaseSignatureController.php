<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\PurchaseSignature;
use App\Services\PurchaseStageService;
use Illuminate\Http\Request;

class PurchaseSignatureController extends Controller
{
    public function show(PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->load('signature.signedBy');
        return view('purchase.signature.show', ['request' => $purchaseRequest]);
    }

    public function store(Request $request, PurchaseRequest $purchaseRequest, PurchaseStageService $stages)
    {
        $validated = $request->validate([
            'signature_image' => ['required', 'string'],
        ]);

        if ($purchaseRequest->signature) {
            return back()->with('error', 'This request has already been signed.');
        }

        PurchaseSignature::create([
            'purchase_request_id' => $purchaseRequest->id,
            'signed_by'           => auth()->id(),
            'signature_image'     => $validated['signature_image'],
            'signed_at'           => now(),
            'ip_address'          => $request->ip(),
        ]);

        $stages->advance($purchaseRequest);

        return redirect()->route('purchase.pipeline.index')
            ->with('success', 'Signature saved. Request moved to RFQ stage.');
    }
}
