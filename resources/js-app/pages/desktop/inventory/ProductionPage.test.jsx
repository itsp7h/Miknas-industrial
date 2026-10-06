import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import ProductionPage from './ProductionPage';
import MobileProductionPage from '../../mobile/inventory/ProductionPage';
import { ToastProvider } from '../../../components/ui/Toast';
import { AccessProvider } from '../../../layouts/AccessContext';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const wrap = (ui, access = { isAdmin: true }) => render(
    <MemoryRouter><AccessProvider {...access}><ToastProvider>{ui}</ToastProvider></AccessProvider></MemoryRouter>
);

const RUNS = [
    {
        id: 1, run_number: 'PRD-00001', item_code: 'ITEM-00001', item_name: 'SUPERBOND F5 White 25kg', unit_of_measure: 'BAG',
        warehouse_name: 'Main', quantity: '40.00', production_date: '2026-10-01', total_cost: '200.000', unit_cost: '5.0000',
        created_by_name: 'Admin', lines: [{ id: 1, item_name: 'Cement', unit_of_measure: 'KG', warehouse_name: 'Main', quantity: '400.00', unit_cost: '0.500', line_cost: '200.000' }],
    },
    {
        id: 2, run_number: 'PRD-00002', item_code: 'ITEM-00001', item_name: 'SUPERBOND F5 White 25kg', unit_of_measure: 'BAG',
        warehouse_name: 'Main', quantity: '100.00', production_date: '2026-10-05', total_cost: '525.000', unit_cost: '5.2500',
        created_by_name: 'Admin', lines: [],
    },
];

const OPTIONS = {
    finished_goods: [
        {
            id: 1, item_code: 'ITEM-00001', item_name: 'SUPERBOND F5 White 25kg', unit_of_measure: 'BAG', cost_price: '9.00',
            recipe: [{ raw_material_id: 10, quantity_required: '10.00' }, { raw_material_id: 11, quantity_required: '2.50' }],
        },
        { id: 2, item_code: 'ITEM-00002', item_name: 'SUPERBOND F5 Grey 25kg', unit_of_measure: 'BAG', cost_price: '0', recipe: [] },
    ],
    raw_materials: [
        { id: 10, item_code: 'RM-1', item_name: 'Cement', unit_of_measure: 'KG', cost_price: '0.50' },
        { id: 11, item_code: 'RM-2', item_name: 'Sand', unit_of_measure: 'KG', cost_price: '0.10' },
    ],
    warehouses: [{ id: 1, code: 'WH-1', name: 'Main' }, { id: 2, code: 'WH-2', name: 'Store' }],
    stock: [
        { item_id: 10, warehouse_id: 1, quantity: '1000.00' },
        { item_id: 11, warehouse_id: 2, quantity: '500.00' },
    ],
};

function mockApi(runs = RUNS) {
    return vi.spyOn(client, 'apiGet').mockImplementation((url) => Promise.resolve(
        url.endsWith('/form-options') ? OPTIONS : { data: runs }
    ));
}

async function openForm() {
    fireEvent.click(await screen.findByText('+ New Production Run'));
    return screen.findByLabelText(/Finished good/);
}

const lineQuantities = () => screen.getAllByLabelText('Quantity used').map((input) => input.value);

