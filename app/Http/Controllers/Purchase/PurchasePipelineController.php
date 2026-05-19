<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Services\PurchaseStageService;

class PurchasePipelineController extends Controller
{
    private function withRelations()
    {
        return PurchaseRequest::with([
            'requestedBy',
            'signature.signedBy',
            'rfqInvitations.supplier',
            'supplierQuotes',
            'awardedQuote.supplier',
        ]);
    }

    public function index(PurchaseStageService $stages)
    {
        $active    = $this->withRelations()->where('stage', '!=', 'complete')->latest()->get();
        $completed = $this->withRelations()->where('stage', 'complete')->latest()->get();

        return view('purchase.pipeline.index', compact('active', 'completed', 'stages'));
    }

    public function show(PurchaseRequest $purchaseRequest, PurchaseStageService $stages)
    {
        $purchaseRequest->load([
            'requestedBy',
            'items',
            'signature.signedBy',
            'rfqInvitations.supplier',
            'supplierQuotes',
            'awardedQuote.supplier',
        ]);

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        return view('purchase.pipeline.show', [
            'pr'        => $purchaseRequest,
            'stages'    => $stages,
            'suppliers' => $suppliers,
        ]);
    }
}
