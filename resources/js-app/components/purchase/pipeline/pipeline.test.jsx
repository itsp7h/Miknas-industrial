import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import PipelineHeader from './PipelineHeader';
import PipelineSidebar from './PipelineSidebar';
import StageTimeline from './StageTimeline';

const STAGES = ['draft', 'gm_approval', 'rfq', 'quoting', 'comparison', 'lpo', 'receiving', 'payment', 'complete'];
const LABELS = {
    draft: 'Purchase Request', gm_approval: 'GM Signature', rfq: 'Select Suppliers',
    quoting: 'Awaiting Quotes', comparison: 'Quote Comparison', lpo: 'LPO Issued',
    receiving: 'Receiving Materials', payment: 'Payment', complete: 'Complete',
};

const base = (overrides = {}) => ({
    id: 3,
    request_number: 'MPR-0003',
    stage: 'lpo',
    stage_index: 5,
    progress_pct: 63,
    is_done: false,
    stages: STAGES,
    stage_labels: LABELS,
    status: 'approved',
    project_name: 'Plant Expansion',
    department: 'Operations',
    requested_by_name: 'Admin User',
    date: '2026-09-01',
    created_at: '2026-09-01',
    location: null,
    required_date_text: null,
    verified_by_name: null,
    signature: null,
    rfq_invitations: [],
    pending_invitation_count: 0,
    sent_invitation_count: 0,
    items: [],
    supplier_quotes: [],
    awarded_supplier_names: [],
    purchase_orders: [],
    goods_receipt_notes: [],
    permissions: {
        update: false, approve: false, manageRfq: false,
        manageQuotes: false, award: false, generateLpo: false,
    },
    ...overrides,
});

const renderIn = (ui) => render(<MemoryRouter>{ui}</MemoryRouter>);

describe('PipelineHeader', () => {
    it('shows the request number, stage badge and emoji meta row', () => {
        renderIn(<PipelineHeader request={base()} />);
        expect(screen.getByText('MPR-0003')).toBeInTheDocument();
        expect(screen.getByText('LPO Issued')).toBeInTheDocument();
        expect(screen.getByText('📁 Plant Expansion')).toBeInTheDocument();
        expect(screen.getByText('👤 Admin User')).toBeInTheDocument();
        expect(screen.getByText('📅 01 Sep 2026')).toBeInTheDocument();
    });

    // Amber while in flight, green once complete — the Blade badge/bar colours.
    it('turns the badge green once the request is complete', () => {
        renderIn(<PipelineHeader request={base({ stage: 'complete', is_done: true })} />);
        expect(screen.getByText('Complete')).toHaveStyle({ background: 'rgb(220, 252, 231)' });
    });

    it('keeps the badge amber while still in flight', () => {
        renderIn(<PipelineHeader request={base()} />);
        expect(screen.getByText('LPO Issued')).toHaveStyle({ background: 'rgb(255, 251, 235)' });
    });

    it('offers Edit only when the user may update the request', () => {
        renderIn(<PipelineHeader request={base()} />);
        expect(screen.queryByText('Edit')).not.toBeInTheDocument();

        renderIn(<PipelineHeader request={base({ permissions: { ...base().permissions, update: true } })} />);
        expect(screen.getByText('Edit')).toBeInTheDocument();
    });
});

