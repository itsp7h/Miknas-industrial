import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import PipelineDialogs from './PipelineDialogs';
import SignatureModal from './SignatureModal';
import StageTimeline from './StageTimeline';
import SupplierSelectModal from './SupplierSelectModal';
import ViewSuppliersModal from './ViewSuppliersModal';
import { ToastProvider } from '../../ui/Toast';
import * as client from '../../../api/client';

const STAGES = ['draft', 'gm_approval', 'rfq', 'quoting', 'comparison', 'lpo', 'receiving', 'payment', 'complete'];
const LABELS = {
    draft: 'Purchase Request', gm_approval: 'GM Signature', rfq: 'Select Suppliers',
    quoting: 'Awaiting Quotes', comparison: 'Quote Comparison', lpo: 'LPO Issued',
    receiving: 'Receiving Materials', payment: 'Payment', complete: 'Complete',
};

const base = (overrides = {}) => ({
    id: 3, request_number: 'MPR-0003', stage: 'gm_approval', stage_index: 1, progress_pct: 12,
    is_done: false, stages: STAGES, stage_labels: LABELS, status: 'pending',
    project_name: null, department: null, requested_by_name: 'Admin User', date: null,
    created_at: '2026-09-01', location: null, required_date_text: null, verified_by_name: null,
    signature: null, rfq_invitations: [], pending_invitation_count: 0, sent_invitation_count: 0,
    items: [], supplier_quotes: [], awarded_supplier_names: [], purchase_orders: [],
    permissions: {
        update: true, approve: true, manageRfq: true,
        manageQuotes: true, award: true, generateLpo: true,
    },
    ...overrides,
});

const wrap = (ui) => render(<MemoryRouter><ToastProvider>{ui}</ToastProvider></MemoryRouter>);

const OPTIONS = {
    suppliers: [
        { id: 1, name: 'Gulf Steel', email: 'a@gulf.test', phone: '+97333000000', can_email: true, can_whatsapp: true },
        { id: 2, name: 'No Phone Co', email: 'b@np.test', phone: null, can_email: true, can_whatsapp: false },
        { id: 3, name: 'Already In', email: 'c@ai.test', phone: null, can_email: true, can_whatsapp: false },
    ],
    selected_supplier_ids: [3],
    items: [{ id: 11, description: 'Steel plate' }, { id: 12, description: 'Angle bar' }],
};

describe('pipeline stage actions', () => {
    beforeEach(() => vi.restoreAllMocks());

    // These were `<a href="/purchase/pipeline/3">` hand-offs to the Blade page.
    it('raises an intent instead of leaving the SPA', () => {
        const onAction = vi.fn();
        wrap(<StageTimeline request={base()} onAction={onAction} />);

        // The draft row and the GM row both carry a Sign button; either proves
        // the point, and neither is an anchor any more.
        const signButtons = screen.getAllByRole('button', { name: /Sign/ });
        expect(signButtons.length).toBeGreaterThan(0);
        fireEvent.click(signButtons[0]);
        expect(onAction).toHaveBeenCalledWith('signature');

        fireEvent.click(screen.getByText('🏭 Select Suppliers'));
        expect(onAction).toHaveBeenCalledWith('suppliers');
    });

    it('offers Send only while invitations are unsent', () => {
        const onAction = vi.fn();
        const request = base({ stage: 'rfq', stage_index: 2, pending_invitation_count: 2 });
        wrap(<StageTimeline request={request} onAction={onAction} />);

        fireEvent.click(screen.getByText('📨 Send (2)'));
        expect(onAction).toHaveBeenCalledWith('send');
    });
});

