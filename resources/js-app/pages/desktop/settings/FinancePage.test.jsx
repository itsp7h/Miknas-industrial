import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import DesktopFinancePage from './FinancePage';
import MobileFinancePage from '../../mobile/settings/FinancePage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => {}, stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const wrap = (Page) => render(<ToastProvider><Page /></ToastProvider>);

describe('settings FinancePage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            vat_rate: 10,
            currency_code: 'BHD',
            currency_symbol: 'BD',
            currencies: [
                { code: 'BHD', symbol: 'BD', label: 'BHD (BD)' },
                { code: 'USD', symbol: '$', label: 'USD ($)' },
            ],
        });
    });

    it('renders the header and the stored rate', async () => {
        wrap(DesktopFinancePage);

        expect(await screen.findByText('Finance')).toBeInTheDocument();
        // Both settings, one page, one request.
        expect(screen.getByText('VAT Configuration')).toBeInTheDocument();
        expect(screen.getByText('Currency')).toBeInTheDocument();
        await waitFor(() => expect(screen.getByLabelText('VAT Rate (%)')).toHaveValue(10));
        expect(screen.getByText(/Enter 0 to disable VAT/)).toBeInTheDocument();
    });

    it('saves the rate', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({ message: 'VAT rate saved.', vat_rate: 15 });
        wrap(DesktopFinancePage);

        await waitFor(() => expect(screen.getByLabelText('VAT Rate (%)')).toHaveValue(10));
        fireEvent.change(screen.getByLabelText('VAT Rate (%)'), { target: { value: '15' } });
        fireEvent.click(screen.getByText('Save VAT Rate'));

        await waitFor(() => expect(put).toHaveBeenCalledWith('/settings/finance', { vat_rate: '15' }));
        expect(await screen.findByText('VAT rate saved.')).toBeInTheDocument();
    });

    it('shows a validation message from the server and keeps the field', async () => {
        vi.spyOn(client, 'apiPut').mockRejectedValue({
            errors: { vat_rate: ['The vat rate must not be greater than 100.'] },
        });
        wrap(DesktopFinancePage);

        await waitFor(() => expect(screen.getByLabelText('VAT Rate (%)')).toHaveValue(10));
        fireEvent.change(screen.getByLabelText('VAT Rate (%)'), { target: { value: '150' } });
        fireEvent.click(screen.getByText('Save VAT Rate'));

        // Once inline under the field, once as the toast.
        expect(await screen.findAllByText('The vat rate must not be greater than 100.')).toHaveLength(2);
        expect(screen.getByLabelText('VAT Rate (%)')).toHaveValue(150);
    });

    it('defaults the currency to Bahrain and shows its symbol', async () => {
        wrap(DesktopFinancePage);

        await waitFor(() => expect(screen.getByLabelText('Default Currency')).toHaveValue('BHD'));
        expect(screen.getByText('BD')).toBeInTheDocument();
        expect([...screen.getByLabelText('Default Currency').options].map((o) => o.textContent))
            .toEqual(['BHD (BD)', 'USD ($)']);
    });

    it('saves the currency on its own, without touching the rate', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({
            message: 'Currency saved.', currency_code: 'USD', currency_symbol: '$', vat_rate: 10,
        });
        wrap(DesktopFinancePage);

        await waitFor(() => expect(screen.getByLabelText('Default Currency')).toHaveValue('BHD'));
        fireEvent.change(screen.getByLabelText('Default Currency'), { target: { value: 'USD' } });
        fireEvent.click(screen.getByText('Save Currency'));

        // Only the currency is sent: a half-typed rate must not be written too.
        await waitFor(() => expect(put).toHaveBeenCalledWith('/settings/finance', { currency_code: 'USD' }));
        expect(await screen.findByText('Currency saved.')).toBeInTheDocument();
    });

    it('mobile gives the cards the full width', async () => {
        const { container } = wrap(MobileFinancePage);

        await screen.findByText('Finance');
        expect(container.firstChild.lastChild).toHaveStyle({ maxWidth: '100%' });
    });
});
