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
    id: 7, request_number: 'MPR-0007', project_name: 'Plant Expansion', stage: 'comparison',
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

    // Once an item is awarded, no other supplier on it can be — the Blade page
    // hid their buttons rather than letting the request fail.
    it('offers no other Award button once an item is awarded', async () => {
        const awardedRows = [
            { ...row('Gulf Steel', 10), line: { ...row('Gulf Steel', 10).line, is_awarded: true, award_reason: 'Best terms', awarded_by: 'Zoe', awarded_at: '01 Sep 2026, 10:00' } },
            { ...row('Zenith', 9), is_min: true },
        ];
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: workspace({
                items: [{
                    id: 11, description: 'Steel plate', quantity: 2, unit: 'PCS',
                    badge: { background: '#dcfce7', colour: '#15803d', label: '✓ Awarded to Gulf Steel' },
                    has_award: true, rows: awardedRows,
                }],
            }),
        });
        wrap();

        expect(await screen.findByText('✓ AWARDED')).toBeInTheDocument();
        expect(screen.queryByText('Award →')).not.toBeInTheDocument();
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
