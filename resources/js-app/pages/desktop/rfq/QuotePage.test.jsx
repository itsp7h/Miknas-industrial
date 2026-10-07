import { describe, it, expect, vi, beforeEach } from 'vitest';
import { StrictMode } from 'react';
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import DesktopQuotePage from './QuotePage';
import MobileQuotePage from '../../mobile/rfq/QuotePage';

/**
 * Both trees are driven from one file, the way LoginPage.test.jsx does it:
 * the supplier portal is the only screen an outside company sees, and the
 * desktop table and the mobile cards have to agree about what a quote is
 * even though they look nothing alike.
 *
 * `load` and `send` are injected rather than mocking the api client, so the
 * tests say what the server answered rather than how the fetch was shaped.
 */
const TOKEN = 'tok-123';

function openPayload(overrides = {}) {
    return {
        state: 'open',
        vat_rate: 10,
        confirm_code: 'AB12C',
        units: ['PCS', 'KG', 'BAG'],
        data: {
            token: TOKEN,
            supplier_name: 'Gulf Steel Co.',
            expires_at_text: '18 Sep 2026',
            submitted_at_text: null,
            request: { request_number: 'MPR-0042', company_name: 'Sitra Yard' },
            items: [
                { id: 7, description: 'Steel rod 12mm', unit: 'kg', quantity_required: 10 },
                { id: 9, description: 'Angle bar', unit: 'pcs', quantity_required: 4 },
            ],
        },
        ...overrides,
    };
}

