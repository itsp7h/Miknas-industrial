import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { ToastProvider } from '../../../components/ui/Toast';
import { RequestModalProvider } from '../../../components/purchase/requests/RequestModalProvider';
import PipelineBoardPage from './PipelineBoardPage';
import * as client from '../../../api/client';

let handlers = {};

vi.mock('../../../echo', () => ({
    echo: {
        private: () => ({
            listen: (event, handler) => { handlers[event] = handler; },
            stopListening: () => {},
        }),
    },
}));

describe('PipelineBoardPage (desktop)', () => {
    it('gives the company and the project a column each', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{
                id: 1, request_number: 'MPR26-0001', stage: 'draft',
                company_name: 'Miknas Industrial', project_name: 'Forkoll',
                requested_by_name: 'Jane', department: 'Ops', date: '2026-08-01',
            }],
        });
        render(<MemoryRouter><ToastProvider><RequestModalProvider><PipelineBoardPage /></RequestModalProvider></ToastProvider></MemoryRouter>);

        // A request belongs to both, and one column standing in for the other
        // is what the single field got wrong.
        expect(await screen.findByText('MPR26-0001')).toBeInTheDocument();
        // Order matters: Project reads as a detail of the department's work,
        // so it sits after it rather than between Company and Department.
        expect(screen.getAllByRole('columnheader').map((th) => th.textContent))
            .toEqual(['Request #', 'Company', 'Department', 'Project', 'Requested By', 'Stage', 'Date', '']);
        expect(screen.getByText('Miknas Industrial')).toBeInTheDocument();
        expect(screen.getByText('Forkoll')).toBeInTheDocument();
    });

    it('splits requests into Active and Completed tabs', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [
                { id: 1, request_number: 'MPR26-0001', stage: 'draft', company_name: 'A', requested_by_name: 'Jane', department: 'Ops', date: '2026-08-01' },
                { id: 2, request_number: 'MPR26-0002', stage: 'complete', company_name: 'B', requested_by_name: 'Sam', department: 'Ops', date: '2026-07-01' },
            ],
        });

        render(<MemoryRouter><ToastProvider><RequestModalProvider><PipelineBoardPage /></RequestModalProvider></ToastProvider></MemoryRouter>);

        await waitFor(() => expect(screen.getByText('MPR26-0001')).toBeInTheDocument());
        expect(screen.queryByText('MPR26-0002')).not.toBeInTheDocument();

        fireEvent.click(screen.getByText(/Completed/));
        expect(screen.getByText('MPR26-0002')).toBeInTheDocument();
    });

    it("updates a request's stage live without a refetch, preserving the row's other fields", async () => {
        handlers = {};
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 1, request_number: 'MPR26-0001', stage: 'draft', company_name: 'A', requested_by_name: 'Jane', department: 'Ops', date: '2026-08-01' }],
        });

        render(<MemoryRouter><ToastProvider><RequestModalProvider><PipelineBoardPage /></RequestModalProvider></ToastProvider></MemoryRouter>);
        await waitFor(() => expect(screen.getByText('MPR26-0001')).toBeInTheDocument());
        expect(screen.getByText(/Draft/i)).toBeInTheDocument();

        // .purchase-request.stage-changed only carries {id, request_number, stage} —
        // firing it must patch the existing row's stage without blanking company_name etc.
        act(() => {
            handlers['.purchase-request.stage-changed']({ id: 1, request_number: 'MPR26-0001', stage: 'complete' });
        });

        fireEvent.click(screen.getByText(/Completed/));
        await waitFor(() => expect(screen.getByText('MPR26-0001')).toBeInTheDocument());
        expect(screen.getByText('Complete')).toBeInTheDocument();
        expect(screen.getByText('A')).toBeInTheDocument();
        expect(screen.getByText('Jane')).toBeInTheDocument();
    });

    it('links each row at the React detail route, not the old Blade page', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 7, request_number: 'MPR26-0007', stage: 'draft', company_name: 'A', requested_by_name: 'Jane', department: 'Ops', date: '2026-08-01' }],
        });

        render(<MemoryRouter><ToastProvider><RequestModalProvider><PipelineBoardPage /></RequestModalProvider></ToastProvider></MemoryRouter>);
        await waitFor(() => expect(screen.getByText('MPR26-0007')).toBeInTheDocument());

        expect(screen.getByText('View').closest('a')).toHaveAttribute('href', '/app/purchase/pipeline/7');
    });

    // Blade badged this action as a button; the React sweep left it a bare text
    // link, alone among the purchase list pages (CLAUDE.md #12).
    it('draws the row action as a button, the way every other list page does', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 7, request_number: 'MPR26-0007', stage: 'draft', company_name: 'A', requested_by_name: 'Jane', department: 'Ops', date: '2026-08-01' }],
        });

        render(<MemoryRouter><ToastProvider><RequestModalProvider><PipelineBoardPage /></RequestModalProvider></ToastProvider></MemoryRouter>);
        await waitFor(() => expect(screen.getByText('MPR26-0007')).toBeInTheDocument());

        expect(screen.getByText('View')).toHaveClass('btn-primary', 'btn-sm');
    });

    // The board used to learn about its own new request only from the broadcast,
    // so with Reverb stopped — every local setup, and any dropped socket — a
    // submitted MPR did not appear until the page was reloaded. store() answers
    // with a board row precisely so the board need not wait for it.
    it('shows a newly created request from the save response, with no broadcast', async () => {
        handlers = {};
        vi.spyOn(client, 'apiGet').mockImplementation((url) => (
            url === '/purchase/pipeline'
                ? Promise.resolve({ data: [{ id: 7, request_number: 'MPR26-0007', stage: 'draft', company_name: 'A', requested_by_name: 'Jane', department: 'Ops', date: '2026-08-01' }] })
                : Promise.resolve({ projects: [], departments: [], units: ['PCS'], today: '2026-09-14' })
        ));
        vi.spyOn(client, 'apiPost').mockResolvedValue({
            message: 'MPR26-0013 submitted successfully.',
            data: {
                id: 13, request_number: 'MPR26-0013', stage: 'draft', company_name: 'A',
                requested_by_name: 'Jane', department: 'Ops', date: '2026-09-14', requested_by_id: 1,
            },
        });

        render(<MemoryRouter><ToastProvider><RequestModalProvider>
            <PipelineBoardPage currentUserId={1} canViewAllPurchaseRequests />
        </RequestModalProvider></ToastProvider></MemoryRouter>);
        await waitFor(() => expect(client.apiGet).toHaveBeenCalled());

        fireEvent.click(screen.getByText('+ New Request'));
        await screen.findByText('New Purchase Request');

        fireEvent.change(screen.getByLabelText('Item 1 description'), { target: { value: 'Steel bar' } });
        fireEvent.submit(screen.getByLabelText('Item 1 description').closest('form'));

        // No handler is fired: nothing reaches the board except the POST's answer.
        await waitFor(() => expect(screen.getByText('MPR26-0013')).toBeInTheDocument());

        // The endpoint sorts newest-first, so the new row belongs above the
        // one already on the board, not under it.
        const numbers = screen.getAllByText(/^MPR26-\d+$/).map((el) => el.textContent);
        expect(numbers).toEqual(['MPR26-0013', 'MPR26-0007']);
    });

    it('puts a broadcast request on top of the board too', async () => {
        handlers = {};
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 7, request_number: 'MPR26-0007', stage: 'draft', company_name: 'A', requested_by_name: 'Jane', department: 'Ops', date: '2026-08-01' }],
        });

        render(<MemoryRouter><ToastProvider><RequestModalProvider>
            <PipelineBoardPage currentUserId={1} canViewAllPurchaseRequests />
        </RequestModalProvider></ToastProvider></MemoryRouter>);
        await waitFor(() => expect(screen.getByText('MPR26-0007')).toBeInTheDocument());

        act(() => {
            handlers['.purchase-request.created']({
                id: 14, request_number: 'MPR26-0014', stage: 'draft', requested_by_id: 1,
            });
        });

        const numbers = screen.getAllByText(/^MPR26-\d+$/).map((el) => el.textContent);
        expect(numbers).toEqual(['MPR26-0014', 'MPR26-0007']);
    });

    it('a view-own user ignores a created event for someone else\'s request', async () => {
        handlers = {};
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });

        render(<MemoryRouter><ToastProvider><RequestModalProvider>
            <PipelineBoardPage currentUserId={1} canViewOwnPurchaseRequests />
        </RequestModalProvider></ToastProvider></MemoryRouter>);
        await waitFor(() => expect(client.apiGet).toHaveBeenCalled());

        act(() => {
            handlers['.purchase-request.created']({
                id: 9, request_number: 'MPR26-0009', stage: 'draft', requested_by_id: 2,
            });
        });

        expect(screen.queryByText('MPR26-0009')).not.toBeInTheDocument();
    });

    it('a view-own user accepts a created event for their own request', async () => {
        handlers = {};
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });

        render(<MemoryRouter><ToastProvider><RequestModalProvider>
            <PipelineBoardPage currentUserId={1} canViewOwnPurchaseRequests />
        </RequestModalProvider></ToastProvider></MemoryRouter>);
        await waitFor(() => expect(client.apiGet).toHaveBeenCalled());

        act(() => {
            handlers['.purchase-request.created']({
                id: 9, request_number: 'MPR26-0009', stage: 'draft', requested_by_id: 1,
            });
        });

        expect(screen.getByText('MPR26-0009')).toBeInTheDocument();
    });

    it('a view-active-pipeline user ignores a created event for a draft-stage request', async () => {
        handlers = {};
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });

        render(<MemoryRouter><ToastProvider><RequestModalProvider>
            <PipelineBoardPage currentUserId={1} canViewActivePipeline />
        </RequestModalProvider></ToastProvider></MemoryRouter>);
        await waitFor(() => expect(client.apiGet).toHaveBeenCalled());

        act(() => {
            handlers['.purchase-request.created']({
                id: 10, request_number: 'MPR26-0010', stage: 'draft', requested_by_id: 2,
            });
        });

        expect(screen.queryByText('MPR26-0010')).not.toBeInTheDocument();
    });

    it('a view-active-pipeline user accepts a created event for an active-pipeline-stage request', async () => {
        handlers = {};
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });

        render(<MemoryRouter><ToastProvider><RequestModalProvider>
            <PipelineBoardPage currentUserId={1} canViewActivePipeline />
        </RequestModalProvider></ToastProvider></MemoryRouter>);
        await waitFor(() => expect(client.apiGet).toHaveBeenCalled());

        act(() => {
            handlers['.purchase-request.created']({
                id: 11, request_number: 'MPR26-0011', stage: 'rfq', requested_by_id: 2,
            });
        });

        expect(screen.getByText('MPR26-0011')).toBeInTheDocument();
    });

    it('a view-all user accepts any created event', async () => {
        handlers = {};
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });

        render(<MemoryRouter><ToastProvider><RequestModalProvider>
            <PipelineBoardPage currentUserId={1} canViewAllPurchaseRequests />
        </RequestModalProvider></ToastProvider></MemoryRouter>);
        await waitFor(() => expect(client.apiGet).toHaveBeenCalled());

        act(() => {
            handlers['.purchase-request.created']({
                id: 12, request_number: 'MPR26-0012', stage: 'draft', requested_by_id: 2,
            });
        });

        expect(screen.getByText('MPR26-0012')).toBeInTheDocument();
    });
});
