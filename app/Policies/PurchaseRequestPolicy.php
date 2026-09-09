<?php

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;

class PurchaseRequestPolicy
{
    public const ACTIVE_PIPELINE_STAGES = [
        'rfq', 'quoting', 'comparison', 'lpo', 'receiving', 'payment', 'complete',
    ];

    public function view(User $user, PurchaseRequest $purchaseRequest): bool
    {
        if ($user->can('purchase-requests.view-all')) {
            return true;
        }

        if ($user->can('purchase-requests.view-active-pipeline')) {
            return in_array($purchaseRequest->stage, self::ACTIVE_PIPELINE_STAGES, true);
        }

        if ($user->can('purchase-requests.view-own')) {
            return $purchaseRequest->requested_by === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('purchase-requests.create');
    }

    public function update(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.edit')
            && $purchaseRequest->requested_by === $user->id
            && $purchaseRequest->stage === 'draft';
    }

    public function delete(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.edit')
            && $purchaseRequest->requested_by === $user->id
            && $purchaseRequest->stage === 'draft';
    }

    public function approve(User $user, PurchaseRequest $purchaseRequest): bool
    {
        // 'draft' is the precondition stage: the signature endpoint
        // (Api\Purchase\PurchasePipelineController::storeSignature) is invoked
        // while the request is still at draft and only advances it to
        // gm_approval afterwards. 'gm_approval' is accepted too so re-checks
        // against an already-advanced request still pass.
        return $user->can('purchase-requests.approve')
            && in_array($purchaseRequest->stage, ['draft', 'gm_approval'], true);
    }

    public function manageRfq(User $user, PurchaseRequest $purchaseRequest): bool
    {
        // 'gm_approval' is the precondition stage:
        // Api\Purchase\PurchasePipelineController::selectSuppliers is invoked
        // right after the GM signature advances the request to gm_approval, and
        // it is itself the action that sets stage to 'rfq'.
        return $user->can('purchase-requests.manage-rfq')
            && in_array($purchaseRequest->stage, ['gm_approval', 'rfq'], true);
    }

    public function manageQuotes(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.manage-quotes')
            && in_array($purchaseRequest->stage, ['quoting', 'comparison', 'lpo'], true);
    }

    public function award(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.award')
            && in_array($purchaseRequest->stage, ['comparison', 'lpo'], true);
    }

    public function generateLpo(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.generate-lpo') && $purchaseRequest->stage === 'lpo';
    }
}
