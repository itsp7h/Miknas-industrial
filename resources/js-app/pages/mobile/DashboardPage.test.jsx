import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import DashboardPage from './DashboardPage';
import { AccessProvider } from '../../layouts/AccessContext';
import { ToastProvider } from '../../components/ui/Toast';
import * as client from '../../api/client';

vi.mock('../../echo', () => ({
    echo: {
        private: () => ({ listen: () => {}, stopListening: () => {} }),
        channel: () => ({ listen: () => {} }),
        leave: () => {},
    },
}));

const SUMMARY = { inventory_value: 3580, purchase_pending: 3 };

const OVERVIEW = {
    pipeline: {
        active: 13,
        completed: 5,
        stages: [
            { key: 'draft', count: 0 },
            { key: 'gm_approval', count: 1 },
            { key: 'quoting', count: 3 },
            { key: 'receiving', count: 9 },
        ],
    },
    actions: [
        { kind: 'gm_signature', count: 1, request_id: 4, reference: 'MI-MPR-26-0004', detail: 'Forkoll Department' },
        { kind: 'draft_grns', count: 3, grn_id: 18, reference: 'GRN-00018', missing: ['LPO', 'GRN', 'Tax Invoice'] },
        { kind: 'unpaid_invoices', count: 4, outstanding: 1827 },
    ],
    low_stock: 0,
    counts: {},
};

function stubApi(overview = OVERVIEW) {
    vi.spyOn(client, 'apiGet').mockImplementation((path) => Promise.resolve(
        path === '/dashboard/overview' ? overview : SUMMARY,
    ));
}

const renderPage = ({ isAdmin = true, permissions = [] } = {}) =>
    render(
        <ToastProvider>
            <AccessProvider isAdmin={isAdmin} permissions={permissions}>
                <MemoryRouter><DashboardPage currentUserId={1} userName="Admin User" /></MemoryRouter>
            </AccessProvider>
        </ToastProvider>
    );

describe('mobile DashboardPage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        stubApi();
    });

    it('greets the person by first name', () => {
        renderPage();
        expect(screen.getByRole('heading', { name: 'Welcome back, Admin' })).toBeInTheDocument();
    });

    it('shows inventory value and the pipeline as active and completed', async () => {
        renderPage();
        expect(await screen.findByText('BD 3,580.000')).toBeInTheDocument();
        expect(await screen.findByText('13 active')).toBeInTheDocument();
        expect(screen.getByText('5 completed')).toBeInTheDocument();
    });

    it('splits the pipeline by stage, leaving out empty stages', async () => {
        renderPage();
        expect(await screen.findByText('GM approval 1')).toBeInTheDocument();
        expect(screen.getByText('Quoting 3')).toBeInTheDocument();
        expect(screen.queryByText(/Draft/)).not.toBeInTheDocument();
    });

    it('lists what is waiting on this person', async () => {
        renderPage();
        expect(await screen.findByText('GM signature needed')).toBeInTheDocument();
        expect(screen.getByText('MI-MPR-26-0004 · Forkoll Department').closest('a'))
            .toHaveAttribute('href', '/app/purchase/pipeline/4');
        expect(screen.getByText('3 draft GRNs to confirm')).toBeInTheDocument();
        expect(screen.getByText('GRN-00018 is missing LPO, GRN & Tax Invoice')).toBeInTheDocument();
        expect(screen.getByText('4 unpaid supplier invoices')).toBeInTheDocument();
        expect(screen.getByText('BD 1,827.000 outstanding')).toBeInTheDocument();
        expect(screen.getByText('All items are above minimum stock levels.')).toBeInTheDocument();
    });

    it('leaves out the stage card when this person cannot see the pipeline', async () => {
        stubApi({ ...OVERVIEW, pipeline: null, actions: [] });
        renderPage();
        await screen.findByText('BD 3,580.000');
        expect(screen.queryByText('Requests by stage')).not.toBeInTheDocument();
    });
});

describe('mobile DashboardPage quick actions', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        stubApi();
    });

    it('links each action to where it starts', () => {
        renderPage();
        expect(screen.getByText('New request').closest('a')).toHaveAttribute('href', '/app/purchase/pipeline?new=1');
        expect(screen.getByText('Receive goods').closest('a')).toHaveAttribute('href', '/app/purchase/grns?new=1');
        expect(screen.getByText('Adjust stock').closest('a')).toHaveAttribute('href', '/app/inventory/movements?new=1');
        expect(screen.getByText('New LPO').closest('a')).toHaveAttribute('href', '/app/purchase/orders?new=1');
    });

    // CLAUDE.md #14: disabled with the reason, not hidden.
    it('shows an action disabled, not linked, without its permission', () => {
        renderPage({ isAdmin: false, permissions: ['goods-receipts.create'] });

        const newRequest = screen.getByText('New request').closest('[aria-disabled="true"]');
        expect(newRequest).toHaveAttribute('title', 'You do not have permission to create purchase requests');
        expect(screen.getByText('New request').closest('a')).toBeNull();
        expect(screen.getByText('Receive goods').closest('a')).toHaveAttribute('href', '/app/purchase/grns?new=1');
    });
});
