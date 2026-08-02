<?php

namespace App\Http\Controllers\Api\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseRequestBoardResource;
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
}
