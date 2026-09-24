import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import PurchaseOrderModal from './PurchaseOrderModal';
import * as client from '../../../api/client';

/**
 * The purchase order form had no tests before it was redrawn onto the shared
 * dialog, which for a form that creates orders is the gap worth closing
 * first. `apiGet` is stubbed for the options call every render makes.
 */
const OPTIONS = {
    suppliers: [{ id: 3, name: 'Gulf Metals' }],
    items: [
        { id: 7, item_code: 'ST-12', item_name: 'Steel rod 12mm', unit_of_measure: 'kg', cost_price: 2 },
        { id: 9, item_code: 'AB-01', item_name: 'Angle bar', unit_of_measure: 'pcs', cost_price: 5 },
    ],
    purchase_requests: [{ id: 4, request_number: 'MPR-0042' }],
    statuses: ['draft', 'sent', 'received', 'cancelled'],
};

describe('PurchaseOrderModal', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        // Answers a beat late on purpose, as a real network does. With an
        // instant mock the lists usually land before the first interaction,
        // so a test that forgets to wait for them passes locally and fails on
        // a slow CI runner. Late, it fails everywhere, every time.
        vi.spyOn(client, 'apiGet').mockImplementation(
            () => new Promise((resolve) => setTimeout(() => resolve(OPTIONS), 20)),
        );
    });

    /**
     * The dialog draws its chrome at once and fetches its supplier and item
     * lists after, so its title is no evidence the form is usable. Choosing
     * supplier 3 before option 3 exists leaves the select empty, `required`
     * then blocks the submit, and the test waits out its timeout for an alert
     * that nothing will raise — which is how this file failed on slow CI
     * runners. Wait for the options themselves.
     */
    const optionsLoaded = () => screen.findByRole('option', { name: 'Gulf Metals' });

    const openCreate = async (props = {}) => {
        render(<PurchaseOrderModal order={null} onSaved={() => {}} onCancel={() => {}} {...props} />);
        await screen.findByText('New Purchase Order');
        await optionsLoaded();
    };

    it('opens with the create chrome and the order sections', async () => {
        await openCreate();

        expect(screen.getByText('Local Purchase Order (LPO)')).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Order Details' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Order Items' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Notes' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Create Purchase Order' })).toHaveClass('btn-primary');
        expect(screen.getByRole('button', { name: 'Cancel' })).toHaveClass('btn-secondary');
    });

    it('posts the header and its line items', async () => {
        const post = vi.spyOn(client, 'apiPost').mockResolvedValue({ data: { id: 1, po_number: 'PO-00001' } });
        const onSaved = vi.fn();
        await openCreate({ onSaved });

        fireEvent.change(screen.getByLabelText(/Supplier/), { target: { value: '3' } });
        fireEvent.change(screen.getByLabelText(/PO Date/), { target: { value: '2026-09-10' } });
        fireEvent.change(screen.getByLabelText('Item for row 1'), { target: { value: '7' } });
        fireEvent.change(screen.getByLabelText('Quantity for row 1'), { target: { value: '10' } });
        fireEvent.change(screen.getByLabelText('Rate for row 1'), { target: { value: '2' } });
        fireEvent.click(screen.getByRole('button', { name: 'Create Purchase Order' }));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/purchase/orders', expect.objectContaining({
            supplier_id: '3',
            po_date: '2026-09-10',
            purchase_request_id: null,
            items: [{ item_id: '7', quantity: '10', rate: '2' }],
        })));
        await waitFor(() => expect(onSaved).toHaveBeenCalled());
    });

    /** The unit belongs to the item; form-options has always carried it unrendered. */
    it('shows the selected item’s unit and totals the row and the order', async () => {
        await openCreate();

        fireEvent.change(screen.getByLabelText('Item for row 1'), { target: { value: '7' } });
        fireEvent.change(screen.getByLabelText('Quantity for row 1'), { target: { value: '10' } });
        fireEvent.change(screen.getByLabelText('Rate for row 1'), { target: { value: '2.5' } });

        expect(screen.getByText('kg')).toBeInTheDocument();
        expect(screen.getAllByText('BD 25.000').length).toBeGreaterThan(0);
    });

    it('adds and removes rows, never dropping the last one', async () => {
        await openCreate();

        expect(screen.queryByLabelText('Remove row 1')).not.toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: '+ Add Row' }));
        expect(screen.getByLabelText('Item for row 2')).toBeInTheDocument();

        fireEvent.click(screen.getByLabelText('Remove row 2'));
        expect(screen.queryByLabelText('Item for row 2')).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Remove row 1')).not.toBeInTheDocument();
    });

    it('surfaces a line error keyed items.0.quantity', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({
            errors: { 'items.0.quantity': ['The quantity must be at least 1.'] },
        });
        await openCreate();

        fireEvent.change(screen.getByLabelText(/Supplier/), { target: { value: '3' } });
        fireEvent.click(screen.getByRole('button', { name: 'Create Purchase Order' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('The quantity must be at least 1.');
    });

    /**
     * Supplier and PO Date carry `required`, as they did in Blade, so the
     * browser stops an empty submit before the API is reached — these tests
     * fill them and drive a server-side rejection on another field.
     */
    it('shows a header field error in the summary and against its field', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({
            errors: { expected_delivery_date: ['The expected delivery date must be on or after the PO date.'] },
        });
        await openCreate();

        fireEvent.change(screen.getByLabelText(/Supplier/), { target: { value: '3' } });
        fireEvent.click(screen.getByRole('button', { name: 'Create Purchase Order' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('must be on or after the PO date');
        expect(screen.getAllByText(/must be on or after the PO date/)).toHaveLength(2);
    });

    it('will not submit at all without a supplier', async () => {
        const post = vi.spyOn(client, 'apiPost');
        await openCreate();

        fireEvent.click(screen.getByRole('button', { name: 'Create Purchase Order' }));

        expect(post).not.toHaveBeenCalled();
        expect(screen.getByLabelText(/Supplier/)).toBeRequired();
    });

    /** A failure with no field errors used to leave the dialog silent. */
    it('reports a failure that carries no field errors', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({ message: 'Server unavailable.' });
        const onSaved = vi.fn();
        await openCreate({ onSaved });

        fireEvent.change(screen.getByLabelText(/Supplier/), { target: { value: '3' } });
        fireEvent.click(screen.getByRole('button', { name: 'Create Purchase Order' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('Server unavailable.');
        expect(onSaved).not.toHaveBeenCalled();
    });

    it('says so when the supplier and item lists cannot be loaded', async () => {
        vi.spyOn(client, 'apiGet').mockRejectedValue({ message: 'nope' });
        render(<PurchaseOrderModal order={null} onSaved={() => {}} onCancel={() => {}} />);

        expect(await screen.findByRole('alert'))
            .toHaveTextContent('The supplier and item lists could not be loaded.');
    });

    /**
     * Edit is header-only: the Blade edit page had no line-item section and
     * the API's update endpoint mirrors that scope. Status appears only here.
     */
    it('edits the header only, and adds Status', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({ data: { id: 5 } });
        const onSaved = vi.fn();
        const order = {
            id: 5, po_number: 'PO-00005', supplier_id: 3, po_date: '2026-09-01',
            expected_delivery_date: '2026-09-20', status: 'sent', notes: 'Rush',
        };

        render(<PurchaseOrderModal order={order} onSaved={onSaved} onCancel={() => {}} />);
        await screen.findByText('Edit Purchase Order');
        await optionsLoaded();

        expect(screen.getByText('PO-00005')).toBeInTheDocument();
        expect(screen.queryByRole('heading', { name: 'Order Items' })).not.toBeInTheDocument();
        expect(screen.queryByLabelText(/Purchase Request/)).not.toBeInTheDocument();
        expect(screen.getByLabelText('Status')).toHaveValue('sent');

        fireEvent.change(screen.getByLabelText('Status'), { target: { value: 'received' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save Changes' }));

        await waitFor(() => expect(put).toHaveBeenCalledWith('/purchase/orders/5', {
            supplier_id: 3,
            po_date: '2026-09-01',
            expected_delivery_date: '2026-09-20',
            status: 'received',
            notes: 'Rush',
        }));
        await waitFor(() => expect(onSaved).toHaveBeenCalled());
    });

    it('closes on Cancel and on Escape', async () => {
        const onCancel = vi.fn();
        await openCreate({ onCancel });

        fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
        expect(onCancel).toHaveBeenCalledTimes(1);

        fireEvent.keyDown(document, { key: 'Escape' });
        expect(onCancel).toHaveBeenCalledTimes(2);
    });
});
