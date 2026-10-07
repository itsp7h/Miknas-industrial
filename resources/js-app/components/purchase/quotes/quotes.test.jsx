import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import AwardedSuppliers from './AwardedSuppliers';
import QuoteWorkspace from './QuoteWorkspace';
import { ToastProvider } from '../../ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => {}, stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const row = (supplier, price, overrides = {}) => ({
    supplier, lead_time_days: 7, payment_terms: '30 days', notes: null, is_min: false,
    line: {
        id: price * 10, unit_price: price, total_price: price * 2, not_available: false,
        is_vatable: false, supplier_description: null, is_awarded: false,
        award_reason: null, awarded_at: null, awarded_by: null,
    },
    ...overrides,
});

const workspace = (overrides = {}) => ({
    id: 7, request_number: 'MPR-0007', company_name: 'Plant Expansion', stage: 'comparison',
    quote_count: 2,
    items: [{
        id: 11, description: 'Steel plate', quantity: 2, unit: 'PCS',
        badge: { background: '#dbeafe', colour: '#1d4ed8', label: '2 suppliers competing' },
        has_award: false,
        rows: [row('Gulf Steel', 10), { ...row('Zenith', 9), is_min: true }],
    }],
    awards: [], fully_awarded: false,
    subtotal: 18, vat_rate: 10, vat_amount: 0, grand_total: 18, unresolved_items: 0,
    permissions: { award: true },
    ...overrides,
});

const wrap = () => render(
    <MemoryRouter><ToastProvider><QuoteWorkspace requestId={7} /></ToastProvider></MemoryRouter>
);

