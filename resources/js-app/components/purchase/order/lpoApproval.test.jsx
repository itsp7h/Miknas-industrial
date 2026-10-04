import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import ApproveLpoButton from './ApproveLpoButton';
import LpoDeliveryStatus from './LpoDeliveryStatus';
import StageTimeline from '../pipeline/StageTimeline';
import { AccessProvider } from '../../../layouts/AccessContext';
import { ToastProvider } from '../../ui/Toast';
import * as client from '../../../api/client';

const ORDER = {
    id: 7,
    po_number: 'PO-00007',
    status: 'draft',
    sent_at: null,
    sent_to: null,
    awaiting_approval: true,
    prepared_by_id: 1,
    created_by_name: 'Buyer One',
    supplier_name: 'Gulf Steel',
    supplier: { id: 1, name: 'Gulf Steel', email: 'sales@gulfsteel.test' },
};

const withAccess = (ui, { permissions = ['pipeline.approve-lpo'], userId = 2 } = {}) => render(
    <MemoryRouter>
        <ToastProvider>
            <AccessProvider permissions={permissions} userId={userId}>{ui}</AccessProvider>
        </ToastProvider>
    </MemoryRouter>,
);

describe('ApproveLpoButton', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
    });

    it('is disabled with the reason for someone without the permission', () => {
        withAccess(<ApproveLpoButton order={ORDER} />, { permissions: [] });
        const button = screen.getByText('✍ Approve & Sign');
        expect(button).toBeDisabled();
        expect(button).toHaveAttribute('title', 'You do not have permission to approve LPOs');
    });

    /** Two signatures from one hand are one signature. */
    it('is disabled for the person who prepared the LPO', () => {
        withAccess(<ApproveLpoButton order={ORDER} />, { userId: 1 });
        expect(screen.getByText('✍ Approve & Sign')).toHaveAttribute(
            'title', 'You prepared this LPO, so someone else must approve it',
        );
    });

    it('asks first, then approves and hands the order back', async () => {
        const onApproved = vi.fn();
        const updated = { ...ORDER, awaiting_approval: false, sent_at: '2026-10-04T08:00:00+00:00' };
        vi.spyOn(client, 'apiPost').mockResolvedValue({ data: updated, message: 'PO-00007 approved and emailed to sales@gulfsteel.test.' });

        withAccess(<ApproveLpoButton order={ORDER} onApproved={onApproved} />);
        fireEvent.click(screen.getByText('✍ Approve & Sign'));
        expect(client.apiPost).not.toHaveBeenCalled();
        expect(screen.getByText(/emailed to Gulf Steel now/)).toBeInTheDocument();

        fireEvent.click(screen.getByText('Confirm'));

        await waitFor(() => expect(onApproved).toHaveBeenCalledWith(updated));
        expect(client.apiPost).toHaveBeenCalledWith('/purchase/orders/7/approve');
        expect(await screen.findByText('PO-00007 approved and emailed to sales@gulfsteel.test.')).toBeInTheDocument();
    });

    it('asks for a signature when the approver has none saved', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({ code: 'signature_required', message: 'Add your signature' });

        withAccess(<ApproveLpoButton order={ORDER} />);
        fireEvent.click(screen.getByText('✍ Approve & Sign'));
        fireEvent.click(screen.getByText('Confirm'));

        expect(await screen.findByText('Add your signature')).toBeInTheDocument();
    });
});

describe('LpoDeliveryStatus awaiting approval', () => {
    it('says the LPO is not sent and offers Approve & Sign instead of Send', () => {
        withAccess(<LpoDeliveryStatus order={ORDER} />);
        expect(screen.getByText('⏳ Awaiting approval — not sent to the supplier')).toBeInTheDocument();
        expect(screen.getByText(/Prepared by Buyer One/)).toBeInTheDocument();
        expect(screen.getByText('✍ Approve & Sign')).toBeInTheDocument();
        expect(screen.queryByText('✉ Send to supplier')).not.toBeInTheDocument();
    });

    it('names the approver once it has gone out', () => {
        withAccess(<LpoDeliveryStatus order={{
            ...ORDER, awaiting_approval: false, status: 'sent',
            sent_at: '2026-10-04T08:00:00+00:00', sent_to: 'sales@gulfsteel.test',
            approved_by_name: 'GM Person', approved_at: '2026-10-04T08:00:00+00:00',
        }} />);
        expect(screen.getByText(/Approved by GM Person/)).toBeInTheDocument();
    });
});

describe('StageTimeline at the LPO stage', () => {
    const request = {
        id: 3, request_number: 'MPR-0003', stage: 'lpo', stage_index: 5,
        stages: ['draft', 'gm_approval', 'rfq', 'quoting', 'comparison', 'lpo', 'receiving', 'complete'],
        stage_labels: {
            draft: 'Purchase Request', gm_approval: 'GM Signature', rfq: 'Select Suppliers',
            quoting: 'Awaiting Quotes', comparison: 'Quote Comparison', lpo: 'LPO Issued',
            receiving: 'Receiving Materials', complete: 'Complete',
        },
        requested_by_name: 'Admin', created_at: '2026-09-01', signature: null,
        rfq_invitations: [], pending_invitation_count: 0, sent_invitation_count: 0,
        items: [], supplier_quotes: [], awarded_supplier_names: ['Gulf Steel'],
        purchase_orders: [ORDER], goods_receipt_notes: [],
        permissions: { approve: false, manageRfq: false, manageQuotes: false, award: false, generateLpo: true },
    };

    it('offers Approve & Sign for an issued LPO that waits for it', () => {
        withAccess(<StageTimeline request={request} />);
        expect(screen.getByText('✍ Approve & Sign')).toBeEnabled();
        expect(screen.getByText('Awarded to Gulf Steel · 1 LPO(s) awaiting approval')).toBeInTheDocument();
        expect(screen.getByText('View LPO').closest('a')).toHaveAttribute('href', '/app/purchase/orders/7');
        expect(screen.queryByText('✓ LPO(s) Issued')).not.toBeInTheDocument();
    });
});
