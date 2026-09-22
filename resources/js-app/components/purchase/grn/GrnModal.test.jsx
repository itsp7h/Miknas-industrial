import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import GrnModal from './GrnModal';
import * as client from '../../../api/client';

const OPTIONS = {
    warehouses: [{ id: 2, name: 'Sitra Store' }],
    types: ['inventory', 'consumable'],
    purchase_orders: [{
        id: 5, po_number: 'PO-00005', supplier_name: 'Gulf Metals',
        items: [
            { purchase_order_item_id: 11, item_id: 7, item_name: 'Steel rod 12mm', quantity: 10, quantity_received: 4, rate: 2 },
            { purchase_order_item_id: 12, item_id: 9, item_name: 'Angle bar', quantity: 6, quantity_received: 0, rate: 5 },
        ],
    }],
};

describe('GrnModal', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue(OPTIONS);
    });

    const open = async (props = {}) => {
        render(<GrnModal onSaved={() => {}} onCancel={() => {}} {...props} />);
        await screen.findByText('New Goods Receipt Note');
    };

    it('opens with the create chrome and the three sections', async () => {
        await open();

        expect(screen.getByRole('heading', { name: 'Receipt Details' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Items Received' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Notes' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Save GRN' })).toHaveClass('btn-primary');
    });

    /**
     * The completeness check: every field POST /purchase/grns validates has
     * to be on this form and reach the payload. Losing one silently is the
     * failure mode a redesign risks.
     */
    it('carries every field the API accepts', async () => {
        const post = vi.spyOn(client, 'apiPost').mockResolvedValue({ data: { id: 1 } });
        await open();

        expect(screen.getByLabelText(/Purchase Order/)).toBeInTheDocument();
        expect(screen.getByLabelText(/Warehouse/)).toBeInTheDocument();
        expect(screen.getByLabelText(/Received Date/)).toBeInTheDocument();
        expect(screen.getByLabelText('Notes')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText(/Purchase Order/), { target: { value: '5' } });
        fireEvent.change(screen.getByLabelText(/Warehouse/), { target: { value: '2' } });
        fireEvent.change(screen.getByLabelText(/Received Date/), { target: { value: '2026-09-10' } });
        fireEvent.change(screen.getByLabelText('Notes'), { target: { value: 'Two pallets' } });
        fireEvent.click(screen.getByLabelText('Consumable for Angle bar', { selector: 'input' }));

        fireEvent.click(screen.getByRole('button', { name: 'Save GRN' }));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/purchase/grns', {
            purchase_order_id: '5',
            warehouse_id: '2',
            received_date: '2026-09-10',
            notes: 'Two pallets',
            items: [
                { item_id: 7, purchase_order_item_id: 11, quantity_received: '6', unit_cost: 2, type: 'inventory' },
                { item_id: 9, purchase_order_item_id: 12, quantity_received: '6', unit_cost: 5, type: 'consumable' },
            ],
        }));
    });

    /** Each line defaults to what the order still has outstanding. */
    it('loads the order’s lines and defaults each to the outstanding quantity', async () => {
        await open();

        fireEvent.change(screen.getByLabelText(/Purchase Order/), { target: { value: '5' } });

        // 10 ordered less 4 already received.
        expect(await screen.findByLabelText('Quantity received for Steel rod 12mm')).toHaveValue(6);
        expect(screen.getByLabelText('Quantity received for Angle bar')).toHaveValue(6);
        expect(screen.getByText('Steel rod 12mm')).toBeInTheDocument();
    });

    it('says what to do before an order is chosen', async () => {
        await open();

        expect(screen.getByText('Select a purchase order to load its items.')).toBeInTheDocument();
    });

    it('honours the purchase order the invoices page sent it', async () => {
        await open({ presetOrderId: 5 });

        expect(await screen.findByLabelText('Quantity received for Steel rod 12mm')).toBeInTheDocument();
        expect(screen.getByLabelText(/Purchase Order/)).toHaveValue('5');
    });

    /**
     * The Inventory/Consumable choice decides whether a line raises stock,
     * so it is a radio group — it used to be clickable `<span>`s, reachable
     * only with a mouse.
     */
    it('offers the type as a keyboard-reachable radio group', async () => {
        await open({ presetOrderId: 5 });
        await screen.findByLabelText('Quantity received for Steel rod 12mm');

        const inventory = screen.getByLabelText('Inventory for Steel rod 12mm', { selector: 'input' });
        expect(inventory).toBeChecked();
        expect(inventory.type).toBe('radio');
    });

    it('surfaces a line error keyed items.0.quantity_received', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({
            errors: { 'items.0.quantity_received': ['The quantity received must be at least 0.01.'] },
        });
        await open({ presetOrderId: 5 });
        await screen.findByLabelText('Quantity received for Steel rod 12mm');

        fireEvent.change(screen.getByLabelText(/Warehouse/), { target: { value: '2' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save GRN' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('must be at least 0.01');
        expect(screen.getByText(/Row 1:/)).toBeInTheDocument();
    });

    it('reports a failure that carries no field errors', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({ message: 'Server unavailable.' });
        await open({ presetOrderId: 5 });
        fireEvent.change(screen.getByLabelText(/Warehouse/), { target: { value: '2' } });

        fireEvent.click(screen.getByRole('button', { name: 'Save GRN' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('Server unavailable.');
    });

    it('says so when the options cannot be loaded', async () => {
        vi.spyOn(client, 'apiGet').mockRejectedValue({ message: 'nope', status: 500 });
        render(<GrnModal onSaved={() => {}} onCancel={() => {}} />);

        expect(await screen.findByRole('alert'))
            .toHaveTextContent('The purchase order and warehouse lists could not be loaded.');
    });

    it('calls a refusal a refusal, not a loading failure', async () => {
        vi.spyOn(client, 'apiGet').mockRejectedValue({
            status: 403, message: 'User does not have the right permissions.',
        });
        render(<GrnModal onSaved={() => {}} onCancel={() => {}} />);

        // A view-only account was told the lists "could not be loaded" and went
        // looking for a fault that was not there.
        expect(await screen.findByRole('alert'))
            .toHaveTextContent('You do not have permission to create goods receipts.');
    });
});

/**
 * A request names its company, so its goods have a yard already implied. The
 * form stops asking, and says whose decision it is.
 */
describe('GrnModal and the company warehouse', () => {
    const LINKED = {
        warehouses: [{ id: 2, name: 'Sitra Store' }, { id: 3, name: 'Askar' }],
        types: ['inventory', 'consumable'],
        purchase_orders: [
            {
                id: 5, po_number: 'PO-00005', supplier_name: 'Gulf Metals',
                warehouse_id: 3, company_name: 'Miknas Industrial',
                items: [{ purchase_order_item_id: 11, item_id: 7, item_name: 'Steel rod 12mm', quantity: 10, quantity_received: 4, rate: 2 }],
            },
            {
                id: 6, po_number: 'PO-00006', supplier_name: 'Bahrain Steel',
                warehouse_id: null, company_name: 'Matana',
                items: [{ purchase_order_item_id: 12, item_id: 9, item_name: 'Angle bar', quantity: 6, quantity_received: 0, rate: 5 }],
            },
        ],
    };

    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue(LINKED);
    });

    const open = async () => {
        render(<GrnModal onSaved={() => {}} onCancel={() => {}} />);
        await screen.findByText('New Goods Receipt Note');
    };

    it('fills the warehouse in from the order’s company and locks it', async () => {
        await open();

        fireEvent.change(screen.getByLabelText(/Purchase Order/), { target: { value: '5' } });

        await waitFor(() => expect(screen.getByLabelText(/Warehouse/)).toHaveValue('3'));
        // Changed in Settings, not on the day — so the field says whose
        // decision it is rather than just refusing to move.
        expect(screen.getByLabelText(/Warehouse/)).toBeDisabled();
        expect(screen.getByText(/Set by Miknas Industrial in Settings/)).toBeInTheDocument();
    });

    it('leaves the choice open for a company with no warehouse set', async () => {
        await open();

        fireEvent.change(screen.getByLabelText(/Purchase Order/), { target: { value: '6' } });

        await waitFor(() => expect(screen.getByLabelText(/Purchase Order/)).toHaveValue('6'));
        expect(screen.getByLabelText(/Warehouse/)).not.toBeDisabled();
        expect(screen.getByText('Where the inventory lines will be raised.')).toBeInTheDocument();
    });

    it('sends the company’s warehouse, not whatever was there before', async () => {
        const post = vi.spyOn(client, 'apiPost').mockResolvedValue({ data: { id: 1 } });
        await open();

        // Picked by hand first, then overruled by the order's company.
        fireEvent.change(screen.getByLabelText(/Purchase Order/), { target: { value: '6' } });
        fireEvent.change(screen.getByLabelText(/Warehouse/), { target: { value: '2' } });
        fireEvent.change(screen.getByLabelText(/Purchase Order/), { target: { value: '5' } });

        await waitFor(() => expect(screen.getByLabelText(/Warehouse/)).toHaveValue('3'));

        fireEvent.click(screen.getByRole('button', { name: 'Save GRN' }));

        await waitFor(() => expect(post).toHaveBeenCalledWith(
            '/purchase/grns',
            expect.objectContaining({ warehouse_id: '3' })
        ));
    });
});