describe('QuoteWorkspace', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: workspace() });
    });

    it('shows the request, the quote count and each supplier’s offer', async () => {
        wrap();

        expect(await screen.findByText('MPR-0007')).toBeInTheDocument();
        expect(screen.getByText('2 quotes received')).toBeInTheDocument();
        expect(screen.getByText('Steel plate')).toBeInTheDocument();
        expect(screen.getByText('Gulf Steel')).toBeInTheDocument();
        expect(screen.getByText('2 suppliers competing')).toBeInTheDocument();
    });

    /** A line quoted in the supplier's unit says so, and can be corrected before awarding. */
    it('shows a line quoted in the supplier’s unit and corrects its conversion', async () => {
        const bagged = row('Gulf Steel', 0.48);
        bagged.line = {
            ...bagged.line, id: 55, quantity: 100, total_price: 48,
            supplier_unit: 'BAG', unit_factor: 25, supplier_quantity: 4, supplier_unit_price: 12,
        };
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: workspace({
            items: [{ ...workspace().items[0], quantity: 100, rows: [bagged] }],
        }) });
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({ data: workspace(), message: 'Steel plate: 1 BAG = 20 PCS.' });
        wrap();

        expect(await screen.findByText(/Quoted in/)).toHaveTextContent('Quoted in BAG: 4 BAG @ BD 12.000 · 1 BAG = 25 PCS = 100 PCS');

        fireEvent.click(screen.getByRole('button', { name: '✎ Edit' }));
        fireEvent.change(screen.getByLabelText('How many PCS one BAG holds'), { target: { value: '20' } });
        fireEvent.change(screen.getByLabelText('Quantity in BAG'), { target: { value: '5' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save Conversion' }));

        await waitFor(() => expect(put).toHaveBeenCalledWith(
            '/purchase/requests/7/quotes/items/55/unit', { unit_factor: 20, supplier_quantity: 5 },
        ));
    });

    it('offers the unit correction disabled to someone who may not award', async () => {
        const bagged = row('Gulf Steel', 0.48);
        bagged.line = { ...bagged.line, quantity: 100, supplier_unit: 'BAG', unit_factor: 25, supplier_quantity: 4, supplier_unit_price: 12 };
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: workspace({
            items: [{ ...workspace().items[0], rows: [bagged] }], permissions: { award: false },
        }) });
        wrap();

        const edit = await screen.findByRole('button', { name: '✎ Edit' });
        expect(edit).toBeDisabled();
        expect(edit).toHaveAttribute('title', 'You do not have permission to change a quote');
    });

    it('shows each supplier’s Ref beside their offer', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: workspace({
            items: [{ ...workspace().items[0], rows: [row('Gulf Steel', 10, { reference: 'GS/Q/2026/118' }), row('Zenith', 9)] }],
        }) });
        wrap();

        expect(await screen.findByText(/Ref GS\/Q\/2026\/118 · 7 days · 30 days/)).toBeInTheDocument();
    });

    // Three decimals and a BD prefix throughout, as Blade had it.
    it('formats money the way the Blade page did', async () => {
        wrap();

        await screen.findByText('Steel plate');
        expect(screen.getByText('BD 9.000')).toBeInTheDocument();
        expect(screen.getByText('LOWEST')).toBeInTheDocument();
    });

    it('says the total is based on the lowest offer until awards are made', async () => {
        wrap();

        await screen.findByText('Steel plate');
        expect(screen.getByText(/lowest offer otherwise/)).toBeInTheDocument();
        // Zenith's line total is also 18.000; the grand total is the large one.
        const totals = screen.getAllByText('BD 18.000');
        expect(totals.some((node) => node.style.fontSize === '24px')).toBe(true);
    });

    it('shows the VAT breakdown only when there is VAT', async () => {
        wrap();
        await screen.findByText('Steel plate');
        expect(screen.queryByText(/Subtotal/)).not.toBeInTheDocument();

        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: workspace({ vat_amount: 1.8, grand_total: 19.8 }),
        });
        const { unmount } = wrap();
        expect(await screen.findByText(/VAT \(10%\)/)).toBeInTheDocument();
        unmount();
    });

    it('notes how many items nobody quoted', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: workspace({ unresolved_items: 2 }) });
        wrap();

        expect(await screen.findByText(/2 item\(s\) with no quotes excluded/)).toBeInTheDocument();
    });

    it('asks for a reason before awarding, and refuses a token one', async () => {
        const post = vi.spyOn(client, 'apiPost');
        wrap();

        fireEvent.click((await screen.findAllByText('Award →'))[1]);
        expect(await screen.findByText('Award Item')).toBeInTheDocument();

        fireEvent.click(screen.getByText('Confirm Award'));
        expect(await screen.findByText('Please give a reason of at least 5 characters.')).toBeInTheDocument();
        expect(post).not.toHaveBeenCalled();
    });

    it('posts the award with its reason', async () => {
        const post = vi.spyOn(client, 'apiPost').mockResolvedValue({
            data: workspace({ fully_awarded: true }), message: 'Steel plate awarded to Zenith.',
        });
        wrap();

        fireEvent.click((await screen.findAllByText('Award →'))[1]);
        fireEvent.change(await screen.findByLabelText(/Reason for selection/), {
            target: { value: 'Cheapest and quickest' },
        });
        fireEvent.click(screen.getByText('Confirm Award'));

        await waitFor(() => expect(post).toHaveBeenCalledWith(
            '/purchase/requests/7/quotes/items/90/award',
            { award_reason: 'Cheapest and quickest' }
        ));
        expect(await screen.findByText('Steel plate awarded to Zenith.')).toBeInTheDocument();
    });

    it('announces when everything is awarded', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: workspace({ fully_awarded: true }) });
        wrap();

        expect(await screen.findByText(/Ready to issue LPO/)).toBeInTheDocument();
    });

    // A supplier invited late can come in cheaper: the other lines on an
    // awarded item offer to take the award over, and the dialog says from whom.
    it('offers to move the award to another supplier once an item is awarded', async () => {
        const awardedRows = [
            { ...row('Gulf Steel', 10), line: { ...row('Gulf Steel', 10).line, is_awarded: true, award_reason: 'Best terms', awarded_by: 'Zoe', awarded_at: '01 Sep 2026, 10:00' } },
            { ...row('Zenith', 9), is_min: true },
        ];
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: workspace({
                items: [{
                    id: 11, description: 'Steel plate', quantity: 2, unit: 'PCS',
                    badge: { background: '#dcfce7', colour: '#15803d', label: '✓ Awarded to Gulf Steel' },
                    has_award: true, awarded_supplier: 'Gulf Steel', rows: awardedRows,
                }],
            }),
        });
        wrap();

        expect(await screen.findByText('✓ AWARDED')).toBeInTheDocument();
        expect(screen.queryByText('Award →')).not.toBeInTheDocument();

        fireEvent.click(screen.getByText('Award instead →'));
        expect(await screen.findByText(/Confirming moves the award to Zenith/)).toBeInTheDocument();
        expect(screen.getByText('Gulf Steel', { selector: 'strong' })).toBeInTheDocument();
    });

    it('shows the award reason and offers to remove it', async () => {
        const awardedLine = { ...row('Gulf Steel', 10).line, is_awarded: true, award_reason: 'Best terms', awarded_by: 'Zoe', awarded_at: '01 Sep 2026, 10:00' };
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: workspace({
                items: [{
                    id: 11, description: 'Steel plate', quantity: 2, unit: 'PCS',
                    badge: { background: '#dcfce7', colour: '#15803d', label: '✓ Awarded to Gulf Steel' },
                    has_award: true, rows: [{ ...row('Gulf Steel', 10), line: awardedLine }],
                }],
            }),
        });
        const post = vi.spyOn(client, 'apiPost').mockResolvedValue({ data: workspace(), message: 'Steel plate unawarded from Gulf Steel. Pick a new supplier.' });
        wrap();

        fireEvent.click(await screen.findByText('✓ AWARDED'));
        expect(await screen.findByText('Award Details')).toBeInTheDocument();
        expect(screen.getByText('Best terms')).toBeInTheDocument();
        expect(screen.getByText('Zoe')).toBeInTheDocument();

        fireEvent.click(screen.getByText('Remove Award'));
        await waitFor(() => expect(post).toHaveBeenCalledWith('/purchase/requests/7/quotes/items/100/unaward'));
    });

    it('hides the award controls from someone who cannot award', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: workspace({ permissions: { award: false } }),
        });
        wrap();

        await screen.findByText('Steel plate');
        expect(screen.queryByText('Award →')).not.toBeInTheDocument();
    });

    it('marks a line the supplier could not supply', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: workspace({
                items: [{
                    id: 11, description: 'Steel plate', quantity: 2, unit: 'PCS',
                    badge: { background: '#f1f5f9', colour: '#64748b', label: 'No quotes yet' },
                    has_award: false,
                    rows: [
                        { ...row('Gulf Steel', 10), line: { ...row('Gulf Steel', 10).line, not_available: true } },
                        { ...row('Zenith', 9), line: null },
                    ],
                }],
            }),
        });
        wrap();

        expect(await screen.findByText('Not available')).toBeInTheDocument();
        expect(screen.getByText('Not quoted')).toBeInTheDocument();
    });

    it('says so plainly when no quotes have arrived', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: workspace({ quote_count: 0, items: [] }),
        });
        wrap();

        expect(await screen.findByText('No quotes yet')).toBeInTheDocument();
        expect(screen.queryByText('Comparison & Award')).not.toBeInTheDocument();
    });

    it('switches to the awarded tab', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: workspace({
                awards: [
                    { id: 1, item: 'Steel plate', quantity: 2, unit: 'PCS', supplier: 'Gulf Steel', unit_price: 10, total_price: 20, reason: 'Best terms', awarded_at: '01 Sep 2026, 10:00', awarded_by: 'Zoe' },
                ],
            }),
        });
        wrap();

        fireEvent.click(await screen.findByText('Awarded Suppliers'));
        expect(await screen.findByText('✓ Gulf Steel')).toBeInTheDocument();
    });
});

