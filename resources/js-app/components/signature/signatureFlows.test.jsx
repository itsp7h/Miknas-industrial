import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import PipelineDialogs from '../purchase/pipeline/PipelineDialogs';
import PurchaseOrderModal from '../purchase/order/PurchaseOrderModal';
import DesktopProfilePage from '../../pages/desktop/profile/ProfilePage';
import { ToastProvider } from '../ui/Toast';
import { PageTitleProvider } from '../../layouts/PageTitleContext';
import * as client from '../../api/client';

const PNG = 'data:image/png;base64,iVBORw0KGgo=';

// jsdom has no canvas, so the pad cannot be drawn on here; a stand-in reports
// a signature the way drawing or uploading would.
vi.mock('./SignatureCapture', () => ({
    default: ({ onChange }) => <button type="button" onClick={() => onChange(PNG)}>fake sign</button>,
}));

vi.mock('../../echo', () => ({
    echo: { private: () => ({ listen: () => {}, stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const MISSING = { status: 422, code: 'signature_required', message: 'Add your signature before issuing an LPO.' };

const request = {
    id: 3, request_number: 'MPR-0003', stage: 'lpo', items: [], purchase_orders: [],
    pending_invitation_count: 0, permissions: { generateLpo: true, manageRfq: false },
};

describe('issuing an LPO without a saved signature', () => {
    beforeEach(() => vi.restoreAllMocks());

    it('asks for the signature, saves it to the profile, then issues the LPO', async () => {
        const generateLpo = vi.fn()
            .mockRejectedValueOnce(MISSING)
            .mockResolvedValueOnce({});
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({ data: {} });
        render(<MemoryRouter><ToastProvider>
            <PipelineDialogs open="lpo" onClose={() => {}} request={request} actions={{ generateLpo }} />
        </ToastProvider></MemoryRouter>);

        fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

        expect(await screen.findByText('Add your signature')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Save and continue' })).toBeDisabled();

        fireEvent.click(screen.getByText('fake sign'));
        fireEvent.click(screen.getByRole('button', { name: 'Save and continue' }));

        await waitFor(() => expect(put).toHaveBeenCalledWith('/profile/signature', { signature_image: PNG }));
        await waitFor(() => expect(generateLpo).toHaveBeenCalledTimes(2));
        await waitFor(() => expect(screen.queryByText('Add your signature')).not.toBeInTheDocument());
    });

    it('issues nothing when the signature is not given', async () => {
        const generateLpo = vi.fn().mockRejectedValue(MISSING);
        render(<MemoryRouter><ToastProvider>
            <PipelineDialogs open="lpo" onClose={() => {}} request={request} actions={{ generateLpo }} />
        </ToastProvider></MemoryRouter>);

        fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));
        // The confirm dialog stays mounted here (its `open` is fixed), so pick
        // the signature dialog's own Cancel.
        const dialog = (await screen.findByText('Add your signature')).closest('.bg-white');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));

        expect(screen.queryByText('Add your signature')).not.toBeInTheDocument();
        expect(generateLpo).toHaveBeenCalledTimes(1);
    });

    it('keeps any other failure a plain error toast', async () => {
        const generateLpo = vi.fn().mockRejectedValue({ status: 422, message: 'Nothing is awarded yet.' });
        render(<MemoryRouter><ToastProvider>
            <PipelineDialogs open="lpo" onClose={() => {}} request={request} actions={{ generateLpo }} />
        </ToastProvider></MemoryRouter>);

        fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

        expect(await screen.findByText('Nothing is awarded yet.')).toBeInTheDocument();
        expect(screen.queryByText('Add your signature')).not.toBeInTheDocument();
    });
});

describe('creating a purchase order without a saved signature', () => {
    const OPTIONS = {
        suppliers: [{ id: 3, name: 'Gulf Metals' }],
        items: [{ id: 7, item_code: 'ST-12', item_name: 'Steel rod 12mm', unit_of_measure: 'kg', cost_price: 2 }],
        purchase_requests: [], statuses: ['draft'],
    };

    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue(OPTIONS);
    });

    it('asks for the signature, then saves the order', async () => {
        const post = vi.spyOn(client, 'apiPost')
            .mockRejectedValueOnce(MISSING)
            .mockResolvedValueOnce({ data: { id: 1, po_number: 'PO-00001' } });
        vi.spyOn(client, 'apiPut').mockResolvedValue({ data: {} });
        const onSaved = vi.fn();
        render(<PurchaseOrderModal order={null} onSaved={onSaved} onCancel={() => {}} />);
        await screen.findByRole('option', { name: 'Gulf Metals' });

        fireEvent.change(screen.getByLabelText(/Supplier/), { target: { value: '3' } });
        fireEvent.change(screen.getByLabelText(/PO Date/), { target: { value: '2026-09-27' } });
        fireEvent.change(screen.getByLabelText('Item for row 1'), { target: { value: '7' } });
        fireEvent.change(screen.getByLabelText('Quantity for row 1'), { target: { value: '1' } });
        fireEvent.change(screen.getByLabelText('Rate for row 1'), { target: { value: '2' } });
        fireEvent.click(screen.getByRole('button', { name: 'Create Purchase Order' }));

        expect(await screen.findByText('Add your signature')).toBeInTheDocument();
        fireEvent.click(screen.getByText('fake sign'));
        fireEvent.click(screen.getByRole('button', { name: 'Save and continue' }));

        await waitFor(() => expect(post).toHaveBeenCalledTimes(2));
        await waitFor(() => expect(onSaved).toHaveBeenCalledWith({ id: 1, po_number: 'PO-00001' }));
    });
});

describe('Profile → Signature', () => {
    const USER = { id: 1, name: 'Nelson', email: 'n@erp.com', email_verified: true, roles: [] };
    const page = () => render(<PageTitleProvider><ToastProvider><DesktopProfilePage /></ToastProvider></PageTitleProvider>);

    beforeEach(() => vi.restoreAllMocks());

    it('asks for a signature when none is saved, and saves one', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: { ...USER, signature: null } });
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({
            data: { ...USER, signature: PNG }, message: 'Signature saved. It will appear on every LPO you issue.',
        });
        page();

        expect(await screen.findByText('Signature', { selector: 'h2' })).toBeInTheDocument();
        const save = screen.getByRole('button', { name: 'Save Signature' });
        expect(save).toBeDisabled();

        fireEvent.click(screen.getByText('fake sign'));
        fireEvent.click(save);

        await waitFor(() => expect(put).toHaveBeenCalledWith('/profile/signature', { signature_image: PNG }));
        expect(await screen.findByAltText('Your signature')).toHaveAttribute('src', PNG);
        expect(screen.getByText('Signature saved. It will appear on every LPO you issue.')).toBeInTheDocument();
    });

    it('shows the saved signature, and replaces or removes it', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: { ...USER, signature: PNG } });
        const del = vi.spyOn(client, 'apiDelete').mockResolvedValue({
            data: { ...USER, signature: null }, message: 'Signature removed.',
        });
        page();

        expect(await screen.findByAltText('Your signature')).toHaveAttribute('src', PNG);

        fireEvent.click(screen.getByRole('button', { name: 'Replace' }));
        expect(screen.getByRole('button', { name: 'Save Signature' })).toBeDisabled();
        fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
        expect(screen.getByAltText('Your signature')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Remove' }));
        await waitFor(() => expect(del).toHaveBeenCalledWith('/profile/signature'));
        expect(await screen.findByRole('button', { name: 'Save Signature' })).toBeInTheDocument();
    });
});