describe('SupplierSelectModal', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue(OPTIONS);
    });

    it('asks which method first, as the Blade modal did', async () => {
        wrap(<SupplierSelectModal open requestId={3} onClose={() => {}} onSubmit={() => {}} />);

        expect(await screen.findByText('Full Order')).toBeInTheDocument();
        expect(screen.getByText('By Item')).toBeInTheDocument();
        // No supplier list until a method is chosen.
        expect(screen.queryByText('Gulf Steel')).not.toBeInTheDocument();
    });

    it('sends the chosen suppliers and their channels', async () => {
        const onSubmit = vi.fn().mockResolvedValue({});
        wrap(<SupplierSelectModal open requestId={3} onClose={() => {}} onSubmit={onSubmit} />);

        fireEvent.click(await screen.findByText('Full Order'));
        fireEvent.click(screen.getByText('Gulf Steel'));
        fireEvent.click(screen.getByText('WhatsApp'));
        fireEvent.click(screen.getByText('Add Suppliers'));

        await waitFor(() => expect(onSubmit).toHaveBeenCalledWith({
            mode: 'global', supplier_ids: [1], channels: { 1: 'whatsapp' },
        }));
    });

    // Sending WhatsApp to a supplier with no phone number would fail at send.
    it('disables a channel the supplier cannot be reached on', async () => {
        wrap(<SupplierSelectModal open requestId={3} onClose={() => {}} onSubmit={() => {}} />);

        fireEvent.click(await screen.findByText('Full Order'));
        fireEvent.click(screen.getByText('No Phone Co'));
        expect(screen.getByTitle(/No Phone Co has no phone number/)).toBeDisabled();
    });

    it('shows an already-invited supplier as locked', async () => {
        wrap(<SupplierSelectModal open requestId={3} onClose={() => {}} onSubmit={() => {}} />);

        fireEvent.click(await screen.findByText('Full Order'));
        expect(screen.getByText('Invited')).toBeInTheDocument();
        const row = screen.getByText('Already In').closest('label');
        expect(row.querySelector('input')).toBeDisabled();
    });

    it('cannot submit with nothing chosen', async () => {
        wrap(<SupplierSelectModal open requestId={3} onClose={() => {}} onSubmit={() => {}} />);

        fireEvent.click(await screen.findByText('Full Order'));
        expect(screen.getByText('Add Suppliers')).toBeDisabled();
    });

    it('by-item mode posts a supplier list per item', async () => {
        const onSubmit = vi.fn().mockResolvedValue({});
        wrap(<SupplierSelectModal open requestId={3} items={OPTIONS.items} onClose={() => {}} onSubmit={onSubmit} />);

        fireEvent.click(await screen.findByText('By Item'));
        // Each item lists every supplier, so pick by position.
        fireEvent.click(screen.getAllByText('Gulf Steel')[0]);
        fireEvent.click(screen.getAllByText('No Phone Co')[1]);
        fireEvent.click(screen.getByText('Add Suppliers'));

        await waitFor(() => expect(onSubmit).toHaveBeenCalledWith({
            mode: 'by_item',
            item_suppliers: { 11: [1], 12: [2] },
            channels: { 1: 'email', 2: 'email' },
        }));
    });

    it('surfaces a server refusal in place', async () => {
        const onSubmit = vi.fn().mockRejectedValue({ errors: { supplier_ids: ['Please choose at least one supplier.'] } });
        wrap(<SupplierSelectModal open requestId={3} onClose={() => {}} onSubmit={onSubmit} />);

        fireEvent.click(await screen.findByText('Full Order'));
        fireEvent.click(screen.getByText('Gulf Steel'));
        fireEvent.click(screen.getByText('Add Suppliers'));

        expect(await screen.findByText('Please choose at least one supplier.')).toBeInTheDocument();
    });
});

describe('ViewSuppliersModal', () => {
    const request = base({
        stage: 'rfq',
        pending_invitation_count: 1,
        rfq_invitations: [
            { id: 9, supplier_id: 1, supplier_name: 'Gulf Steel', channel: 'both', status: 'pending', portal_url: 'http://erp.test/rfq/tok', whatsapp_link: 'https://wa.me/97333?text=x' },
            { id: 10, supplier_id: 2, supplier_name: 'Zenith', channel: 'email', status: 'submitted', portal_url: 'http://erp.test/rfq/tok2', whatsapp_link: null },
        ],
    });

    it('lists each invitation with its channel and status', () => {
        wrap(<ViewSuppliersModal open request={request} onClose={() => {}} onSend={() => {}} />);

        expect(screen.getByText('Gulf Steel')).toBeInTheDocument();
        expect(screen.getByText('Email + WA')).toBeInTheDocument();
        expect(screen.getByText('Unsent')).toBeInTheDocument();
        expect(screen.getByText('Quoted')).toBeInTheDocument();
        expect(screen.getByText('Open in WhatsApp')).toHaveAttribute('href', 'https://wa.me/97333?text=x');
    });

    it('offers to send the unsent ones', async () => {
        const onSend = vi.fn().mockResolvedValue({});
        wrap(<ViewSuppliersModal open request={request} onClose={() => {}} onSend={onSend} />);

        fireEvent.click(screen.getByText('Send 1 invitation(s)'));
        await waitFor(() => expect(onSend).toHaveBeenCalled());
    });
});