const SUPPLIERS = [
    { quote_id: 41, supplier: 'Yousif Dhneem', quoted: 6, awarded: 0, held_elsewhere: 2, total: 38.346 },
    { quote_id: 42, supplier: 'Gulf Steel', quoted: 2, awarded: 2, held_elsewhere: 0, total: 20 },
];

describe('QuoteWorkspace — award all', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: workspace({ suppliers: SUPPLIERS }) });
    });

    it('offers each supplier’s whole quote, with what it would change', async () => {
        wrap();

        const rows = await screen.findAllByTestId('award-all-row');
        expect(rows[0]).toHaveTextContent('Yousif Dhneem');
        expect(rows[0]).toHaveTextContent('6 items quoted · BD 38.346 before VAT');
        expect(rows[0]).toHaveTextContent('2 awarded to others');
    });

    it('links each supplier’s own quotation where they attached one', async () => {
        const doc = { name: 'Yousif Q-77.pdf', size: 1200, url: '/purchase/quotes/5/document' };
        client.apiGet.mockResolvedValue({ data: workspace({
            suppliers: [{ ...SUPPLIERS[0], document: doc }, { ...SUPPLIERS[1], document: null }],
            items: [{ ...workspace().items[0], rows: [row('Gulf Steel', 10, { document: doc }), row('Zenith', 9)] }],
        }) });
        wrap();

        const rows = await screen.findAllByTestId('award-all-row');
        const link = screen.getByRole('link', { name: '📎 Their quotation' });
        expect(rows[0]).toContainElement(link);
        expect(link).toHaveAttribute('href', '/purchase/quotes/5/document');
        expect(link).toHaveAttribute('target', '_blank');
        expect(rows[1]).not.toHaveTextContent('Their quotation');

        expect(screen.getByRole('link', { name: "Open Gulf Steel's quotation" })).toHaveAttribute('href', '/purchase/quotes/5/document');
        expect(screen.queryByRole('link', { name: "Open Zenith's quotation" })).not.toBeInTheDocument();
    });

    it('says when a supplier offers a different quantity from the one asked', async () => {
        const offering = (supplier, price, quantity) => {
            const base = row(supplier, price);

            return { ...base, line: { ...base.line, quantity } };
        };
        client.apiGet.mockResolvedValue({ data: workspace({
            suppliers: SUPPLIERS,
            items: [{ ...workspace().items[0], quantity: '2.00', rows: [offering('Gulf Steel', 10, 1), offering('Zenith', 9, 2)] }],
        }) });
        wrap();

        expect(await screen.findByText('Offers 1 of 2 PCS')).toBeInTheDocument();
        expect(screen.queryByText(/Offers 2 of 2/)).not.toBeInTheDocument();
    });

    it('greys it, with the reason, once everything they quoted is theirs', async () => {
        wrap();

        const button = (await screen.findAllByTestId('award-all-row'))[1].querySelector('button');
        expect(button).toBeDisabled();
        expect(button).toHaveAttribute('title', 'Everything Gulf Steel quoted is already awarded to them');
    });

    it('greys it for someone who may not award', async () => {
        client.apiGet.mockResolvedValue({ data: workspace({ suppliers: SUPPLIERS, permissions: { award: false } }) });
        wrap();

        const button = (await screen.findAllByTestId('award-all-row'))[0].querySelector('button');
        expect(button).toBeDisabled();
        expect(button).toHaveAttribute('title', 'You do not have permission to award on this request');
    });

    it('warns that awards held elsewhere move, and asks for a real reason', async () => {
        const post = vi.spyOn(client, 'apiPost');
        wrap();

        fireEvent.click((await screen.findAllByText('Award all'))[0]);
        expect(await screen.findByText('Award all to Yousif Dhneem')).toBeInTheDocument();
        expect(screen.getByText(/2 of them are awarded to another supplier/)).toBeInTheDocument();

        fireEvent.click(screen.getByText('Award 6 items'));
        expect(await screen.findByText('Please give a reason of at least 5 characters.')).toBeInTheDocument();
        expect(post).not.toHaveBeenCalled();
    });

    it('awards the whole quote with one reason', async () => {
        const post = vi.spyOn(client, 'apiPost').mockResolvedValue({
            data: workspace({ suppliers: SUPPLIERS, fully_awarded: true }),
            message: '6 items awarded to Yousif Dhneem (2 moved from Gulf Steel).',
        });
        wrap();

        fireEvent.click((await screen.findAllByText('Award all'))[0]);
        fireEvent.change(await screen.findByLabelText(/Reason for selection/), { target: { value: 'Only supplier quoted' } });
        fireEvent.click(screen.getByText('Award 6 items'));

        await waitFor(() => expect(post).toHaveBeenCalledWith(
            '/purchase/requests/7/quotes/41/award-all', { award_reason: 'Only supplier quoted' }
        ));
        expect(await screen.findByText('6 items awarded to Yousif Dhneem (2 moved from Gulf Steel).')).toBeInTheDocument();
        expect(screen.queryByText('Award all to Yousif Dhneem')).not.toBeInTheDocument();
    });

    it('offers just the rest when some of a quote is already theirs', async () => {
        client.apiGet.mockResolvedValue({ data: workspace({ suppliers: [{ ...SUPPLIERS[0], awarded: 4, held_elsewhere: 0 }] }) });
        wrap();

        expect(await screen.findByText('Award remaining 2')).toBeEnabled();
    });
});

