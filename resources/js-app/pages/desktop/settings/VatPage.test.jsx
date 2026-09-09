import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import DesktopVatPage from './VatPage';
import MobileVatPage from '../../mobile/settings/VatPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => {}, stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const wrap = (Page) => render(<ToastProvider><Page /></ToastProvider>);

describe('settings VatPage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue({ vat_rate: 10 });
    });

    it('renders the header and the stored rate', async () => {
        wrap(DesktopVatPage);

        expect(await screen.findByText('VAT Settings')).toBeInTheDocument();
        expect(screen.getByText('Set the global VAT rate applied to vatable items on supplier quotes.')).toBeInTheDocument();
        expect(screen.getByText('VAT Configuration')).toBeInTheDocument();
        await waitFor(() => expect(screen.getByLabelText('VAT Rate (%)')).toHaveValue(10));
        expect(screen.getByText(/Enter 0 to disable VAT/)).toBeInTheDocument();
    });

    it('saves the rate', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({ message: 'VAT rate saved.', vat_rate: 15 });
        wrap(DesktopVatPage);

        await waitFor(() => expect(screen.getByLabelText('VAT Rate (%)')).toHaveValue(10));
        fireEvent.change(screen.getByLabelText('VAT Rate (%)'), { target: { value: '15' } });
        fireEvent.click(screen.getByText('Save VAT Rate'));

        await waitFor(() => expect(put).toHaveBeenCalledWith('/settings/vat', { vat_rate: '15' }));
        expect(await screen.findByText('VAT rate saved.')).toBeInTheDocument();
    });

    it('shows a validation message from the server and keeps the field', async () => {
        vi.spyOn(client, 'apiPut').mockRejectedValue({
            errors: { vat_rate: ['The vat rate must not be greater than 100.'] },
        });
        wrap(DesktopVatPage);

        await waitFor(() => expect(screen.getByLabelText('VAT Rate (%)')).toHaveValue(10));
        fireEvent.change(screen.getByLabelText('VAT Rate (%)'), { target: { value: '150' } });
        fireEvent.click(screen.getByText('Save VAT Rate'));

        // Once inline under the field, once as the toast.
        expect(await screen.findAllByText('The vat rate must not be greater than 100.')).toHaveLength(2);
        expect(screen.getByLabelText('VAT Rate (%)')).toHaveValue(150);
    });

    it('mobile gives the card the full width', async () => {
        const { container } = wrap(MobileVatPage);

        await screen.findByText('VAT Settings');
        expect(container.firstChild.lastChild).toHaveStyle({ maxWidth: '100%' });
    });
});
