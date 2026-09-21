import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import DesktopGeneralSettingsPage from './GeneralSettingsPage';
import MobileGeneralSettingsPage from '../../mobile/settings/GeneralSettingsPage';
import { ToastProvider } from '../../../components/ui/Toast';
import { NAV_GROUPS, visibleGroups } from '../../../layouts/navItems';
import { previewFor, shortYear } from '../../../components/settings/documentNumbering/useDocumentNumbering';
import * as client from '../../../api/client';

const YY = shortYear();

const PAYLOAD = {
    data: [
        { id: 1, name: 'Miknas Industrial', is_active: true, lpo_code: 'MI', next_number: `MI-LPO-${YY}-0004`, next_mpr_number: `MI-MPR-${YY}-0007` },
        { id: 2, name: 'Steel Tech', is_active: true, lpo_code: 'ST', next_number: `ST-LPO-${YY}-0001`, next_mpr_number: `ST-MPR-${YY}-0001` },
    ],
};

const renderPage = (Page = DesktopGeneralSettingsPage) =>
    render(<ToastProvider><Page /></ToastProvider>);

describe('the Settings tab', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue(PAYLOAD);
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
        fireEvent.click(screen.getByText('Save'));

        await waitFor(() => expect(put).toHaveBeenCalledWith('/settings/document-numbering', {
            codes: [{ id: 1, lpo_code: 'MI' }, { id: 2, lpo_code: 'STL' }],
        }));
        expect(await screen.findByText('Document numbering saved.')).toBeInTheDocument();
    });

    it('will not save until something has changed', async () => {
        renderPage();
        await screen.findByText('Document Numbering');

        expect(screen.getByText('Save')).toBeDisabled();
        fireEvent.change(screen.getByLabelText('Steel Tech document code'), { target: { value: 'STL' } });
        expect(screen.getByText('Save')).not.toBeDisabled();
    });

    it('shows the server’s refusal rather than a generic failure', async () => {
        vi.spyOn(client, 'apiPut').mockRejectedValue({
            status: 422, message: 'Two companies cannot share the same code.',
        });
        renderPage();
        await screen.findByText('Document Numbering');

        fireEvent.change(screen.getByLabelText('Steel Tech document code'), { target: { value: 'MI' } });
        fireEvent.click(screen.getByText('Save'));

        expect(await screen.findByRole('alert'))
            .toHaveTextContent('Two companies cannot share the same code.');
    });

    it('calls a refusal a refusal when the tab is not theirs', async () => {
        vi.spyOn(client, 'apiGet').mockRejectedValue({ status: 403, message: 'Forbidden' });
        renderPage();

        expect(await screen.findByRole('alert'))
            .toHaveTextContent('You do not have permission to view the document numbering.');
    });

    it('renders on mobile too, since every page is a pair', async () => {
        renderPage(MobileGeneralSettingsPage);

        expect(await screen.findByText('Document Numbering')).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Settings' })).toBeInTheDocument();
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