describe.each([
    ['desktop', DesktopQuotePage],
    ['mobile', MobileQuotePage],
])('RFQ QuotePage (%s)', (_name, Page) => {
    let load;
    let send;

    beforeEach(() => {
        vi.restoreAllMocks();
        load = vi.fn().mockResolvedValue(openPayload());
        send = vi.fn().mockResolvedValue({
            state: 'submitted',
            data: { ...openPayload().data, submitted_at_text: '10 Sep 2026, 09:14' },
        });
    });

    const mount = () => render(<Page token={TOKEN} load={load} send={send} />);

    it('stars the fields the quote cannot be submitted without, and only those', async () => {
        const { container } = mount();
        await screen.findByText('MPR-0042');

        const starred = (label) => !!label.querySelector('span[aria-hidden="true"]')
            && label.textContent.includes('*');
        const labelOf = (id) => container.querySelector(`label[for="${id}"]`);

        expect(starred(labelOf('reference'))).toBe(true);
        expect(starred(labelOf('terms-cb'))).toBe(true);
        expect(starred(labelOf('confirm-input'))).toBe(true);
        expect(screen.getAllByText(/Unit Price \(BD\)/).every(starred)).toBe(true);

        expect(starred(labelOf('lead_time_days'))).toBe(false);
        expect(starred(labelOf('payment_terms'))).toBe(false);
        expect(starred(labelOf('notes'))).toBe(false);

        expect(screen.getByText(/are required\./)).toBeInTheDocument();
    });

    it('explains each field in a tooltip, on hover and on tap', async () => {
        mount();
        await screen.findByText('MPR-0042');

        const ref = screen.getByRole('button', { name: 'What is Ref?' });
        expect(screen.queryByRole('tooltip')).toBeNull();

        // A mouse hovering shows it, and leaving hides it.
        fireEvent.pointerEnter(ref.parentElement, { pointerType: 'mouse' });
        expect(screen.getByRole('tooltip')).toHaveTextContent(/printed on our purchase order/);
        fireEvent.pointerLeave(ref.parentElement, { pointerType: 'mouse' });
        expect(screen.queryByRole('tooltip')).toBeNull();

        // A tap pins it; a tap elsewhere closes it.
        fireEvent.click(ref);
        expect(screen.getByRole('tooltip')).toHaveTextContent(/printed on our purchase order/);
        fireEvent.pointerDown(document.body);
        expect(screen.queryByRole('tooltip')).toBeNull();

        // Escape closes a pinned one too.
        fireEvent.click(screen.getAllByRole('button', { name: 'What is Unit Price?' })[0]);
        expect(screen.getByRole('tooltip')).toHaveTextContent(/before VAT/);
        fireEvent.keyDown(document, { key: 'Escape' });
        expect(screen.queryByRole('tooltip')).toBeNull();

        // The optional fields are explained as well.
        for (const name of ['Delivery Time (days)', 'Payment Terms', 'Notes / Remarks', 'the confirmation code', 'the terms and conditions']) {
            expect(screen.getByRole('button', { name: `What is ${name}?` })).toBeInTheDocument();
        }
    });

    it('a tooltip tap does not tick the checkbox it sits beside', async () => {
        mount();
        await screen.findByText('MPR-0042');

        const tips = screen.getAllByRole('button', { name: /What is (N\/A|Item not available)\?/ });
        fireEvent.click(tips[0]);

        expect(screen.getByLabelText('Steel rod 12mm is not available')).not.toBeChecked();
        expect(screen.getByRole('tooltip')).toHaveTextContent(/cannot supply this item/);
    });

    /** Every field the supplier fills, so each test can start from a valid quote. */
    async function fillValidQuote() {
        mount();
        await screen.findByText('MPR-0042');

        fireEvent.change(screen.getByLabelText(/^Ref/), { target: { value: 'GS/Q/2026/118' } });
        fireEvent.change(screen.getByLabelText('Unit price for Steel rod 12mm'), { target: { value: '2' } });
        fireEvent.change(screen.getByLabelText('Unit price for Angle bar'), { target: { value: '3' } });
        fireEvent.click(screen.getByLabelText(/I have read and agree to the terms/));
        fireEvent.change(screen.getByLabelText(/Paste code here/), { target: { value: 'ab12c' } });
    }

    it('reads the invitation from the API and shows what is being quoted', async () => {
        mount();

        expect(await screen.findByText('MPR-0042')).toBeInTheDocument();
        expect(load).toHaveBeenCalledWith(`/rfq/${TOKEN}`);
        expect(screen.getByText('Sitra Yard')).toBeInTheDocument();
        expect(screen.getByText(/Gulf Steel Co\./)).toBeInTheDocument();
        expect(screen.getByText('Steel rod 12mm')).toBeInTheDocument();
        expect(screen.getByText('Angle bar')).toBeInTheDocument();
        expect(screen.getByText('AB12C')).toBeInTheDocument();
    });

    /** The Ref is asked for first, under the opening line, before any price. */
    it('asks for the Ref under the opening line, above the items', async () => {
        mount();
        await screen.findByText('MPR-0042');

        const ref = screen.getByLabelText(/^Ref/);
        const intro = screen.getByText(/Please enter your unit prices/);
        const firstPrice = screen.getByLabelText('Unit price for Steel rod 12mm');
        expect(intro.compareDocumentPosition(ref) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
        expect(ref.compareDocumentPosition(firstPrice) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });

    it('totals each line and the quote, applying VAT only where it is ticked', async () => {
        mount();
        await screen.findByText('MPR-0042');

        fireEvent.change(screen.getByLabelText('Unit price for Steel rod 12mm'), { target: { value: '2' } });
        fireEvent.change(screen.getByLabelText('Unit price for Angle bar'), { target: { value: '3' } });
        fireEvent.click(screen.getByLabelText('Apply VAT to Steel rod 12mm'));

        // 10 × 2 = 20, +10% VAT = 2; 4 × 3 = 12, VAT-free.
        expect(screen.getByText('BD 20.000')).toBeInTheDocument();
        expect(screen.getByText('BD 12.000')).toBeInTheDocument();
        expect(screen.getByText('BD 32.000')).toBeInTheDocument();
        expect(screen.getByText('BD 2.000')).toBeInTheDocument();
        expect(screen.getByText('BD 34.000')).toBeInTheDocument();
    });

    it('marking a line unavailable clears its price and drops it from the total', async () => {
        mount();
        await screen.findByText('MPR-0042');

        const price = screen.getByLabelText('Unit price for Steel rod 12mm');
        fireEvent.change(price, { target: { value: '2' } });
        fireEvent.change(screen.getByLabelText('Unit price for Angle bar'), { target: { value: '3' } });
        fireEvent.click(screen.getByLabelText('Apply VAT to Angle bar'));

        // 20 + 12 + 1.2 while both lines count.
        expect(screen.getByText('BD 33.200')).toBeInTheDocument();

        fireEvent.click(screen.getByLabelText('Steel rod 12mm is not available'));

        expect(price).toBeDisabled();
        expect(price).toHaveValue(null);
        expect(screen.getByText('Not available')).toBeInTheDocument();
        expect(screen.getByLabelText('Apply VAT to Steel rod 12mm')).toBeDisabled();
        // Only the Angle bar is left: 12 + 1.2.
        expect(screen.getByText('BD 13.200')).toBeInTheDocument();
    });

    it('will not submit until every line is priced, the terms are accepted and the code matches', async () => {
        mount();
        await screen.findByText('MPR-0042');

        const submit = screen.getByRole('button', { name: /Submit/ });
        expect(submit).toHaveAttribute('aria-disabled', 'true');
        // The Ref goes on the LPO, so it comes first.
        expect(screen.getByText('Please enter your quotation reference number (Ref).')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText(/^Ref/), { target: { value: '   ' } });
        expect(screen.getByText('Please enter your quotation reference number (Ref).')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText(/^Ref/), { target: { value: 'Q-118' } });
        expect(screen.getByText(/2 items still need a unit price/)).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Unit price for Steel rod 12mm'), { target: { value: '2' } });
        fireEvent.change(screen.getByLabelText('Unit price for Angle bar'), { target: { value: '3' } });
        expect(screen.getByText('Please accept the terms and conditions.')).toBeInTheDocument();

        fireEvent.click(screen.getByLabelText(/I have read and agree to the terms/));
        expect(screen.getByText('Enter the confirmation code exactly as shown.')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText(/Paste code here/), { target: { value: 'WRONG' } });
        expect(submit).toHaveAttribute('aria-disabled', 'true');

        fireEvent.change(screen.getByLabelText(/Paste code here/), { target: { value: 'ab12c' } });
        expect(submit).not.toHaveAttribute('aria-disabled');
    });

    /**
     * Pressing Submit with something missing sends nothing and outlines every
     * missing required field in red; each one clears as it is filled. Until
     * that first press the blank form is not marked at all.
     */
    it('outlines every missing required field once Submit is pressed', async () => {
        mount();
        await screen.findByText('MPR-0042');

        const ref = screen.getByLabelText(/^Ref/);
        const rodPrice = screen.getByLabelText('Unit price for Steel rod 12mm');
        const barPrice = screen.getByLabelText('Unit price for Angle bar');
        const terms = screen.getByLabelText(/I have read and agree to the terms/);
        const code = screen.getByLabelText(/Paste code here/);

        expect(ref).not.toHaveAttribute('aria-invalid');
        expect(rodPrice).not.toHaveAttribute('aria-invalid');

        fireEvent.change(rodPrice, { target: { value: '2' } });
        fireEvent.click(screen.getByRole('button', { name: /Submit/ }));

        expect(send).not.toHaveBeenCalled();
        for (const field of [ref, barPrice, terms, code]) {
            expect(field).toHaveAttribute('aria-invalid', 'true');
        }
        // The line already priced is not marked.
        expect(rodPrice).not.toHaveAttribute('aria-invalid');
        expect(screen.getByText('Please paste the confirmation code.')).toBeInTheDocument();

        fireEvent.change(ref, { target: { value: 'Q-118' } });
        expect(ref).not.toHaveAttribute('aria-invalid');

        // Unavailable counts as answered, so the red goes with it.
        fireEvent.click(screen.getByLabelText('Angle bar is not available'));
        expect(barPrice).not.toHaveAttribute('aria-invalid');

        fireEvent.change(code, { target: { value: 'WRONG' } });
        expect(code).toHaveAttribute('aria-invalid', 'true');
        expect(screen.getByText('The code does not match. Copy it exactly as shown.')).toBeInTheDocument();
    });

    it('outlines the quantity of a line quoted in another unit until it is filled', async () => {
        await fillValidQuote();

        fireEvent.change(screen.getByLabelText('Unit for Angle bar'), { target: { value: 'BAG' } });
        fireEvent.click(screen.getByRole('button', { name: /Submit/ }));

        expect(send).not.toHaveBeenCalled();
        const theirs = screen.getByLabelText('Your quantity in BAG, for Angle bar');
        expect(theirs).toHaveAttribute('aria-invalid', 'true');

        fireEvent.change(theirs, { target: { value: '2' } });
        expect(theirs).not.toHaveAttribute('aria-invalid');
    });

    it('a line marked unavailable counts as answered rather than unpriced', async () => {
        mount();
        await screen.findByText('MPR-0042');

        fireEvent.change(screen.getByLabelText(/^Ref/), { target: { value: 'Q-118' } });
        fireEvent.change(screen.getByLabelText('Unit price for Steel rod 12mm'), { target: { value: '2' } });
        fireEvent.click(screen.getByLabelText('Angle bar is not available'));
        fireEvent.click(screen.getByLabelText(/I have read and agree to the terms/));
        fireEvent.change(screen.getByLabelText(/Paste code here/), { target: { value: 'AB12C' } });

        expect(screen.getByRole('button', { name: /Submit/ })).not.toHaveAttribute('aria-disabled');
    });

    it('posts the quote keyed by item id and then shows the thank-you screen', async () => {
        await fillValidQuote();

        fireEvent.click(screen.getByRole('button', { name: /Submit/ }));

        await waitFor(() => expect(send).toHaveBeenCalledWith(`/rfq/${TOKEN}`, {
            terms: true,
            confirm_code: 'AB12C',
            reference: 'GS/Q/2026/118',
            lead_time_days: null,
            payment_terms: null,
            notes: null,
            items: [
                { id: 7, unit_price: 2, is_vatable: false, not_available: false, supplier_description: null },
                { id: 9, unit_price: 3, is_vatable: false, not_available: false, supplier_description: null },
            ],
        }));

        expect(await screen.findByText('Quote Received')).toBeInTheDocument();
        expect(screen.getByText('10 Sep 2026, 09:14')).toBeInTheDocument();
    });

    /**
     * A supplier who sells in bags picks BAG, says how many bags, and prices
     * per bag. What a bag holds in our unit is not theirs to say: we set it on
     * the GRN, so the portal neither asks for it nor sends one.
     */
    it('lets the supplier quote in their own unit without converting it', async () => {
        await fillValidQuote();

        fireEvent.change(screen.getByLabelText('Unit for Angle bar'), { target: { value: 'BAG' } });
        expect(screen.getByRole('button', { name: /Submit/ })).toHaveAttribute('aria-disabled', 'true');
        expect(screen.getByText(/One item is in a different unit/)).toBeInTheDocument();
        expect(screen.queryByLabelText(/holds, for Angle bar/)).not.toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Your quantity in BAG, for Angle bar'), { target: { value: '2' } });

        // Priced per bag: 2 × 3 = 6, beside 10 × 2 = 20 for the rod.
        expect(screen.getByText('BD 6.000')).toBeInTheDocument();
        expect(screen.getAllByText('BD 26.000').length).toBeGreaterThan(0);

        fireEvent.click(screen.getByRole('button', { name: /Submit/ }));

        await waitFor(() => expect(send).toHaveBeenCalled());
        expect(send.mock.calls[0][1].items).toEqual([
            { id: 7, unit_price: 2, is_vatable: false, not_available: false, supplier_description: null },
            {
                id: 9, unit_price: 3, is_vatable: false, not_available: false, supplier_description: null,
                supplier_unit: 'BAG', supplier_quantity: 2,
            },
        ]);
    });

    it('going back to our unit forgets their quantity', async () => {
        await fillValidQuote();

        fireEvent.change(screen.getByLabelText('Unit for Angle bar'), { target: { value: 'BAG' } });
        fireEvent.change(screen.getByLabelText('Your quantity in BAG, for Angle bar'), { target: { value: '2' } });
        fireEvent.change(screen.getByLabelText('Unit for Angle bar'), { target: { value: 'pcs' } });

        expect(screen.queryByLabelText('Your quantity in BAG, for Angle bar')).not.toBeInTheDocument();
        expect(screen.getByText('BD 12.000')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Submit/ })).not.toHaveAttribute('aria-disabled');
    });

    it('sends the logistics fields the supplier filled in', async () => {
        await fillValidQuote();

        fireEvent.change(screen.getByLabelText('Delivery Time (days)'), { target: { value: '14' } });
        fireEvent.change(screen.getByLabelText('Payment Terms'), { target: { value: '30 days net' } });
        fireEvent.change(screen.getByLabelText('Notes / Remarks'), { target: { value: 'Ex-works Sitra.' } });
        fireEvent.click(screen.getByRole('button', { name: /Submit/ }));

        await waitFor(() => expect(send).toHaveBeenCalledWith(`/rfq/${TOKEN}`, expect.objectContaining({
            lead_time_days: 14,
            payment_terms: '30 days net',
            notes: 'Ex-works Sitra.',
        })));
    });

    /** A renamed line rides along as supplier_description; an untouched one does not. */
    it('sends a renamed line as the supplier description and badges it', async () => {
        await fillValidQuote();

        fireEvent.click(screen.getAllByRole('button', { name: '✎ Edit' })[0]);
        fireEvent.change(screen.getByLabelText('Item description'), {
            target: { value: 'Steel rod 12mm (equivalent)' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Save item description' }));

        expect(screen.getByText('adjusted')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: /Submit/ }));

        await waitFor(() => expect(send).toHaveBeenCalledWith(`/rfq/${TOKEN}`, expect.objectContaining({
            items: [
                expect.objectContaining({ id: 7, supplier_description: 'Steel rod 12mm (equivalent)' }),
                expect.objectContaining({ id: 9, supplier_description: null }),
            ],
        })));
    });

    it('cancelling a rename restores the original name', async () => {
        mount();
        await screen.findByText('MPR-0042');

        fireEvent.click(screen.getAllByRole('button', { name: '✎ Edit' })[0]);
        fireEvent.change(screen.getByLabelText('Item description'), { target: { value: 'Something else' } });
        fireEvent.click(screen.getByRole('button', { name: 'Cancel editing item description' }));

        expect(screen.getByText('Steel rod 12mm')).toBeInTheDocument();
        expect(screen.queryByText('adjusted')).not.toBeInTheDocument();
    });

    it('shows a rejected confirmation code against the field and stays on the form', async () => {
        send.mockRejectedValue({
            errors: { confirm_code: ['Incorrect confirmation code. Please copy the code exactly as shown.'] },
        });
        await fillValidQuote();

        fireEvent.click(screen.getByRole('button', { name: /Submit/ }));

        expect(await screen.findByText(/Incorrect confirmation code/)).toBeInTheDocument();
        expect(screen.queryByText('Quote Received')).not.toBeInTheDocument();
    });

    it('falls back to a form-level message when the failure carries no field errors', async () => {
        send.mockRejectedValue({ message: 'This link is no longer valid.' });
        await fillValidQuote();

        fireEvent.click(screen.getByRole('button', { name: /Submit/ }));

        expect(await screen.findByRole('alert')).toHaveTextContent('This link is no longer valid.');
    });

    it('renders the expired screen instead of the form', async () => {
        load.mockResolvedValue({ state: 'expired', data: openPayload().data });
        mount();

        expect(await screen.findByText('Link Expired')).toBeInTheDocument();
        expect(screen.getByText(/Expired on 18 Sep 2026/)).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Submit/ })).not.toBeInTheDocument();
    });

    it('renders the thank-you screen for an invitation already quoted', async () => {
        load.mockResolvedValue({
            state: 'submitted',
            data: { ...openPayload().data, submitted_at_text: '09 Sep 2026, 16:02' },
        });
        mount();

        expect(await screen.findByText('Quote Received')).toBeInTheDocument();
        expect(screen.getByText(/Gulf Steel Co\./)).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Submit/ })).not.toBeInTheDocument();

        // company_name is the company, not the project — the one screen the
        // rename missed, because nothing asserted its label.
        expect(screen.getByText('Company')).toBeInTheDocument();
        expect(screen.getByText('Sitra Yard')).toBeInTheDocument();
        expect(screen.queryByText('Project')).not.toBeInTheDocument();
    });

    it('reports a failed read rather than an empty page', async () => {
        load.mockRejectedValue({ message: 'This quote request could not be loaded.' });
        mount();

        expect(await screen.findByText('Something went wrong')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Try again' })).toBeInTheDocument();
    });

    /** VAT is a global setting; at zero the column and the row disappear. */
    it('hides VAT entirely when the rate is zero', async () => {
        load.mockResolvedValue(openPayload({ vat_rate: 0 }));
        mount();
        await screen.findByText('MPR-0042');

        expect(screen.queryByLabelText('Apply VAT to Steel rod 12mm')).not.toBeInTheDocument();
        expect(screen.queryByText(/^VAT \(/)).not.toBeInTheDocument();
    });
});