describe('StageTimeline', () => {
    it('renders every stage label', () => {
        renderIn(<StageTimeline request={base()} />);
        Object.values(LABELS).forEach((label) => {
            expect(screen.getByText(label)).toBeInTheDocument();
        });
    });

    it('colours done, current and future stages differently', () => {
        renderIn(<StageTimeline request={base()} />);
        expect(screen.getByText('Purchase Request')).toHaveStyle({ color: 'rgb(29, 78, 216)' });
        expect(screen.getByText('LPO Issued')).toHaveStyle({ color: 'rgb(217, 119, 6)' });
        expect(screen.getByText('Payment')).toHaveStyle({ color: 'rgb(148, 163, 184)' });
    });

    it('captions completed stages with their counts', () => {
        renderIn(<StageTimeline request={base({
            rfq_invitations: [{ id: 1, supplier_name: 'A', channel: 'email', status: 'sent', whatsapp_link: null }],
            pending_invitation_count: 1,
            sent_invitation_count: 2,
            supplier_quotes: [{ id: 1, supplier_name: 'A', total_amount: '10.000', has_awarded_items: false, awarded_item_count: 0, is_lowest: false }],
        })} />);

        expect(screen.getByText('1 supplier(s) selected · 1 unsent')).toBeInTheDocument();
        expect(screen.getByText('1 quote(s) received · 2 invited')).toBeInTheDocument();
    });

    /**
     * "Awaiting GM signature" and "Select suppliers…" are prompts the Blade page
     * only printed on the *current* stage. A stage already passed with no
     * signature shows nothing — getting this wrong put stale prompts on
     * completed rows.
     */
    it('does not print current-stage prompts on an already-completed stage', () => {
        renderIn(<StageTimeline request={base()} />);
        expect(screen.queryByText('Awaiting GM signature')).not.toBeInTheDocument();
        expect(screen.queryByText('Select suppliers to receive quote requests')).not.toBeInTheDocument();
    });

    it('does print the prompt when that stage is the current one', () => {
        renderIn(<StageTimeline request={base({ stage: 'gm_approval', stage_index: 1 })} />);
        expect(screen.getByText('Awaiting GM signature')).toBeInTheDocument();
    });

    it('shows the signer once a signature exists', () => {
        renderIn(<StageTimeline request={base({
            signature: { signed_by_name: 'The GM', signed_at: '2026-08-30' },
        })} />);
        expect(screen.getByText('Signed by The GM · 30 Aug 2026')).toBeInTheDocument();
    });

    it('offers Issue LPO only with the generateLpo permission', () => {
        renderIn(<StageTimeline request={base()} />);
        expect(screen.queryByText('Issue LPO →')).not.toBeInTheDocument();

        renderIn(<StageTimeline request={base({
            permissions: { ...base().permissions, generateLpo: true },
        })} />);
        expect(screen.getByText('Issue LPO →')).toBeInTheDocument();
    });

    it('replaces Issue LPO with an issued badge once an LPO exists', () => {
        renderIn(<StageTimeline request={base({
            permissions: { ...base().permissions, generateLpo: true },
            purchase_orders: [{ id: 9, po_number: 'PO-00009', supplier_name: 'A', total_amount: '5.000', status: 'sent' }],
        })} />);
        expect(screen.getByText('✓ LPO(s) Issued')).toBeInTheDocument();
        expect(screen.queryByText('Issue LPO →')).not.toBeInTheDocument();
    });

    it('links a single issued LPO at the React order page', () => {
        renderIn(<StageTimeline request={base({
            stage: 'receiving', stage_index: 6,
            purchase_orders: [{ id: 9, po_number: 'PO-00009', supplier_name: 'A', total_amount: '5.000', status: 'sent' }],
        })} />);
        expect(screen.getByText('View LPO').closest('a')).toHaveAttribute('href', '/app/purchase/orders/9');
    });

    /**
     * Re-issuing cancels the stale LPO and creates a new one, so the request
     * carries both. The timeline rendered a download button per order, which on
     * a re-issued request meant two buttons reading the same supplier name —
     * one of them for an LPO that no longer stands.
     */
    it('offers only the LPO that still stands after a re-issue', () => {
        renderIn(<StageTimeline request={base({
            stage: 'receiving', stage_index: 6,
            purchase_orders: [
                { id: 29, po_number: 'PO-00029', supplier_name: 'Yousif Dhneem', total_amount: '5.000', status: 'cancelled' },
                { id: 30, po_number: 'PO-00030', supplier_name: 'Yousif Dhneem', total_amount: '5.000', status: 'sent' },
            ],
        })} />);

        // One live order, so the single-order branch: View LPO + one PDF link.
        expect(screen.getByText('View LPO').closest('a')).toHaveAttribute('href', '/app/purchase/orders/30');
        expect(screen.getAllByText('⬇ Download PDF')).toHaveLength(1);
        expect(screen.queryByText(/PO-00029/)).not.toBeInTheDocument();
    });

    // A request genuinely split across two suppliers still lists both, and now
    // names each by its order number as well.
    it('names each LPO by supplier and number when a request is split', () => {
        renderIn(<StageTimeline request={base({
            stage: 'receiving', stage_index: 6,
            purchase_orders: [
                { id: 26, po_number: 'PO-00026', supplier_name: 'Ali Hussain', total_amount: '5.000', status: 'sent' },
                { id: 27, po_number: 'PO-00027', supplier_name: 'Nelson Desuza', total_amount: '5.000', status: 'sent' },
            ],
        })} />);

        expect(screen.getByText('⬇ Ali Hussain (PO-00026)')).toBeInTheDocument();
        expect(screen.getByText('⬇ Nelson Desuza (PO-00027)')).toBeInTheDocument();
    });

    /**
     * The Receiving step used to show a bare "Record GRN" button and nothing
     * else, so a recorded GRN left the screen identical — and a draft GRN has
     * raised no stock, which the step never said.
     */
    it('reports what has been received and offers to confirm a draft GRN', () => {
        renderIn(<StageTimeline request={base({
            stage: 'receiving', stage_index: 6,
            goods_receipt_notes: [
                { id: 1, grn_number: 'GRN-00001', po_number: 'PO-00030', status: 'draft' },
            ],
        })} />);

        expect(screen.getByText('1 recorded, not yet confirmed')).toBeInTheDocument();
        expect(screen.getByText('⚠ Confirm GRN-00001').closest('a'))
            .toHaveAttribute('href', '/app/purchase/grns/1');
        // Still recordable — a partial delivery takes more than one GRN.
        expect(screen.getByText('Record GRN →')).toBeInTheDocument();
    });

    it('says so plainly while nothing has been received', () => {
        renderIn(<StageTimeline request={base({ stage: 'receiving', stage_index: 6 })} />);

        expect(screen.getByText('Nothing received yet')).toBeInTheDocument();
        expect(screen.queryByText(/Confirm GRN/)).not.toBeInTheDocument();
    });

    it('links each GRN once the step is behind the request', () => {
        renderIn(<StageTimeline request={base({
            stage: 'payment', stage_index: 7,
            goods_receipt_notes: [
                { id: 1, grn_number: 'GRN-00001', po_number: 'PO-00030', status: 'confirmed' },
            ],
        })} />);

        expect(screen.getByText('GRN-00001').closest('a'))
            .toHaveAttribute('href', '/app/purchase/grns/1');
        expect(screen.getByText('1 GRN(s) received into stock')).toBeInTheDocument();
    });

    it('shows no issued badge when every LPO on the request was cancelled', () => {
        renderIn(<StageTimeline request={base({
            permissions: { ...base().permissions, generateLpo: true },
            purchase_orders: [{ id: 29, po_number: 'PO-00029', supplier_name: 'A', total_amount: '5.000', status: 'cancelled' }],
        })} />);

        expect(screen.queryByText('✓ LPO(s) Issued')).not.toBeInTheDocument();
        expect(screen.getByText('Issue LPO →')).toBeInTheDocument();
    });
});

