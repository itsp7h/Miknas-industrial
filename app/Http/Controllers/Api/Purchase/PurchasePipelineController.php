<?php

namespace App\Http\Controllers\Api\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseRequestBoardResource;
use App\Http\Resources\PurchaseRequestDetailResource;
use App\Models\PurchaseRequest;
use App\Policies\PurchaseRequestPolicy;

class PurchasePipelineController extends Controller
{
    public function index()
    {
        $query = PurchaseRequest::with('requestedBy');
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

        return PurchaseRequestBoardResource::collection($query->latest()->get());
    }

    /** Backs the React pipeline detail page. */
    public function show(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('view', $purchaseRequest);

        // The same relation graph the Blade show() loaded, so the sidebar and
        // timeline have every count and name they render without N+1 queries.
        $purchaseRequest->load([
            'requestedBy', 'items', 'signature.signedBy',
            'rfqInvitations.supplier', 'supplierQuotes.supplier', 'supplierQuotes.items',
            'purchaseOrders.supplier',
        ]);

        return new PurchaseRequestDetailResource($purchaseRequest);
    }
}
