import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { ToastProvider } from '../../ui/Toast';
import { RequestModalProvider, useRequestModal } from './RequestModalProvider';
import ItemRows, { blankRow } from './ItemRows';
import ProjectPicker from './ProjectPicker';
import UrgencyPicker from './UrgencyPicker';
import * as client from '../../../api/client';

const OPTIONS = {
    projects: [
        {
            id: 1, name: 'Plant Expansion', company_id: 3, company_name: 'Miknas Steel',
            label: 'Miknas Steel — Plant Expansion', locations: ['Bay 4', 'Yard'],
        },
        {
            id: 2, name: 'Harbour Works', company_id: 4, company_name: 'Gulf Marine',
            label: 'Gulf Marine — Harbour Works', locations: [],
        },
    ],
    departments: [
        { id: 10, name: 'Operations', company_id: 3 },
        { id: 11, name: 'Marine Ops', company_id: 4 },
    ],
    units: ['PCS', 'KG'],
    today: '2026-09-01',
};

function Opener() {
    const { openNew, openEdit } = useRequestModal();

    return (
        <>
            <button onClick={openNew}>open new</button>
            <button onClick={() => openEdit(7)}>open edit</button>
        </>
    );
}

const renderProvider = () => render(
    <ToastProvider><RequestModalProvider><Opener /></RequestModalProvider></ToastProvider>
);

describe('UrgencyPicker', () => {
    it('shows a coloured pill for the chosen preset', () => {
        render(<UrgencyPicker value="Urgent" onChange={() => {}} />);
        expect(screen.getByText('Urgent')).toBeInTheDocument();
    });

    it('formats a specific date the way Blade did', () => {
        render(<UrgencyPicker value="2026-09-10" onChange={() => {}} />);
        // Whatever the platform's en-GB short month is — Blade called the same API.
        expect(screen.getByText(/10 Sept? 2026/)).toBeInTheDocument();
    });

    it('offers the five presets and a specific-date row', () => {
        render(<UrgencyPicker value="" onChange={() => {}} />);
        fireEvent.click(screen.getByLabelText('Required Date / Urgency'));

        ['Urgent', '3 Days', '1 Week', '2 Weeks', '1 Month', 'Specific Date'].forEach((label) => {
            expect(screen.getByText(label)).toBeInTheDocument();
        });
    });

    it('reveals a date input only after Specific Date is picked', () => {
        const onChange = vi.fn();
        render(<UrgencyPicker value="" onChange={onChange} />);
        fireEvent.click(screen.getByLabelText('Required Date / Urgency'));
        expect(screen.queryByLabelText('Specific required date')).not.toBeInTheDocument();

        fireEvent.click(screen.getByText('Specific Date'));
        fireEvent.change(screen.getByLabelText('Specific required date'), { target: { value: '2026-10-02' } });
        expect(onChange).toHaveBeenCalledWith('2026-10-02');
    });
});