describe('Inventory → Production', () => {
    beforeEach(() => vi.restoreAllMocks());

    it('lists runs newest first, with what each cost', async () => {
        mockApi();
        wrap(<ProductionPage />);

        await screen.findByText('PRD-00002');
        const rows = screen.getAllByRole('row').slice(1);
        expect(rows[0]).toHaveTextContent('PRD-00002');
        expect(rows[0]).toHaveTextContent('BD 525.000');
        expect(rows[0]).toHaveTextContent('BD 5.250');
        expect(rows[1]).toHaveTextContent('PRD-00001');
    });

    it('opens a run to show the materials it used', async () => {
        mockApi();
        wrap(<ProductionPage />);

        fireEvent.click((await screen.findAllByText('View'))[1]);
        expect(await screen.findByText('Production Run PRD-00001')).toBeInTheDocument();
        expect(screen.getByText('Cement')).toBeInTheDocument();
        expect(screen.getByText('400.00 KG')).toBeInTheDocument();
    });

    it('searches by a material the run used', async () => {
        mockApi();
        wrap(<ProductionPage />);
        await screen.findByText('PRD-00002');

        fireEvent.change(screen.getByLabelText('Search production runs'), { target: { value: 'cement' } });
        expect(screen.getByText('1 of 2 runs')).toBeInTheDocument();
        expect(screen.queryByText('PRD-00002')).not.toBeInTheDocument();
    });

    it('fills the materials in from the recipe, scaled, from where each is held', async () => {
        mockApi();
        wrap(<ProductionPage />);
        fireEvent.change(await openForm(), { target: { value: '1' } });
        fireEvent.change(screen.getByLabelText(/Quantity made/), { target: { value: '100' } });

        expect(lineQuantities()).toEqual(['1000', '250']);
        // Sand is only held in Store, so that is where it is taken from.
        const froms = screen.getAllByLabelText('From warehouse').map((select) => select.value);
        expect(froms).toEqual(['1', '2']);
        // 1000 × 0.50 + 250 × 0.10
        expect(screen.getByTestId('run-total')).toHaveTextContent('BD 525.000');
        expect(screen.getByTestId('run-per-unit')).toHaveTextContent('BD 5.250');
        expect(screen.getByText(/Saving sets SUPERBOND F5 White 25kg.s cost price to BD 5.250/)).toBeInTheDocument();
    });

    it('rescales recipe lines with the quantity, but not one edited by hand', async () => {
        mockApi();
        wrap(<ProductionPage />);
        fireEvent.change(await openForm(), { target: { value: '1' } });
        fireEvent.change(screen.getByLabelText(/Quantity made/), { target: { value: '100' } });

        fireEvent.change(screen.getAllByLabelText('Quantity used')[1], { target: { value: '300' } });
        fireEvent.change(screen.getByLabelText(/Quantity made/), { target: { value: '50' } });

        expect(lineQuantities()).toEqual(['500', '300']);
    });

    it('says when a product has no recipe yet', async () => {
        mockApi();
        wrap(<ProductionPage />);
        fireEvent.change(await openForm(), { target: { value: '2' } });

        expect(screen.getByText(/No recipe for this product yet/)).toBeInTheDocument();
        expect(screen.getAllByTestId('run-line')).toHaveLength(1);
    });

    it('records the run and puts a refused line’s reason on that line', async () => {
        mockApi();
        const post = vi.spyOn(client, 'apiPost').mockRejectedValue({
            status: 422, message: 'Invalid', errors: { 'lines.1.quantity': ['Only 500.00 KG of Sand on hand in Store.'] },
        });
        wrap(<ProductionPage />);
        fireEvent.change(await openForm(), { target: { value: '1' } });
        fireEvent.change(screen.getByLabelText(/Quantity made/), { target: { value: '100' } });
        fireEvent.change(screen.getByLabelText(/Into warehouse/), { target: { value: '1' } });
        fireEvent.click(screen.getByText('Record Production'));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/inventory/production', expect.objectContaining({
            item_id: '1', warehouse_id: '1', quantity: '100',
            lines: [
                { item_id: '10', warehouse_id: '1', quantity: '1000' },
                { item_id: '11', warehouse_id: '2', quantity: '250' },
            ],
        })));
        const sandLine = screen.getAllByTestId('run-line')[1];
        expect(await within(sandLine).findByText('Only 500.00 KG of Sand on hand in Store.')).toBeInTheDocument();
    });

    it('adds the saved run to the list and says so', async () => {
        mockApi([]);
        vi.spyOn(client, 'apiPost').mockResolvedValue({ data: RUNS[1], message: 'PRD-00002 recorded — stock updated.' });
        wrap(<ProductionPage />);
        fireEvent.change(await openForm(), { target: { value: '1' } });
        fireEvent.change(screen.getByLabelText(/Quantity made/), { target: { value: '100' } });
        fireEvent.change(screen.getByLabelText(/Into warehouse/), { target: { value: '1' } });
        fireEvent.click(screen.getByText('Record Production'));

        expect(await screen.findByText('PRD-00002 recorded — stock updated.')).toBeInTheDocument();
        expect(screen.getByText('PRD-00002')).toBeInTheDocument();
        expect(screen.queryByText('New Production Run')).not.toBeInTheDocument();
    });

    it('edits and saves a product’s recipe', async () => {
        mockApi();
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({
            data: { product_id: 2, recipe: [{ raw_material_id: 10, quantity_required: '12.00' }] },
            message: 'Recipe for SUPERBOND F5 Grey 25kg saved.',
        });
        wrap(<ProductionPage />);

        fireEvent.click(await screen.findByText('Recipes'));
        fireEvent.change(await screen.findByLabelText('Finished good'), { target: { value: '2' } });
        fireEvent.change(screen.getByLabelText('Raw material'), { target: { value: '10' } });
        fireEvent.change(screen.getByLabelText('Quantity per unit'), { target: { value: '12' } });
        fireEvent.click(screen.getByText('Save Recipe'));

        await waitFor(() => expect(put).toHaveBeenCalledWith('/inventory/production/recipes/2', {
            lines: [{ raw_material_id: '10', quantity_required: '12' }],
        }));
        expect(await screen.findByText('Recipe for SUPERBOND F5 Grey 25kg saved.')).toBeInTheDocument();
    });

    // CLAUDE.md #14: shown, greyed, with the reason — and nothing fetched.
    it('greys both actions, with the reason, for someone who may only view', async () => {
        const get = mockApi();
        wrap(<ProductionPage />, { permissions: ['production.view'] });

        const record = await screen.findByText('+ New Production Run');
        expect(record).toBeDisabled();
        expect(record).toHaveAttribute('title', 'You do not have permission to record production');
        expect(screen.getByText('Recipes')).toHaveAttribute('title', 'You do not have permission to edit recipes');
        expect(get).not.toHaveBeenCalledWith('/inventory/production/form-options');
    });

    it('lets someone who records runs leave the recipes alone', async () => {
        mockApi();
        wrap(<ProductionPage />, { permissions: ['production.view', 'production.create'] });

        expect(await screen.findByText('+ New Production Run')).toBeEnabled();
        expect(screen.getByText('Recipes')).toBeDisabled();
    });
});

describe('Inventory → Production, mobile', () => {
    beforeEach(() => vi.restoreAllMocks());

    it('shows each run as a card that opens it', async () => {
        mockApi();
        wrap(<MobileProductionPage />);

        fireEvent.click(await screen.findByText('PRD-00001'));
        expect(await screen.findByText('Production Run PRD-00001')).toBeInTheDocument();
    });

    it('stacks the run form and still fills it from the recipe', async () => {
        mockApi();
        wrap(<MobileProductionPage />);
        fireEvent.change(await openForm(), { target: { value: '1' } });
        fireEvent.change(screen.getByLabelText(/Quantity made/), { target: { value: '10' } });

        expect(lineQuantities()).toEqual(['100', '25']);
    });

    it('greys the full-width button for someone who may only view', async () => {
        mockApi();
        wrap(<MobileProductionPage />, { permissions: ['production.view'] });

        const record = await screen.findByText('+ New Production Run');
        expect(record).toBeDisabled();
        expect(record).toHaveStyle({ width: '100%', cursor: 'not-allowed' });
    });
});
