import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import DesktopRequestSheetPage from './RequestSheetPage';
import MobileRequestSheetPage from '../../mobile/purchase/RequestSheetPage';
import { ToastProvider } from '../../../components/ui/Toast';
import { RequestModalProvider } from '../../../components/purchase/requests/RequestModalProvider';
import { PageTitleProvider } from '../../../layouts/PageTitleContext';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => {}, stopListening: () => {} }), leave: () => {} },
}));

const SHEET = {
    id: 7,
    request_number: 'MPR26-0007',
    status: 'pending',
    stage: 'draft',
    date: '2026-08-20',
    project_name: 'Plant Expansion',
    requested_by_name: 'Aisha Rahman',
    required_date_text: '1 Week',
    location: 'Bay 4',
    department: 'Operations',
    remarks: 'Before the shutdown.',
    items: [
        {
            id: 11, description: 'Steel Plate 10mm', unit: 'KG',
            quantity_required: '500.00', purpose_use: 'Frame', required_date: '2026-09-10',
        },
        {
            id: 12, description: 'Galvanised Bolt M12', unit: null,
            quantity_required: '2000.00', purpose_use: null, required_date: null,
        },
    ],
    approval: null,
    rejection: null,
    print_url: '/purchase/requests/7/print',
    permissions: { update: true, delete: true },
};

const renderPage = (Page = DesktopRequestSheetPage) => render(
    <MemoryRouter initialEntries={['/app/purchase/requests/7']}>
        <ToastProvider>
            <RequestModalProvider>
                <PageTitleProvider>
                    <Routes>
                        <Route path="/app/purchase/requests/:id" element={<Page />} />
                        <Route path="/app/purchase/pipeline" element={<p>the board</p>} />
                    </Routes>
                </PageTitleProvider>
            </RequestModalProvider>
        </ToastProvider>
    </MemoryRouter>
);

describe('the MPR sheet page', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: SHEET });
    });

    it('fetches the request named in the route', async () => {
        renderPage();
        await waitFor(() => expect(client.apiGet).toHaveBeenCalledWith('/purchase/requests/7'));
    });

    it('prints the project grid as Blade did, with d-m-Y dates and em-dashes for blanks', async () => {
        renderPage();

        await waitFor(() => expect(screen.getByText('MPR26-0007', { selector: 'h1' })).toBeInTheDocument());
        expect(screen.getByText('20-08-2026')).toBeInTheDocument();
        expect(screen.getByText('Plant Expansion')).toBeInTheDocument();
        expect(screen.getByText('Aisha Rahman')).toBeInTheDocument();
        expect(screen.getByText('Bay 4')).toBeInTheDocument();
        expect(screen.getByText('Before the shutdown.')).toBeInTheDocument();
        expect(screen.getByText('Pending')).toBeInTheDocument();

        // The item table: quantities to two places, blanks as em-dashes.
        expect(screen.getByText('Steel Plate 10mm')).toBeInTheDocument();
        expect(screen.getByText('10-09-2026')).toBeInTheDocument();
        expect(screen.getByText('500.00')).toBeInTheDocument();
        expect(screen.getAllByText('—').length).toBeGreaterThanOrEqual(3);
    });

    it('hides the approval card until the request is approved', async () => {
        renderPage();
        await waitFor(() => expect(screen.getByText('Material Details')).toBeInTheDocument());
        expect(screen.queryByText('Approval Info')).not.toBeInTheDocument();

        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: {
                ...SHEET,
                status: 'approved',
                approval: { approved_by_name: 'Khalid Nasser', approved_at: '2026-09-01T14:07:00+00:00' },
            },
        });
        renderPage();
        await waitFor(() => expect(screen.getByText('Approval Info')).toBeInTheDocument());
        expect(screen.getByText('Khalid Nasser')).toBeInTheDocument();
    });

    it('shows the rejection with its reason, and only while the request is rejected', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: {
                ...SHEET,
                status: 'rejected',
                rejection: {
                    reason: 'Quantities exceed the project budget.',
                    rejected_by_name: 'Khalid Nasser',
                    rejected_at: '2026-09-02T10:09:00+00:00',
                },
            },
        });
        renderPage();

        await waitFor(() => expect(screen.getByText('Rejection Info')).toBeInTheDocument());
        expect(screen.getByText('Quantities exceed the project budget.')).toBeInTheDocument();
        expect(screen.getByText('Khalid Nasser')).toBeInTheDocument();
        expect(screen.getByText('Rejected')).toBeInTheDocument();
        expect(screen.queryByText('Approval Info')).not.toBeInTheDocument();
    });

    it('links the DomPDF document and the pipeline, and opens the edit modal in place', async () => {
        renderPage();
        await waitFor(() => expect(screen.getByText('Print MPR Form')).toBeInTheDocument());

        expect(screen.getByText('Print MPR Form').closest('a')).toHaveAttribute('href', '/purchase/requests/7/print');
        expect(screen.getByText('Back to Pipeline').closest('a')).toHaveAttribute('href', '/app/purchase/pipeline/7');

        // Editing is the modal, not a navigation to a Blade page.
        vi.spyOn(client, 'apiGet').mockImplementation((path) => (
            path.endsWith('/edit')
                ? Promise.resolve({ data: { ...SHEET, items: SHEET.items } })
                : Promise.resolve({ projects: [], departments: [], units: [], today: '2026-09-02' })
        ));
        fireEvent.click(screen.getByText('Edit'));
        await waitFor(() => expect(screen.getByText('Edit Purchase Request')).toBeInTheDocument());
    });

    it('deletes on confirmation and returns to the board', async () => {
        const destroy = vi.spyOn(client, 'apiDelete').mockResolvedValue({ message: 'MPR26-0007 deleted.' });
        renderPage();
        await waitFor(() => expect(screen.getByText('Delete')).toBeInTheDocument());

        fireEvent.click(screen.getByText('Delete'));
        expect(screen.getByText('Delete MPR26-0007?')).toBeInTheDocument();
        fireEvent.click(screen.getByText('Confirm'));

        await waitFor(() => expect(destroy).toHaveBeenCalledWith('/purchase/requests/7'));
        await waitFor(() => expect(screen.getByText('the board')).toBeInTheDocument());
    });

    it('offers neither Edit nor Delete when the policy says no', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: { ...SHEET, permissions: { update: false, delete: false } },
        });
        renderPage();

        await waitFor(() => expect(screen.getByText('Print MPR Form')).toBeInTheDocument());
        expect(screen.queryByText('Edit')).not.toBeInTheDocument();
        expect(screen.queryByText('Delete')).not.toBeInTheDocument();
    });

    it('says so when the request cannot be loaded', async () => {
        vi.spyOn(client, 'apiGet').mockRejectedValue({ message: 'nope' });
        renderPage();

        await waitFor(() => expect(
            screen.getByText('That purchase request could not be found.')
        ).toBeInTheDocument());
    });

    it('stacks its actions full width on mobile and renders the same sheet', async () => {
        renderPage(MobileRequestSheetPage);

        await waitFor(() => expect(screen.getByText('Print MPR Form')).toBeInTheDocument());
        expect(screen.getByText('Material Purchase Request')).toBeInTheDocument();
        expect(screen.getByText('Steel Plate 10mm')).toBeInTheDocument();
        expect(screen.getByText('Print MPR Form')).toHaveStyle({ justifyContent: 'center' });
    });
});