describe('PipelineSidebar', () => {
    const rich = base({
        location: 'Sitra',
        rfq_invitations: [
            { id: 1, supplier_name: 'Gulf', channel: 'email', status: 'submitted', whatsapp_link: null },
            { id: 2, supplier_name: 'Zen', channel: 'whatsapp', status: 'pending', whatsapp_link: 'https://wa.me/123' },
        ],
        items: [
            { id: 1, description: 'Steel Plate', quote_count: 2, quote_supplier_names: ['Gulf', 'Zen'], is_awarded: true },
            { id: 2, description: 'Bolt', quote_count: 0, quote_supplier_names: [], is_awarded: false },
        ],
        supplier_quotes: [
            { id: 1, supplier_name: 'Gulf', total_amount: '3100.500', has_awarded_items: true, awarded_item_count: 1, is_lowest: false },
            { id: 2, supplier_name: 'Zen', total_amount: '3450.000', has_awarded_items: false, awarded_item_count: 0, is_lowest: false },
        ],
        purchase_orders: [
            { id: 3, po_number: 'PO-00003', supplier_name: 'Al Rawabi', total_amount: '858.000', status: 'received' },
        ],
    });

    it('always shows Request Details with a capitalised status', () => {
        renderIn(<PipelineSidebar request={rich} />);
        expect(screen.getByText('Request Details')).toBeInTheDocument();
        expect(screen.getByText('Approved')).toBeInTheDocument();
        expect(screen.getByText('Sitra')).toBeInTheDocument();
    });

    it('names who raised the request, and labels the urgency as a date not a person', () => {
        renderIn(<PipelineSidebar request={base({
            requested_by_name: 'Operation manager',
            location: 'Askar Forkoll',
            required_date_text: 'Urgent',
        })} />);

        // Was missing entirely, so the box named nobody who raised it.
        expect(screen.getByText('Requested By')).toBeInTheDocument();
        expect(screen.getByText('Operation manager')).toBeInTheDocument();

        // The urgency picker fills this, so "Required By: Urgent" read as a
        // name. It holds an urgency, and says so.
        expect(screen.getByText('Required Urgency')).toBeInTheDocument();
        expect(screen.getByText('Urgent')).toBeInTheDocument();
        expect(screen.queryByText('Required By')).not.toBeInTheDocument();
    });

    it('calls it a Required Date, formatted, when a specific date was picked', () => {
        renderIn(<PipelineSidebar request={base({ required_date_text: '2026-10-02' })} />);

        // The same column holds both answers, so the row follows the value
        // rather than carrying one fixed label that is wrong half the time.
        expect(screen.getByText('Required Date')).toBeInTheDocument();
        expect(screen.getByText('02 Oct 2026')).toBeInTheDocument();
        expect(screen.queryByText('Required Urgency')).not.toBeInTheDocument();
        expect(screen.queryByText('2026-10-02')).not.toBeInTheDocument();
    });

    it('shows supplier status pills and the channel for non-email invitations', () => {
        renderIn(<PipelineSidebar request={rich} />);
        expect(screen.getByText('Suppliers (2)')).toBeInTheDocument();
        expect(screen.getByText('Submitted')).toBeInTheDocument();
        expect(screen.getByText('Pending')).toBeInTheDocument();
        expect(screen.getByText('Whatsapp')).toBeInTheDocument();
    });

    it('offers the WhatsApp shortcut only where the API supplied a link', () => {
        renderIn(<PipelineSidebar request={rich} />);
        const wa = screen.getAllByText('WA');
        expect(wa).toHaveLength(1);
        expect(wa[0].closest('a')).toHaveAttribute('href', 'https://wa.me/123');
    });

    it('badges an awarded item and counts quotes on the rest', () => {
        renderIn(<PipelineSidebar request={rich} />);
        expect(screen.getByText('✓ Awarded')).toBeInTheDocument();
        expect(screen.getByText('0 quotes')).toBeInTheDocument();
    });

    it('prints quote and LPO money to three decimals with a BD prefix', () => {
        renderIn(<PipelineSidebar request={rich} />);
        expect(screen.getByText('BD 3,100.500')).toBeInTheDocument();
        expect(screen.getByText('BD 858.000')).toBeInTheDocument();
    });

    it('links each LPO at the React order page', () => {
        renderIn(<PipelineSidebar request={rich} />);
        expect(screen.getByText('PO-00003').closest('a')).toHaveAttribute('href', '/app/purchase/orders/3');
    });

    // The Blade sidebar rendered these cards only when there was data.
    it('omits the supplier, item, quote and LPO cards when there is nothing to show', () => {
        renderIn(<PipelineSidebar request={base()} />);
        expect(screen.getByText('Request Details')).toBeInTheDocument();
        ['Suppliers (0)', 'Items (0)', 'Quotes (0)', 'LPOs (0)'].forEach((heading) => {
            expect(screen.queryByText(heading)).not.toBeInTheDocument();
        });
    });
});
