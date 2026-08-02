import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
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

        render(<ToastProvider><PipelineBoardPage /></ToastProvider>);

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

        render(<ToastProvider><PipelineBoardPage /></ToastProvider>);
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

    it('renders a row link that navigates to the Blade detail page via a real <a> tag', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 7, request_number: 'MPR26-0007', stage: 'draft', project_name: 'A', requested_by_name: 'Jane', department: 'Ops', date: '2026-08-01' }],
        });

        render(<ToastProvider><PipelineBoardPage /></ToastProvider>);
        await waitFor(() => expect(screen.getByText('MPR26-0007')).toBeInTheDocument());

        expect(screen.getByText('View').closest('a')).toHaveAttribute('href', '/purchase/pipeline/7');
    });
});
