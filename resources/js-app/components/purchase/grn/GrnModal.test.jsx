import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import GrnModal from './GrnModal';
import * as client from '../../../api/client';

const OPTIONS = {
    warehouses: [{ id: 2, name: 'Sitra Store' }],
    types: ['inventory', 'consumable'],
    projects: [
        { id: 3, name: 'Hidd Yard', company_id: 1 },
        { id: 4, name: 'Askar Plant', company_id: 1 },
        { id: 8, name: 'Another Company Job', company_id: 2 },
    ],
    purchase_orders: [{
        id: 5, po_number: 'PO-00005', supplier_name: 'Gulf Metals', company_id: 1, project_name: 'Hidd Yard',
        items: [
            { purchase_order_item_id: 11, item_id: 7, item_name: 'Steel rod 12mm', quantity: 10, quantity_received: 4, rate: 2 },
            { purchase_order_item_id: 12, item_id: 9, item_name: 'Angle bar', quantity: 6, quantity_received: 0, rate: 5 },
        ],
    }],
};

/** What a multipart request sent, as a plain object keyed by field name. */
const sent = (post) => Object.fromEntries(post.mock.calls[0][1].entries());

const attach = (label, name) => {
    const file = new File(['%PDF-1.4'], name, { type: 'application/pdf' });
    fireEvent.change(screen.getByLabelText(label), { target: { files: [file] } });

    return file;
};

// jsdom's constraint check reads its own file list, which a test cannot
// fill, so a required file input blocks the button for ever. Submitting the
// form skips that check, which is the browser's job, not the modal's.
const save = () => fireEvent.submit(document.getElementById('grn-form'));

const attachAll = () => {
    attach(/^LPO/, 'lpo.pdf');
    attach(/^GRN/, 'grn.pdf');
    attach(/^Tax Invoice/, 'invoice.pdf');
};

