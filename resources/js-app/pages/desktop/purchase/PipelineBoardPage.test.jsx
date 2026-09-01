import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { ToastProvider } from '../../../components/ui/Toast';
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
    it('splits requests into Active and Completed tabs', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [
                { id: 1, request_number: 'MPR26-0001', stage: 'draft', project_name: 'A', requested_by_name: 'Jane', department: 'Ops', date: '2026-08-01' },
                { id: 2, request_number: 'MPR26-0002', stage: 'complete', project_name: 'B', requested_by_name: 'Sam', department: 'Ops', date: '2026-07-01' },
            ],
        });

        render(<MemoryRouter><ToastProvider><PipelineBoardPage /></ToastProvider></MemoryRouter>);

        await waitFor(() => expect(screen.getByText('MPR26-0001')).toBeInTheDocument());
        expect(screen.queryByText('MPR26-0002')).not.toBeInTheDocument();

        fireEvent.click(screen.getByText(/Completed/));
        expect(screen.getByText('MPR26-0002')).toBeInTheDocument();
    });

    it("updates a request's stage live without a refetch, preserving the row's other fields", async () => {
        handlers = {};
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 1, request_number: 'MPR26-0001', stage: 'draft', project_name: 'A', requested_by_name: 'Jane', department: 'Ops', date: '2026-08-01' }],
        });

        render(<MemoryRouter><ToastProvider><PipelineBoardPage /></ToastProvider></MemoryRouter>);
        await waitFor(() => expect(screen.getByText('MPR26-0001')).toBeInTheDocument());
        expect(screen.getByText(/Draft/i)).toBeInTheDocument();

        // .purchase-request.stage-changed only carries {id, request_number, stage} —
        // firing it must patch the existing row's stage without blanking project_name etc.
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
            data: [{ id: 7, request_number: 'MPR26-0007', stage: 'draft', project_name: 'A', requested_by_name: 'Jane', department: 'Ops', date: '2026-08-01' }],
        });

        render(<MemoryRouter><ToastProvider><PipelineBoardPage /></ToastProvider></MemoryRouter>);
        await waitFor(() => expect(screen.getByText('MPR26-0007')).toBeInTheDocument());

        expect(screen.getByText('View').closest('a')).toHaveAttribute('href', '/app/purchase/pipeline/7');
    });

    it('a view-own user ignores a created event for someone else\'s request', async () => {
        handlers = {};
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });

        render(<MemoryRouter><ToastProvider>
            <PipelineBoardPage currentUserId={1} canViewOwnPurchaseRequests />
        </ToastProvider></MemoryRouter>);
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

        render(<MemoryRouter><ToastProvider>
            <PipelineBoardPage currentUserId={1} canViewOwnPurchaseRequests />
        </ToastProvider></MemoryRouter>);
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

        render(<MemoryRouter><ToastProvider>
            <PipelineBoardPage currentUserId={1} canViewActivePipeline />
        </ToastProvider></MemoryRouter>);
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

        render(<MemoryRouter><ToastProvider>
            <PipelineBoardPage currentUserId={1} canViewActivePipeline />
        </ToastProvider></MemoryRouter>);
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

        render(<MemoryRouter><ToastProvider>
            <PipelineBoardPage currentUserId={1} canViewAllPurchaseRequests />
        </ToastProvider></MemoryRouter>);
        await waitFor(() => expect(client.apiGet).toHaveBeenCalled());

        act(() => {
            handlers['.purchase-request.created']({
                id: 12, request_number: 'MPR26-0012', stage: 'draft', requested_by_id: 2,
            });
        });

        expect(screen.getByText('MPR26-0012')).toBeInTheDocument();
    });
});