describe('RFQ QuotePage (desktop specifics)', () => {
    it('lays the items out as a table with a running footer', async () => {
        render(
            <DesktopQuotePage
                token={TOKEN}
                load={vi.fn().mockResolvedValue(openPayload())}
                send={vi.fn()}
            />
        );
        await screen.findByText('MPR-0042');

        const table = screen.getByRole('table');
        expect(within(table).getByText('Unit Price (BD)')).toBeInTheDocument();
        expect(within(table).getByText('Grand Total:')).toBeInTheDocument();
        expect(within(table).getAllByRole('row')).toHaveLength(2 + 1 + 3);
    });
});

/**
 * rfq.jsx mounts the portal inside <StrictMode>, which double-invokes effects
 * in development. The loader guarded itself with a ref *and* a cleanup flag,
 * and the two cancelled out: the first mount's cleanup cleared the flag, the
 * second mount's ref check skipped the refetch, and the single response was
 * discarded — leaving the supplier on "Loading your quote request…" for ever.
 *
 * Every other test here renders the page bare, so none of them saw it. This
 * one mounts it the way the entry point does.
 */
describe.each([
    ['desktop', DesktopQuotePage],
    ['mobile', MobileQuotePage],
])('RFQ QuotePage (%s) under StrictMode', (_name, Page) => {
    it('leaves the loading state, fetching exactly once', async () => {
        const load = vi.fn().mockResolvedValue(openPayload());
        const send = vi.fn();

        render(
            <StrictMode>
                <Page token={TOKEN} load={load} send={send} />
            </StrictMode>
        );

        // The quote form appears — the payload was applied, not dropped.
        expect(await screen.findByText('Steel rod 12mm')).toBeInTheDocument();
        expect(screen.queryByText(/Loading your quote request/i)).not.toBeInTheDocument();

        // GET /rfq/{token} marks the invitation opened and issues the session's
        // confirmation code, so the double mount must not double-request it.
        expect(load).toHaveBeenCalledTimes(1);
    });
});