describe('GrnModal', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        // Answers a beat late, as a real network does, so a test that forgets
        // to wait for the options fails every time rather than only on a slow
        // CI runner (the PurchaseOrderModal tests do the same).
        vi.spyOn(client, 'apiGet').mockImplementation(
            () => new Promise((resolve) => setTimeout(() => resolve(OPTIONS), 20)),
        );
    });

    // Wait for the order list, not just the title: an order chosen before
    // its <option> exists is dropped (see the warehouse block below).
    const open = async (props = {}) => {
        render(<GrnModal onSaved={() => {}} onCancel={() => {}} {...props} />);
        await screen.findByText('New Goods Receipt Note');
        await screen.findByRole('option', { name: /PO-00005/ });
    };

    it('opens with the create chrome and the three sections', async () => {
        await open();

        expect(screen.getByRole('heading', { name: 'Receipt Details' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Items Received' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Documents' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Notes' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Save GRN' })).toHaveClass('btn-primary');
    });

    /**
     * The completeness check: every field POST /purchase/grns validates has
     * to be on this form and reach the payload. Losing one silently is the
     * failure mode a redesign risks.
     */
    it('carries every field the API accepts', async () => {
        const post = vi.spyOn(client, 'apiPostForm').mockResolvedValue({ data: { id: 1 } });
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
        const lpo = attach(/^LPO/, 'lpo.pdf');
        const grn = attach(/^GRN/, 'delivery-note.pdf');
        const invoice = attach(/^Tax Invoice/, 'invoice.pdf');

        save();

        await waitFor(() => expect(post).toHaveBeenCalledWith('/purchase/grns', expect.any(FormData)));
        expect(sent(post)).toEqual({
            purchase_order_id: '5',
            warehouse_id: '2',
            received_date: '2026-09-10',
            notes: 'Two pallets',
            'items[0][item_id]': '7',
            'items[0][purchase_order_item_id]': '11',
            'items[0][quantity_received]': '6',
            'items[0][unit_cost]': '2',
            'items[0][type]': 'inventory',
            'items[1][item_id]': '9',
            'items[1][purchase_order_item_id]': '12',
            'items[1][quantity_received]': '6',
            'items[1][unit_cost]': '5',
            'items[1][type]': 'consumable',
            'items[1][project_id]': '3',
            lpo_document: lpo,
            grn_document: grn,
            tax_invoice_document: invoice,
        });
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

    /**
     * A consumable is used up on a project, so choosing it asks which one:
     * the order's company's projects, starting from the MPR's own.
     */
    it('asks which project a consumable line is for', async () => {
        await open({ presetOrderId: 5 });
        await screen.findByLabelText('Quantity received for Steel rod 12mm');

        expect(screen.queryByLabelText('Project for Steel rod 12mm')).not.toBeInTheDocument();

        fireEvent.click(screen.getByLabelText('Consumable for Steel rod 12mm', { selector: 'input' }));

        const project = screen.getByLabelText('Project for Steel rod 12mm');
        expect(project).toHaveValue('3');
        expect(project).toBeRequired();
        const names = Array.from(project.options).map((o) => o.textContent);
        expect(names).toEqual(['— Which project? —', 'Hidd Yard', 'Askar Plant']);

        fireEvent.change(project, { target: { value: '4' } });
        fireEvent.click(screen.getByLabelText('Inventory for Steel rod 12mm', { selector: 'input' }));
        expect(screen.queryByLabelText('Project for Steel rod 12mm')).not.toBeInTheDocument();
    });

    it('surfaces a line error keyed items.0.quantity_received', async () => {
        vi.spyOn(client, 'apiPostForm').mockRejectedValue({
            errors: { 'items.0.quantity_received': ['The quantity received must be at least 0.01.'] },
        });
        await open({ presetOrderId: 5 });
        await screen.findByLabelText('Quantity received for Steel rod 12mm');

        fireEvent.change(screen.getByLabelText(/Warehouse/), { target: { value: '2' } });
        attachAll();
        save();

        expect(await screen.findByRole('alert')).toHaveTextContent('must be at least 0.01');
        expect(screen.getByText(/Row 1:/)).toBeInTheDocument();
    });

    it('shows a document error under that document', async () => {
        vi.spyOn(client, 'apiPostForm').mockRejectedValue({
            message: 'The Tax Invoice document field is required.',
            errors: { tax_invoice_document: ['The Tax Invoice document field is required.'] },
        });
        await open({ presetOrderId: 5 });
        fireEvent.change(screen.getByLabelText(/Warehouse/), { target: { value: '2' } });
        attachAll();

        save();

        await waitFor(() => expect(screen.getByLabelText(/^Tax Invoice/)).toHaveAttribute('aria-invalid', 'true'));
        expect(screen.getByLabelText(/^LPO/)).not.toHaveAttribute('aria-invalid');
        expect(screen.getByLabelText(/^LPO/)).toHaveAttribute('accept', '.pdf,.jpg,.jpeg,.png');
    });

    it('sends the optional Other files as other_documents[]', async () => {
        const post = vi.spyOn(client, 'apiPostForm').mockResolvedValue({ data: { id: 1 } });
        await open({ presetOrderId: 5 });
        fireEvent.change(screen.getByLabelText(/Warehouse/), { target: { value: '2' } });
        attachAll();

        const other = screen.getByLabelText(/^Other/);
        expect(other).not.toBeRequired();
        expect(other).toHaveAttribute('multiple');
        const a = new File(['a'], 'packing-list.pdf', { type: 'application/pdf' });
        const b = new File(['b'], 'photo.jpg', { type: 'image/jpeg' });
        fireEvent.change(other, { target: { files: [a, b] } });
        save();

        await waitFor(() => expect(post).toHaveBeenCalled());
        expect(post.mock.calls[0][1].getAll('other_documents[]')).toEqual([a, b]);
    });

    it('turns away more than five Other files', async () => {
        await open({ presetOrderId: 5 });

        const six = Array.from({ length: 6 }, (_, i) => new File(['x'], `extra-${i}.pdf`, { type: 'application/pdf' }));
        fireEvent.change(screen.getByLabelText(/^Other/), { target: { files: six } });

        expect(await screen.findByText('Attach at most 5 other files.')).toBeInTheDocument();
    });

    it('shows a server error for one Other file under the Other field', async () => {
        vi.spyOn(client, 'apiPostForm').mockRejectedValue({
            message: 'The other file field must be a file of type: pdf, jpg, jpeg, png.',
            errors: { 'other_documents.1': ['The other file field must be a file of type: pdf, jpg, jpeg, png.'] },
        });
        await open({ presetOrderId: 5 });
        fireEvent.change(screen.getByLabelText(/Warehouse/), { target: { value: '2' } });
        attachAll();
        save();

        await waitFor(() => expect(screen.getByLabelText(/^Other/)).toHaveAttribute('aria-invalid', 'true'));
    });

    /** Paperwork can follow the goods: the form saves with a document missing. */
    it('saves with only some of the documents attached', async () => {
        const post = vi.spyOn(client, 'apiPostForm').mockResolvedValue({ data: { id: 1 } });
        await open({ presetOrderId: 5 });
        fireEvent.change(screen.getByLabelText(/Warehouse/), { target: { value: '2' } });

        expect(screen.getByLabelText(/^Tax Invoice/)).not.toBeRequired();
        expect(screen.getByText(/All three are needed to confirm the GRN/)).toBeInTheDocument();
        attach(/^LPO/, 'lpo.pdf');
        // The real button, so the browser's own required check would stop it
        // if any document were still marked required.
        fireEvent.click(screen.getByRole('button', { name: 'Save GRN' }));

        await waitFor(() => expect(post).toHaveBeenCalled());
        const form = post.mock.calls[0][1];
        expect(form.get('lpo_document').name).toBe('lpo.pdf');
        expect(form.has('tax_invoice_document')).toBe(false);
    });

    it('turns away a file over 10 MB before uploading it', async () => {
        const post = vi.spyOn(client, 'apiPostForm').mockResolvedValue({ data: { id: 1 } });
        await open({ presetOrderId: 5 });

        const big = new File(['x'], 'scan.pdf', { type: 'application/pdf' });
        Object.defineProperty(big, 'size', { value: 11 * 1024 * 1024 });
        fireEvent.change(screen.getByLabelText(/^GRN/), { target: { files: [big] } });

        expect(await screen.findByText('This file is larger than 10 MB.')).toBeInTheDocument();
        expect(screen.getByLabelText(/^GRN/)).toHaveAttribute('aria-invalid', 'true');
        expect(post).not.toHaveBeenCalled();
    });

    it('reports a failure that carries no field errors', async () => {
        vi.spyOn(client, 'apiPostForm').mockRejectedValue({ message: 'Server unavailable.' });
        await open({ presetOrderId: 5 });
        fireEvent.change(screen.getByLabelText(/Warehouse/), { target: { value: '2' } });
        attachAll();

        save();

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
        // Answers a beat late, as a real network does, so a test that forgets
        // to wait for the options fails every time rather than only on a slow
        // CI runner (the PurchaseOrderModal tests do the same).
        vi.spyOn(client, 'apiGet').mockImplementation(
            () => new Promise((resolve) => setTimeout(() => resolve(LINKED), 20)),
        );
    });

    // The title renders before the order list arrives. Choosing order 6
    // before its <option> exists is silently dropped and the select stays
    // empty, which is how this block failed on a slow CI runner. Wait for
    // the options themselves.
    const open = async () => {
        render(<GrnModal onSaved={() => {}} onCancel={() => {}} />);
        await screen.findByText('New Goods Receipt Note');
        await screen.findByRole('option', { name: /PO-00006/ });
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
        const post = vi.spyOn(client, 'apiPostForm').mockResolvedValue({ data: { id: 1 } });
        await open();

        // Picked by hand first, then overruled by the order's company.
        fireEvent.change(screen.getByLabelText(/Purchase Order/), { target: { value: '6' } });
        fireEvent.change(screen.getByLabelText(/Warehouse/), { target: { value: '2' } });
        fireEvent.change(screen.getByLabelText(/Purchase Order/), { target: { value: '5' } });

        await waitFor(() => expect(screen.getByLabelText(/Warehouse/)).toHaveValue('3'));
        attachAll();

        save();

        await waitFor(() => expect(post).toHaveBeenCalled());
        expect(sent(post).warehouse_id).toBe('3');
    });
});
