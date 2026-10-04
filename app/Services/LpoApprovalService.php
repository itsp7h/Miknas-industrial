<?php

namespace App\Services;

use App\Events\PurchaseOrderSaved;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\IssuerSignature;
use RuntimeException;

/**
 * The second signature on an LPO, and the moment it goes to the supplier.
 *
 * Issuing signs an LPO under Prepared By and stops there. Approving signs it
 * under Approved By with the approver's saved signature, marks it sent and
 * emails it — nothing reaches a supplier before this. Once every live LPO on a
 * request is approved, the request moves on to Receiving.
 */
class LpoApprovalService
{
    public function __construct(
        private LpoDeliveryService $delivery,
        private PurchaseStageService $stages,
    ) {}

    /**
     * Returns null when the supplier was emailed, or why they were not. A failed
     * email does not undo the approval: the LPO is approved, and it can be sent
     * again from the order once the cause is fixed — the same rule issuing had.
     */
    public function approve(PurchaseOrder $order, User $user): ?string
    {
        $blocked = $order->approvalBlockedFor($user);
        abort_if($blocked !== null, $order->awaitingApproval() ? 403 : 422, $blocked ?? '');

        $signature = IssuerSignature::require(
            $user,
            'Add your signature before approving an LPO. It is saved to your profile and used on every LPO you sign.'
        );

        $order->update([
            'approved_by' => $user->id,
            'approved_at' => now(),
            // Frozen, like Prepared By's: the LPO keeps the signature it went out with.
            'approved_signature' => $signature,
            'status' => 'sent',
        ]);

        $failure = null;
        try {
            $this->delivery->deliver($order);
        } catch (RuntimeException $e) {
            $failure = $e->getMessage();
        }

        event(new PurchaseOrderSaved($order));

        $request = $order->purchaseRequest;
        if ($request && $request->stage === 'lpo') {
            $waiting = $request->purchaseOrders()->where('status', '!=', 'cancelled')->get()
                ->contains(fn (PurchaseOrder $po) => $po->awaitingApproval());

            if (! $waiting) {
                $this->stages->setStage($request, 'receiving');
            }
        }

        return $failure;
    }
}
