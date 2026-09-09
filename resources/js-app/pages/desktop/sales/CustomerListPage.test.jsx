import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import CustomerListPage from './CustomerListPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const CUSTOMERS = [
    { id: 1, name: 'Gulf Steel', contact_person: 'A. Buyer', email: 'a@gulf.example', phone: '111', credit_limit: '5000.00', outstanding_balance: '250.00', is_active: true },
    { id: 2, name: 'Zenith Trading', contact_person: null, email: null, phone: null, credit_limit: '0.00', outstanding_balance: '0.00', is_active: false },
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
        fireEvent.click(screen.getByText('+ Add Customer'));
        expect(await screen.findByLabelText(/Customer Name/)).toBeInTheDocument();
        expect(screen.getByLabelText('Credit Limit')).toBeInTheDocument();
        // Blade hinted at the format WhatsApp needs; the modal had dropped it.
        expect(screen.getByPlaceholderText('+971501234567')).toBeInTheDocument();
        expect(screen.getByText('Save Customer')).toHaveClass('btn-primary');
    });

    // Blade's columns, in Blade's order. The first React port swapped Email for
    // Phone and turned the status into the word "Yes".
    it('lists Blade\u2019s seven columns', async () => {
        renderPage();
        await screen.findByText('Gulf Steel');
        expect(screen.getAllByRole('columnheader').map((th) => th.textContent))
            .toEqual(['Name', 'Contact', 'Email', 'Credit Limit', 'Outstanding Balance', 'Status', 'Actions']);
        expect(screen.getByText('a@gulf.example')).toBeInTheDocument();
        expect(screen.getByText('Active')).toHaveClass('badge-green');
        expect(screen.getByText('Inactive')).toHaveClass('badge-gray');
        expect(screen.queryByText('Yes')).not.toBeInTheDocument();
    });

    // The point of the column: money owed stands out.
    it('flags a non-zero outstanding balance in red and semibold', async () => {
        renderPage();
        await screen.findByText('Gulf Steel');
        expect(screen.getByText('250.00')).toHaveClass('text-red-600', 'font-semibold');
        // Zenith's credit limit is also 0.00, so pick the balance cell by position.
        const zenithCells = screen.getByText('Zenith Trading').closest('tr').querySelectorAll('td');
        expect(zenithCells[4]).toHaveClass('text-gray-500');
        expect(zenithCells[4]).not.toHaveClass('text-red-600');
    });

    it('filters client-side with a live count, email included', async () => {
        renderPage();
        await screen.findByText('Gulf Steel');
        expect(screen.getByText('2 customers')).toBeInTheDocument();
        fireEvent.change(screen.getByLabelText('Search customers'), { target: { value: 'gulf.example' } });
        expect(screen.getByText('1 of 2 customers')).toBeInTheDocument();
        expect(screen.queryByText('Zenith Trading')).not.toBeInTheDocument();
    });

    it('says so when there are no customers', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });
        renderPage();
        expect(await screen.findByText('No customers found.')).toBeInTheDocument();
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
