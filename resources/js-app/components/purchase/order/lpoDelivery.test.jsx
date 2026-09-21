import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import LpoDeliveryStatus from './LpoDeliveryStatus';
import { ToastProvider } from '../../ui/Toast';
import * as client from '../../../api/client';

const ORDER = {
    id: 7,
    po_number: 'PO-00007',
    status: 'sent',
    sent_at: null,
    sent_to: null,
    supplier: { id: 1, name: 'Gulf Steel', email: 'sales@gulfsteel.test' },
};

const renderStatus = (order = ORDER, onSent = vi.fn()) =>
    render(<ToastProvider><LpoDeliveryStatus order={order} onSent={onSent} /></ToastProvider>);

describe('LpoDeliveryStatus', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
    });

    /**
     * `status` reads 'sent' from the moment the pipeline generates an order,
     * so a panel keyed off it would call every LPO delivered. Only sent_at is
     * evidence that an email left.
     */
    it('calls an unsent LPO unsent even though its status says sent', () => {
        renderStatus();
        expect(screen.getByText('⚠ Not sent to the supplier yet')).toBeInTheDocument();
        expect(screen.getByText(/Nothing has reached sales@gulfsteel\.test/)).toBeInTheDocument();
    });

    it('says when and where a sent LPO went', () => {
        renderStatus({ ...ORDER, sent_at: '2026-09-15T08:30:00+00:00', sent_to: 'sales@gulfsteel.test' });
        expect(screen.getByText('✓ Sent to supplier')).toBeInTheDocument();
        expect(screen.getByText(/Emailed to sales@gulfsteel\.test on/)).toBeInTheDocument();
        expect(screen.getByText('↻ Send again')).toBeInTheDocument();
    });

    it('sends the LPO and hands the updated order back', async () => {
        const onSent = vi.fn();
        const updated = { ...ORDER, sent_at: '2026-09-15T08:30:00+00:00', sent_to: 'sales@gulfsteel.test' };
        vi.spyOn(client, 'apiPost').mockResolvedValue({ data: updated, message: 'LPO emailed to sales@gulfsteel.test.' });

        renderStatus(ORDER, onSent);
        fireEvent.click(screen.getByText('✉ Send to supplier'));

        await waitFor(() => expect(onSent).toHaveBeenCalledWith(updated));
        expect(client.apiPost).toHaveBeenCalledWith('/purchase/orders/7/send');
        expect(await screen.findByText('LPO emailed to sales@gulfsteel.test.')).toBeInTheDocument();
    });

    // The whole point of recording the send separately: a failure has to be
    // visible, not a toast saying "done" over an email that never left.
    it('toasts the API failure rather than reporting success', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({ message: 'Could not email Gulf Steel: Connection refused' });

        renderStatus();
        fireEvent.click(screen.getByText('✉ Send to supplier'));

        expect(await screen.findByText(/Could not email Gulf Steel/)).toBeInTheDocument();
        expect(screen.getByText('⚠ Not sent to the supplier yet')).toBeInTheDocument();
    });

    it('cannot send to a supplier with no address, and says why', () => {
        renderStatus({ ...ORDER, supplier: { id: 1, name: 'Gulf Steel', email: null } });
        expect(screen.getByText(/no email address on file/)).toBeInTheDocument();
        expect(screen.getByText('✉ Send to supplier')).toBeDisabled();
    });

    it('renders nothing before the order has loaded', () => {
        const { container } = render(<ToastProvider><LpoDeliveryStatus order={null} /></ToastProvider>);
        expect(container.querySelector('button')).toBeNull();
    });
});
