import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import PipelinePage from './PipelinePage';
import { ToastProvider } from '../../../components/ui/Toast';
import { PageTitleProvider } from '../../../layouts/PageTitleContext';
import * as client from '../../../api/client';

let stageHandler;
vi.mock('../../../echo', () => ({
    echo: {
        private: () => ({
            listen: (event, handler) => { stageHandler = handler; },
            stopListening: () => {},
        }),
        leave: () => {},
    },
}));

const DETAIL = {
    id: 3,
    request_number: 'MPR-0003',
    stage: 'lpo',
    stage_index: 5,
    progress_pct: 63,
    is_done: false,
    stages: ['draft', 'gm_approval', 'rfq', 'quoting', 'comparison', 'lpo', 'receiving', 'payment', 'complete'],
    stage_labels: {
        draft: 'Purchase Request', gm_approval: 'GM Signature', rfq: 'Select Suppliers',
        quoting: 'Awaiting Quotes', comparison: 'Quote Comparison', lpo: 'LPO Issued',
        receiving: 'Receiving Materials', payment: 'Payment', complete: 'Complete',
    },
    status: 'approved',
    project_name: 'Plant Expansion',
    department: 'Operations',
    requested_by_name: 'Admin User',
    date: '2026-09-01',
    created_at: '2026-09-01',
    signature: null,
    rfq_invitations: [],
    pending_invitation_count: 0,
    sent_invitation_count: 0,
    items: [],
    supplier_quotes: [],
    awarded_supplier_names: [],
    purchase_orders: [],
    permissions: { update: false, approve: false, manageRfq: false, manageQuotes: false, award: false, generateLpo: false },
};

const renderPage = () =>
    render(
        <MemoryRouter initialEntries={['/app/purchase/pipeline/3']}>
            <ToastProvider>
                <PageTitleProvider>
                    <Routes>
                        <Route path="/app/purchase/pipeline/:id" element={<PipelinePage />} />
                    </Routes>
                </PageTitleProvider>
            </ToastProvider>
        </MemoryRouter>
    );

describe('desktop PipelinePage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        stageHandler = undefined;
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: DETAIL });
    });

    it('fetches the request by its route id', async () => {
        renderPage();
        await waitFor(() => expect(client.apiGet).toHaveBeenCalledWith('/purchase/pipeline/3'));
    });

    it('renders the header, timeline and sidebar together', async () => {
        renderPage();
        expect(await screen.findByText('MPR-0003')).toBeInTheDocument();
        expect(screen.getByText('Pipeline Stages')).toBeInTheDocument();
        expect(screen.getByText('Request Details')).toBeInTheDocument();
    });

    it('links back to the board', async () => {
        renderPage();
        await screen.findByText('MPR-0003');
        expect(screen.getByText('Purchase Pipeline').closest('a'))
            .toHaveAttribute('href', '/app/purchase/pipeline');
    });

    /**
     * The stage-changed broadcast carries only {id, request_number, stage}, so
     * the page refetches rather than merging a partial payload into a full
     * detail object.
     */
    it('refetches when this request\'s stage changes', async () => {
        renderPage();
        await waitFor(() => expect(client.apiGet).toHaveBeenCalledTimes(1));

        stageHandler({ id: 3, request_number: 'MPR-0003', stage: 'receiving' });
        await waitFor(() => expect(client.apiGet).toHaveBeenCalledTimes(2));
    });

    it('ignores a stage change for a different request', async () => {
        renderPage();
        await waitFor(() => expect(client.apiGet).toHaveBeenCalledTimes(1));

        stageHandler({ id: 99, request_number: 'MPR-0099', stage: 'receiving' });
        await new Promise((resolve) => setTimeout(resolve, 20));
        expect(client.apiGet).toHaveBeenCalledTimes(1);
    });

    it('says so when the request cannot be loaded', async () => {
        vi.spyOn(client, 'apiGet').mockRejectedValue({ message: 'nope' });
        renderPage();
        expect(await screen.findByText('That purchase request could not be found.')).toBeInTheDocument();
    });
});