describe('SignatureModal', () => {
    it('will not submit until something is drawn', () => {
        wrap(<SignatureModal open request={base()} onClose={() => {}} onSubmit={() => {}} />);

        expect(screen.getByText('Sign here')).toBeInTheDocument();
        expect(screen.getByText('Confirm Signature →')).toBeDisabled();
    });

    it('shows an existing signature read-only, with who signed', () => {
        const request = base({
            signature: { signed_by_name: 'Zoe Admin', signed_at: '2026-09-01', image: 'data:image/png;base64,AAA' },
        });
        wrap(<SignatureModal open request={request} onClose={() => {}} onSubmit={() => {}} />);

        expect(screen.getByAltText('Signature')).toHaveAttribute('src', 'data:image/png;base64,AAA');
        expect(screen.getByText(/Zoe Admin/)).toBeInTheDocument();
        expect(screen.queryByLabelText('Signature pad')).not.toBeInTheDocument();
    });
});

describe('PipelineDialogs', () => {
    beforeEach(() => vi.restoreAllMocks());

    // Issuing an LPO emails suppliers, so it asks first.
    it('confirms before issuing an LPO', async () => {
        const generateLpo = vi.fn().mockResolvedValue({});
        wrap(
            <PipelineDialogs
                open="lpo" onClose={() => {}} request={base({ stage: 'lpo' })}
                actions={{ generateLpo }}
            />
        );

        expect(screen.getByText('Issue the LPO?')).toBeInTheDocument();
        fireEvent.click(screen.getByText('Confirm'));
        await waitFor(() => expect(generateLpo).toHaveBeenCalled());
    });

    it('says re-issue when an LPO already exists', () => {
        const request = base({ stage: 'lpo', purchase_orders: [{ id: 5, po_number: 'PO-00005', supplier_name: 'Gulf', total_amount: '10.000', status: 'sent' }] });
        wrap(<PipelineDialogs open="lpo" onClose={() => {}} request={request} actions={{}} />);

        expect(screen.getByText('Re-issue the LPO?')).toBeInTheDocument();
    });

    it('warns that sending the quote requests cannot be undone', () => {
        const request = base({ stage: 'rfq', pending_invitation_count: 3 });
        wrap(<PipelineDialogs open="send" onClose={() => {}} request={request} actions={{}} />);

        expect(screen.getByText(/3 supplier\(s\) will be sent their quote link/)).toBeInTheDocument();
        expect(screen.getByText(/cannot be unsent/)).toBeInTheDocument();
    });

    it('lists the receivable LPOs and links each to the GRN page', () => {
        const request = base({
            stage: 'receiving',
            purchase_orders: [
                { id: 5, po_number: 'PO-00005', supplier_name: 'Gulf Steel', total_amount: '1200.500', status: 'sent' },
                { id: 6, po_number: 'PO-00006', supplier_name: 'Done Co', total_amount: '10.000', status: 'received' },
            ],
        });
        wrap(<PipelineDialogs open="grn" onClose={() => {}} request={request} actions={{}} />);

        expect(screen.getByText('Gulf Steel').closest('a'))
            .toHaveAttribute('href', '/app/purchase/grns?purchase_order_id=5');
        // A fully received order has nothing left to receive.
        expect(screen.queryByText('Done Co')).not.toBeInTheDocument();
    });
});
