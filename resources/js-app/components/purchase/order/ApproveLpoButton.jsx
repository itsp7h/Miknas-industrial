import { useState } from 'react';
import ConfirmModal from '../../ui/ConfirmModal';
import SignatureDialog from '../../signature/SignatureDialog';
import { apiPost } from '../../../api/client';
import { useAccess } from '../../../layouts/AccessContext';
import { useToast } from '../../ui/Toast';

/**
 * Why this person may not approve the LPO, or null when they may — the
 * server's PurchaseOrder::approvalBlockedFor, asked here only to decide what to
 * offer. The approver must not be the person who prepared it.
 */
export function approvalBlockedReason(order, access) {
    if (!access.can('pipeline.approve-lpo')) return 'You do not have permission to approve LPOs';
    if (order.prepared_by_id != null && access.userId != null && Number(order.prepared_by_id) === access.userId) {
        return 'You prepared this LPO, so someone else must approve it';
    }

    return null;
}

/**
 * Approve & Sign: the second signature on an LPO, under Approved By, and the
 * moment it is emailed to the supplier. Asks first — it cannot be undone — and
 * asks for a signature when the approver has none saved, then carries on.
 *
 * Shown disabled with the reason when this person may not approve (gotcha #14).
 * `onApproved` gets the updated order.
 */
export default function ApproveLpoButton({ order, onApproved, label = '✍ Approve & Sign', style, className }) {
    const access = useAccess();
    const { showToast } = useToast();
    const [confirming, setConfirming] = useState(false);
    const [askingSignature, setAskingSignature] = useState(false);
    const [busy, setBusy] = useState(false);

    const blocked = approvalBlockedReason(order, access);
    const supplier = order.supplier_name ?? order.supplier?.name ?? 'the supplier';

    async function approve() {
        setBusy(true);
        try {
            const response = await apiPost(`/purchase/orders/${order.id}/approve`);
            // Approved either way; a failed email is said, not hidden.
            showToast(response.message ?? 'LPO approved.', response.data?.sent_at ? 'success' : 'warn');
            onApproved?.(response.data);
        } catch (err) {
            if (err?.code === 'signature_required') {
                setAskingSignature(true);

                return;
            }
            showToast(err?.message || 'Could not approve the LPO.', 'error');
        } finally {
            setBusy(false);
        }
    }

    return (
        <>
            <button
                type="button"
                onClick={() => setConfirming(true)}
                disabled={!!blocked || busy}
                title={blocked ?? undefined}
                className={className}
                style={{ ...style, ...(blocked ? { opacity: 0.5, cursor: 'not-allowed' } : {}) }}
            >
                {busy ? 'Approving…' : label}
            </button>

            <ConfirmModal
                open={confirming}
                title={`Approve ${order.po_number}?`}
                body={`Your saved signature goes under Approved By, and the LPO is emailed to ${supplier} now. This cannot be undone.`}
                onConfirm={() => { setConfirming(false); approve(); }}
                onCancel={() => setConfirming(false)}
            />

            <SignatureDialog
                open={askingSignature}
                onClose={() => setAskingSignature(false)}
                onSaved={async () => { setAskingSignature(false); await approve(); }}
            />
        </>
    );
}
