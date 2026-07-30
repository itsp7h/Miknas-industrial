<?php

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;

class PurchaseRequestPolicy
{
    private const ACTIVE_PIPELINE_STAGES = [
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

    public function approve(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.approve') && $purchaseRequest->stage === 'gm_approval';
    }

    public function manageRfq(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.manage-rfq') && $purchaseRequest->stage === 'rfq';
    }

    public function manageQuotes(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.manage-quotes')
            && in_array($purchaseRequest->stage, ['quoting', 'comparison'], true);
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
