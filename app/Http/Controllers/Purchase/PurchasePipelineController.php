<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Policies\PurchaseRequestPolicy;
use App\Services\PurchaseStageService;

class PurchasePipelineController extends Controller
{
    private function withRelations()
    {
        $query = PurchaseRequest::with([
            'requestedBy',
            'signature.signedBy',
            'rfqInvitations.supplier',
            'supplierQuotes.items',
        ]);

        $user = auth()->user();

        if (! $user->can('purchase-requests.view-all')) {
            if ($user->can('purchase-requests.view-active-pipeline')) {
                $query->whereIn('stage', PurchaseRequestPolicy::ACTIVE_PIPELINE_STAGES);
            } elseif ($user->can('purchase-requests.view-own')) {
                $query->where('requested_by', $user->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    public function index(PurchaseStageService $stages)
    {
        $active    = $this->withRelations()->where('stage', '!=', 'complete')->latest()->get();
        $completed = $this->withRelations()->where('stage', 'complete')->latest()->get();

        return view('purchase.pipeline.index', compact('active', 'completed', 'stages'));
    }

    public function show(PurchaseRequest $purchaseRequest, PurchaseStageService $stages)
    {
        $this->authorize('view', $purchaseRequest);

        $purchaseRequest->load([
            'requestedBy',
            'items',
            'signature.signedBy',
            'rfqInvitations.supplier',
            'supplierQuotes.supplier',
            'supplierQuotes.items',
            'purchaseOrders.supplier',
        ]);

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        return view('purchase.pipeline.show', [
            'pr'        => $purchaseRequest,
            'stages'    => $stages,
            'suppliers' => $suppliers,
        ]);
    }
}
