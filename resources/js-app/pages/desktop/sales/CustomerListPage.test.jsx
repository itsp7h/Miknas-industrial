import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import CustomerListPage from './CustomerListPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const CUSTOMERS = [
    { id: 1, name: 'Gulf Steel', contact_person: 'A. Buyer', phone: '111', credit_limit: '5000.00', outstanding_balance: '250.00', is_active: true },
    { id: 2, name: 'Zenith Trading', contact_person: null, phone: null, credit_limit: '0.00', outstanding_balance: '0.00', is_active: false },
];

const renderPage = () => render(<ToastProvider><CustomerListPage /></ToastProvider>);

describe('desktop CustomerListPage', () => {
    beforeEach(() => { vi.spyOn(client, 'apiGet').mockResolvedValue({ data: CUSTOMERS }); });

    it('lists customers with money formatted to two decimals', async () => {
        renderPage();
        expect(await screen.findByText('Gulf Steel')).toBeInTheDocument();
        expect(screen.getByText('5,000.00')).toBeInTheDocument();
    });

    it('opens the create form', async () => {
        renderPage();
        await screen.findByText('Gulf Steel');
        fireEvent.click(screen.getByText('New Customer'));
        expect(await screen.findByLabelText('Customer Name')).toBeInTheDocument();
        expect(screen.getByLabelText('Credit Limit')).toBeInTheDocument();
    });

    it('explains when the API deactivates instead of deleting', async () => {
        vi.spyOn(client, 'apiDelete').mockResolvedValue({
            deactivated: true,
            message: 'Customer has sales history, so it was deactivated rather than deleted.',
        });
        renderPage();
        await screen.findByText('Gulf Steel');
        fireEvent.click(screen.getAllByText('Delete')[0]);
        fireEvent.click(await screen.findByText('Confirm'));
        await waitFor(() => {
            expect(screen.getByText(/deactivated rather than deleted/)).toBeInTheDocument();
        });
    });
});