describe('AwardedSuppliers', () => {
    it('groups awards by supplier with each one’s total', () => {
        const awards = [
            { id: 1, item: 'Steel plate', quantity: 2, unit: 'PCS', supplier: 'Gulf Steel', unit_price: 10, total_price: 20, reason: 'Best terms', awarded_at: null, awarded_by: null },
            { id: 2, item: 'Angle bar', quantity: 3, unit: 'PCS', supplier: 'Gulf Steel', unit_price: 5, total_price: 15, reason: null, awarded_at: null, awarded_by: null },
            { id: 3, item: 'Bolts', quantity: 10, unit: 'PCS', supplier: 'Zenith', unit_price: 1, total_price: 10, reason: null, awarded_at: null, awarded_by: null },
        ];
        render(<AwardedSuppliers awards={awards} />);

        expect(screen.getByText('✓ Gulf Steel')).toBeInTheDocument();
        expect(screen.getByText('BD 35.000')).toBeInTheDocument();
        expect(screen.getByText('✓ Zenith')).toBeInTheDocument();
        // The awarded total across suppliers is what the LPOs will come to.
        expect(screen.getByText('BD 45.000')).toBeInTheDocument();
    });

    it('says so when nothing has been awarded', () => {
        render(<AwardedSuppliers awards={[]} />);
        expect(screen.getByText('No items have been awarded yet.')).toBeInTheDocument();
    });
});