describe('ProjectPicker', () => {
    it('lists each project under its company and filters as you type', () => {
        render(<ProjectPicker projects={OPTIONS.projects} value="" onChange={() => {}} />);
        fireEvent.click(screen.getByLabelText(/Project \/ Site Name/));

        expect(screen.getByText('Plant Expansion')).toBeInTheDocument();
        expect(screen.getByText('Miknas Steel')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Search projects'), { target: { value: 'gulf' } });
        expect(screen.getByText('Harbour Works')).toBeInTheDocument();
        expect(screen.queryByText('Plant Expansion')).not.toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Search projects'), { target: { value: 'nothing' } });
        expect(screen.getByText('No projects found.')).toBeInTheDocument();
    });

    it('keeps showing a saved project that is no longer in the active list', () => {
        render(<ProjectPicker projects={OPTIONS.projects} value="Closed Site" onChange={() => {}} />);
        expect(screen.getByLabelText(/Project \/ Site Name/)).toHaveTextContent('Closed Site');
    });
});

describe('ItemRows', () => {
    const rows = [blankRow('2026-09-01')];

    it('numbers rows by position and inherits the last row date on add', () => {
        const onChange = vi.fn();
        render(<ItemRows items={rows} units={OPTIONS.units} accent="#000" today="2026-09-01" onChange={onChange} />);

        fireEvent.click(screen.getByText('+ Add Item'));
        expect(onChange).toHaveBeenCalledWith([
            rows[0],
            { description: '', unit: '', quantity_required: '', purpose_use: '', required_date: '2026-09-01' },
        ]);
    });

    it('refuses to remove the only remaining row', () => {
        const onChange = vi.fn();
        render(<ItemRows items={rows} units={OPTIONS.units} accent="#000" today="" onChange={onChange} />);

        expect(screen.getByLabelText('Remove item 1')).toBeDisabled();
        fireEvent.click(screen.getByLabelText('Remove item 1'));
        expect(onChange).not.toHaveBeenCalled();
    });

    it('removes a row once there is more than one', () => {
        const onChange = vi.fn();
        const two = [{ ...blankRow(''), description: 'Keep' }, { ...blankRow(''), description: 'Drop' }];
        render(<ItemRows items={two} units={OPTIONS.units} accent="#000" today="" onChange={onChange} />);

        fireEvent.click(screen.getByLabelText('Remove item 2'));
        expect(onChange).toHaveBeenCalledWith([two[0]]);
    });

    it('keeps a unit outside the standard list selectable', () => {
        render(<ItemRows
            items={[{ ...blankRow(''), unit: 'DRUM' }]} units={OPTIONS.units}
            accent="#000" today="" onChange={() => {}}
        />);

        expect(screen.getByLabelText('Item 1 unit')).toHaveValue('DRUM');
    });
});

describe('the new-request modal', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue(OPTIONS);
    });

    it('opens with Blade\'s heading, one blank row and today\'s date', async () => {
        renderProvider();
        fireEvent.click(screen.getByText('open new'));

        expect(screen.getByText('New Purchase Request')).toBeInTheDocument();
        expect(screen.getByText('Material Purchase Request (MPR)')).toBeInTheDocument();
        expect(screen.getByText('Submit Request')).toBeInTheDocument();
        await waitFor(() => expect(screen.getByLabelText(/^Date/)).toHaveValue('2026-09-01'));
        expect(screen.getByLabelText('Item 1 description')).toHaveValue('');
    });

    it('narrows locations and departments to the chosen project', async () => {
        renderProvider();
        fireEvent.click(screen.getByText('open new'));
        await waitFor(() => expect(client.apiGet).toHaveBeenCalledWith('/purchase/requests/form-options'));

        // With no project chosen, every department is on offer.
        expect(screen.getByText('Operations')).toBeInTheDocument();
        expect(screen.getByText('Marine Ops')).toBeInTheDocument();
        expect(screen.getByLabelText('Location / Site')).toBeDisabled();

        fireEvent.click(screen.getByLabelText(/Project \/ Site Name/));
        fireEvent.click(screen.getByText('Plant Expansion'));

        expect(screen.getByLabelText('Location / Site')).not.toBeDisabled();
        expect(screen.getByRole('option', { name: 'Bay 4' })).toBeInTheDocument();
        // Marine Ops belongs to the other company, so it drops out.
        expect(screen.queryByText('Marine Ops')).not.toBeInTheDocument();
    });

    it('posts the form and drops rows left blank', async () => {
        const post = vi.spyOn(client, 'apiPost').mockResolvedValue({
            data: { id: 9, request_number: 'MPR26-0009' }, message: 'MPR26-0009 submitted successfully.',
        });
        renderProvider();
        fireEvent.click(screen.getByText('open new'));
        await waitFor(() => expect(screen.getByLabelText(/^Date/)).toHaveValue('2026-09-01'));

        fireEvent.click(screen.getByLabelText(/Project \/ Site Name/));
        fireEvent.click(screen.getByText('Plant Expansion'));
        fireEvent.change(screen.getByLabelText(/^Requested By/), { target: { value: 'Aisha Rahman' } });
        fireEvent.change(screen.getByLabelText('Item 1 description'), { target: { value: 'Steel Plate 10mm' } });
        fireEvent.change(screen.getByLabelText('Item 1 quantity'), { target: { value: '500' } });
        // A second row the user added and left empty must not be submitted.
        fireEvent.click(screen.getByText('+ Add Item'));
        fireEvent.submit(screen.getByLabelText('Item 1 description').closest('form'));

        await waitFor(() => expect(post).toHaveBeenCalled());
        const [path, payload] = post.mock.calls[0];
        expect(path).toBe('/purchase/requests');
        expect(payload.project_name).toBe('Plant Expansion');
        expect(payload.requested_by_name).toBe('Aisha Rahman');
        expect(payload.items).toHaveLength(1);
        expect(payload.items[0].description).toBe('Steel Plate 10mm');

        await waitFor(() => expect(screen.getByText('MPR26-0009 submitted successfully.')).toBeInTheDocument());
        expect(screen.queryByText('New Purchase Request')).not.toBeInTheDocument();
    });

    it('lists server validation errors and stays open', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({
            message: 'Invalid.', errors: { project_name: ['The project name field is required.'] },
        });
        renderProvider();
        fireEvent.click(screen.getByText('open new'));
        await waitFor(() => expect(screen.getByLabelText(/^Date/)).toHaveValue('2026-09-01'));

        fireEvent.submit(screen.getByLabelText('Item 1 description').closest('form'));

        await waitFor(() => expect(screen.getByText('The project name field is required.')).toBeInTheDocument());
        expect(screen.getByText('New Purchase Request')).toBeInTheDocument();
    });
});

