import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import DesktopGeneralSettingsPage from './GeneralSettingsPage';
import MobileGeneralSettingsPage from '../../mobile/settings/GeneralSettingsPage';
import { ToastProvider } from '../../../components/ui/Toast';
import { NAV_GROUPS, visibleGroups } from '../../../layouts/navItems';
import { previewFor, shortYear } from '../../../components/settings/documentNumbering/useDocumentNumbering';
import * as client from '../../../api/client';

const YY = shortYear();

const PAYLOAD = {
    data: [
        { id: 1, name: 'Miknas Industrial', is_active: true, lpo_code: 'MI', mpr_code: 'MPR', next_number: `MI-LPO-${YY}-0004`, next_mpr_number: `MI-MPR-${YY}-0007` },
        { id: 2, name: 'Steel Tech', is_active: true, lpo_code: 'ST', mpr_code: 'MPR', next_number: `ST-LPO-${YY}-0001`, next_mpr_number: `ST-MPR-${YY}-0001` },
    ],
};

const WAREHOUSE_PAYLOAD = {
    data: [
        { id: 1, name: 'Miknas Industrial', is_active: true, warehouse_id: 1, warehouse_name: 'Askar' },
        { id: 2, name: 'Steel Tech', is_active: true, warehouse_id: null, warehouse_name: null },
    ],
    warehouses: [
        { id: 1, code: 'WH-ASKAR', name: 'Askar', is_active: true },
        { id: 2, code: 'WH-HIDD', name: 'Hidd', is_active: true },
    ],
};

/** The page holds two cards, so the mock answers by endpoint, not by turn. */
const getFor = (overrides = {}) => (url) => {
    if (url === '/settings/company-warehouses') {
        return Promise.resolve(overrides.warehouses ?? WAREHOUSE_PAYLOAD);
    }

    return Promise.resolve(overrides.numbering ?? PAYLOAD);
};

/** The Document Numbering card, since the page now holds two of them. */
const numberingCard = () => screen.getByText('Document Numbering').closest('div');

const renderPage = (Page = DesktopGeneralSettingsPage) =>
    render(<ToastProvider><Page /></ToastProvider>);

describe('the Settings tab', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockImplementation(getFor());
    });

    it('lists each company with its code and the number it will issue next', async () => {
        renderPage();

        expect(await screen.findByText('Document Numbering')).toBeInTheDocument();
        expect(screen.getByLabelText('Miknas Industrial document code')).toHaveValue('MI');
        expect(screen.getByLabelText('Steel Tech document code')).toHaveValue('ST');
        // Not an example — the sequence each company has actually reached,
        // and the two documents count separately.
        expect(screen.getByText(`MI-LPO-${YY}-0004`)).toBeInTheDocument();
        expect(screen.getByText(`MI-MPR-${YY}-0007`)).toBeInTheDocument();
        expect(screen.getByText(`ST-LPO-${YY}-0001`)).toBeInTheDocument();
        expect(screen.getByText(`ST-MPR-${YY}-0001`)).toBeInTheDocument();
    });

    it('previews the new letters as you type, keeping the sequence', async () => {
        renderPage();
        await screen.findByText('Document Numbering');

        fireEvent.change(screen.getByLabelText('Steel Tech document code'), { target: { value: 'stl' } });

        // Upper-cased as typed, and 0001 is not reset by renaming the series.
        // One code, so both previews follow it.
        expect(screen.getByLabelText('Steel Tech document code')).toHaveValue('STL');
        expect(screen.getByText(`STL-LPO-${YY}-0001`)).toBeInTheDocument();
        expect(screen.getByText(`STL-MPR-${YY}-0001`)).toBeInTheDocument();
    });

    it('saves every code in one request', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({
            message: 'Document numbering saved.',
            data: [{ ...PAYLOAD.data[0] }, { ...PAYLOAD.data[1], lpo_code: 'STL' }],
        });
        renderPage();
        await screen.findByText('Document Numbering');

        fireEvent.change(screen.getByLabelText('Steel Tech document code'), { target: { value: 'STL' } });
        fireEvent.click(within(numberingCard()).getByText('Save'));

        await waitFor(() => expect(put).toHaveBeenCalledWith('/settings/document-numbering', {
            codes: [{ id: 1, lpo_code: 'MI' }, { id: 2, lpo_code: 'STL' }],
        }));
        expect(await screen.findByText('Document numbering saved.')).toBeInTheDocument();
    });

    it('will not save until something has changed', async () => {
        renderPage();
        await screen.findByText('Document Numbering');

        expect(within(numberingCard()).getByText('Save')).toBeDisabled();
        fireEvent.change(screen.getByLabelText('Steel Tech document code'), { target: { value: 'STL' } });
        expect(within(numberingCard()).getByText('Save')).not.toBeDisabled();
    });

    it('shows the server’s refusal rather than a generic failure', async () => {
        vi.spyOn(client, 'apiPut').mockRejectedValue({
            status: 422, message: 'Two companies cannot share the same code.',
        });
        renderPage();
        await screen.findByText('Document Numbering');

        fireEvent.change(screen.getByLabelText('Steel Tech document code'), { target: { value: 'MI' } });
        fireEvent.click(within(numberingCard()).getByText('Save'));

        expect(await screen.findByRole('alert'))
            .toHaveTextContent('Two companies cannot share the same code.');
    });

    it('calls a refusal a refusal when the tab is not theirs', async () => {
        vi.spyOn(client, 'apiGet').mockRejectedValue({ status: 403, message: 'Forbidden' });
        renderPage();

        // Both cards sit behind settings.view, so both say so rather than
        // reporting a fault that is not there.
        const alerts = await screen.findAllByRole('alert');
        expect(alerts.map((node) => node.textContent)).toEqual([
            'You do not have permission to view the document numbering.',
            'You do not have permission to view the company warehouses.',
        ]);
    });

    it('previews a company’s own word for the document, not always MPR', async () => {
        // Matana files an MRF. The preview follows the company, so editing the
        // letters must not quietly turn its series back into an MPR.
        vi.spyOn(client, 'apiGet').mockImplementation(getFor({
            numbering: {
                data: [{
                    id: 3, name: 'Matana steel Factory', is_active: true, lpo_code: 'MSF',
                    mpr_code: 'MRF', next_number: `MSF-LPO-${YY}-0001`, next_mpr_number: `MSF-MRF-${YY}-0001`,
                }],
            },
        }));
        renderPage();

        expect(await screen.findByText(`MSF-MRF-${YY}-0001`)).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Matana steel Factory document code'), { target: { value: 'MS' } });
        expect(screen.getByText(`MS-MRF-${YY}-0001`)).toBeInTheDocument();
        expect(screen.getByText(`MS-LPO-${YY}-0001`)).toBeInTheDocument();
    });

    it('renders on mobile too, since every page is a pair', async () => {
        renderPage(MobileGeneralSettingsPage);

        expect(await screen.findByText('Document Numbering')).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Settings' })).toBeInTheDocument();
    });
});

