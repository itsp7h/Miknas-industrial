import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import CustomerListPage from './CustomerListPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const CUSTOMERS = [
    { id: 1, name: 'Gulf Steel', contact_person: 'A. Buyer', phone: '111', email: 'a@x.com', credit_limit: '5000.00', outstanding_balance: '250.00', is_active: true },
    { id: 2, name: 'Zenith Trading', contact_person: null, phone: null, email: null, credit_limit: '0.00', outstanding_balance: '0.00', is_active: false },
];

const renderPage = () => render(<ToastProvider><CustomerListPage /></ToastProvider>);

describe('mobile CustomerListPage', () => {
    beforeEach(() => { vi.spyOn(client, 'apiGet').mockResolvedValue({ data: CUSTOMERS }); });

    it('renders customers as cards, not a table', async () => {
        const { container } = renderPage();
        expect(await screen.findByText('Gulf Steel')).toBeInTheDocument();
        expect(container.querySelector('table')).toBeNull();
    });

    it('filters client-side with a live count', async () => {
        renderPage();
        await screen.findByText('Gulf Steel');
        expect(screen.getByText('2 customers')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Search customers'), { target: { value: 'zenith' } });

        expect(screen.getByText('1 of 2 customers')).toBeInTheDocument();
        expect(screen.queryByText('Gulf Steel')).not.toBeInTheDocument();
    });

    it('flags a non-zero outstanding balance in red', async () => {
        renderPage();
        await screen.findByText('Gulf Steel');
        expect(screen.getByText('250.00')).toHaveClass('text-red-600', 'font-semibold');
    });

    it('badges the status rather than colouring a word', async () => {
        renderPage();
        await screen.findByText('Gulf Steel');
        expect(screen.getByText('Active')).toHaveClass('badge-green');
        expect(screen.getByText('Inactive')).toHaveClass('badge-gray');
    });

    it('shows a no-results message', async () => {
        renderPage();
        await screen.findByText('Gulf Steel');
        fireEvent.change(screen.getByLabelText('Search customers'), { target: { value: 'zzz' } });
        expect(screen.getByText('No customers match that search.')).toBeInTheDocument();
    });
});