describe('the edit-request modal', () => {
    const RECORD = {
        id: 7, request_number: 'MPR26-0007', date: '2026-08-20',
        project_name: 'Plant Expansion', requested_by_name: 'Omar Said',
        required_date_text: '2 Weeks', location: 'Bay 4', department: 'Operations',
        remarks: 'Shutdown work.',
        items: [{ description: 'Steel Plate 10mm', unit: 'KG', quantity_required: '500.00', purpose_use: 'Frame', required_date: '2026-09-10' }],
    };

    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockImplementation((path) => (
            path.endsWith('/edit') ? Promise.resolve({ data: RECORD }) : Promise.resolve(OPTIONS)
        ));
    });

    it('opens on the record with its own heading and colours', async () => {
        renderProvider();
        fireEvent.click(screen.getByText('open edit'));

        await waitFor(() => expect(screen.getByText('Edit Purchase Request')).toBeInTheDocument());
        expect(screen.getByText('MPR26-0007')).toBeInTheDocument();
        expect(screen.getByText('Save Changes')).toBeInTheDocument();
        expect(screen.getByLabelText(/^Requested By/)).toHaveValue('Omar Said');
        expect(screen.getByLabelText('Item 1 description')).toHaveValue('Steel Plate 10mm');
        // The saved urgency comes back as its pill, not as raw text in a box.
        expect(screen.getByText('2 Weeks')).toBeInTheDocument();
    });

    it('puts the changed request and hands the caller the fresh detail payload', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({
            data: { id: 7, request_number: 'MPR26-0007', stage: 'draft' },
            message: 'MPR26-0007 updated successfully.',
        });
        renderProvider();
        fireEvent.click(screen.getByText('open edit'));
        await waitFor(() => expect(screen.getByLabelText(/^Requested By/)).toHaveValue('Omar Said'));

        fireEvent.change(screen.getByLabelText(/^Requested By/), { target: { value: 'Layla Hassan' } });
        fireEvent.submit(screen.getByLabelText('Item 1 description').closest('form'));

        await waitFor(() => expect(put).toHaveBeenCalled());
        expect(put.mock.calls[0][0]).toBe('/purchase/requests/7');
        expect(put.mock.calls[0][1].requested_by_name).toBe('Layla Hassan');
        await waitFor(() => expect(screen.getByText('MPR26-0007 updated successfully.')).toBeInTheDocument());
    });

    it('clears the location when the project changes under it', async () => {
        renderProvider();
        fireEvent.click(screen.getByText('open edit'));
        await waitFor(() => expect(screen.getByLabelText('Location / Site')).toHaveValue('Bay 4'));

        fireEvent.click(screen.getByLabelText(/Project \/ Site Name/));
        fireEvent.click(screen.getByText('Harbour Works'));

        // Harbour Works has no locations of its own, so nothing stale survives.
        expect(screen.getByLabelText('Location / Site')).toHaveValue('');
    });
});
