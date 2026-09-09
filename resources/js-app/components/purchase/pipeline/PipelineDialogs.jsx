import { useState } from 'react';
import ConfirmModal from '../../ui/ConfirmModal';
import RecordGrnModal from './RecordGrnModal';
import SignatureModal from './SignatureModal';
import SupplierSelectModal from './SupplierSelectModal';
import ViewSuppliersModal from './ViewSuppliersModal';
import { useToast } from '../../ui/Toast';

/**
 * The five actions the Blade detail page carried as modals and bare POSTs,
 * gathered in one place so both viewports mount them identically and the
 * timeline only has to name the one it wants.
 */
export default function PipelineDialogs({ open, onClose, request, actions }) {
    const [sendingInvites, setSendingInvites] = useState(false);
    const { showToast } = useToast();

    async function confirmLpo() {
        onClose();
        try {
            await actions.generateLpo();
        } catch (err) {
            showToast(err?.message || 'Could not issue the LPO.', 'error');
        }
    }

    async function sendInvitations() {
        setSendingInvites(true);
        try {
            await actions.sendInvitations();
            onClose();
        } catch (err) {
            showToast(err?.message || 'Could not send the invitations.', 'error');
        } finally {
            setSendingInvites(false);
        }
    }

    return (
        <>
            <SupplierSelectModal
                open={open === 'suppliers'}
                requestId={request.id}
                items={request.items}
                onClose={onClose}
                onSubmit={actions.selectSuppliers}
            />

            <ViewSuppliersModal
                open={open === 'view-suppliers'}
                request={request}
                onClose={onClose}
                onSend={request.permissions.manageRfq ? actions.sendInvitations : null}
            />

            <SignatureModal
                open={open === 'signature'}
                request={request}
                onClose={onClose}
                onSubmit={actions.saveSignature}
                onReject={actions.rejectRequest}
            />

            <RecordGrnModal open={open === 'grn'} request={request} onClose={onClose} />

            {/* Sending is irreversible per invitation — it emails and messages
                real suppliers — so it asks first. */}
            <ConfirmModal
                open={open === 'send'}
                title="Send the quote requests?"
                body={`${request.pending_invitation_count} supplier(s) will be sent their quote link now, by email or WhatsApp as chosen. This cannot be unsent.`}
                onConfirm={sendingInvites ? () => {} : sendInvitations}
                onCancel={onClose}
            />

            <ConfirmModal
                open={open === 'lpo'}
                title={request.purchase_orders.length ? 'Re-issue the LPO?' : 'Issue the LPO?'}
                body={request.purchase_orders.length
                    ? 'A new LPO will be generated from the current awards and sent to the supplier(s). The existing one stays on record.'
                    : 'An LPO will be generated from the awarded items and sent to each awarded supplier.'}
                onConfirm={confirmLpo}
                onCancel={onClose}
            />
        </>
    );
}