describe('the Company Warehouses card', () => {
    const warehouseCard = () => screen.getByText('Company Warehouses').closest('div');

    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockImplementation(getFor());
    });

    it('shows each company’s receiving warehouse, and the ones with none', async () => {
        renderPage();
        await screen.findByText('Company Warehouses');

        expect(screen.getByLabelText('Miknas Industrial receiving warehouse')).toHaveValue('1');
        // An unlinked company is a valid state, not a blank waiting to be filled.
        expect(screen.getByLabelText('Steel Tech receiving warehouse')).toHaveValue('');
    });

    it('saves every link in one request, sending null for the unlinked', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({
            message: 'Company warehouses saved.',
            data: [
                { ...WAREHOUSE_PAYLOAD.data[0] },
                { ...WAREHOUSE_PAYLOAD.data[1], warehouse_id: 2, warehouse_name: 'Hidd' },
            ],
            warehouses: WAREHOUSE_PAYLOAD.warehouses,
        });
        renderPage();
        await screen.findByText('Company Warehouses');

        fireEvent.change(screen.getByLabelText('Steel Tech receiving warehouse'), { target: { value: '2' } });
        fireEvent.click(within(warehouseCard()).getByText('Save'));

        await waitFor(() => expect(put).toHaveBeenCalledWith('/settings/company-warehouses', {
            links: [{ id: 1, warehouse_id: 1 }, { id: 2, warehouse_id: 2 }],
        }));
        expect(await screen.findByText('Company warehouses saved.')).toBeInTheDocument();
    });

    it('sends null when a company is unlinked', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({
            message: 'Company warehouses saved.',
            data: [
                { ...WAREHOUSE_PAYLOAD.data[0], warehouse_id: null, warehouse_name: null },
                { ...WAREHOUSE_PAYLOAD.data[1] },
            ],
            warehouses: WAREHOUSE_PAYLOAD.warehouses,
        });
        renderPage();
        await screen.findByText('Company Warehouses');

        fireEvent.change(screen.getByLabelText('Miknas Industrial receiving warehouse'), { target: { value: '' } });
        fireEvent.click(within(warehouseCard()).getByText('Save'));

        await waitFor(() => expect(put).toHaveBeenCalledWith('/settings/company-warehouses', {
            links: [{ id: 1, warehouse_id: null }, { id: 2, warehouse_id: null }],
        }));
    });

    it('will not save until something has changed', async () => {
        renderPage();
        await screen.findByText('Company Warehouses');

        expect(within(warehouseCard()).getByText('Save')).toBeDisabled();
        fireEvent.change(screen.getByLabelText('Steel Tech receiving warehouse'), { target: { value: '2' } });
        expect(within(warehouseCard()).getByText('Save')).not.toBeDisabled();
    });

    it('renders on mobile too, since every page is a pair', async () => {
        renderPage(MobileGeneralSettingsPage);

        expect(await screen.findByText('Company Warehouses')).toBeInTheDocument();
    });
});

describe('previewFor', () => {
    it('keeps the company’s own sequence and only swaps the letters', () => {
        expect(previewFor('MS', `MSF-LPO-${YY}-0007`)).toBe(`MS-LPO-${YY}-0007`);
    });

    it('names the document it is previewing', () => {
        expect(previewFor('MI', `MI-MPR-${YY}-0007`, 'MPR')).toBe(`MI-MPR-${YY}-0007`);
    });

    it('falls back to the house series when there is no code', () => {
        expect(previewFor('', `MI-LPO-${YY}-0004`)).toBe(`LPO-${YY}-0004`);
        expect(previewFor('', `MI-MPR-${YY}-0004`, 'MPR')).toBe(`MPR-${YY}-0004`);
    });
});

describe('the Settings menu entry', () => {
    it('is in the System group, behind settings.view', () => {
        const entry = NAV_GROUPS
            .flatMap((group) => group.items)
            .find((item) => item.to === '/app/settings/general');

        expect(entry).toBeDefined();
        expect(entry.label).toBe('Settings');
        expect(entry.permission).toBe('settings.view');
        expect(entry.hidden).toBeUndefined();
    });

    it('is offered to someone holding settings.view and nobody else', () => {
        const shownTo = (permissions) => visibleGroups({ can: (p) => permissions.includes(p) })
            .flatMap((group) => group.items)
            .map((item) => item.to);

        expect(shownTo(['settings.view'])).toContain('/app/settings/general');
        expect(shownTo(['finance.view'])).not.toContain('/app/settings/general');
    });
});
