import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import DesktopCompanyListPage from './CompanyListPage';
import MobileCompanyListPage from '../../mobile/settings/CompanyListPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => {}, stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const COMPANIES = [
    {
        id: 1, name: 'Miknas Industrial', is_active: true, project_count: 2,
        departments: [
            { id: 10, name: 'Accounts', is_active: true },
            { id: 11, name: 'Welding', is_active: false },
        ],
    },
    { id: 2, name: 'Steel tech', is_active: false, project_count: 0, departments: [] },
];

const PAYLOAD = { data: COMPANIES, meta: { total_companies: 2, total_departments: 2 } };

const wrap = (Page) => render(<ToastProvider><Page /></ToastProvider>);

describe('settings CompanyListPage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue(PAYLOAD);
    });

    it('renders the header, both stat boxes and a card per company', async () => {
        wrap(DesktopCompanyListPage);

        expect(await screen.findByText('Companies & Departments')).toBeInTheDocument();
        expect(screen.getByText('Manage companies and their departments.')).toBeInTheDocument();
        expect(screen.getByText('Companies')).toBeInTheDocument();
        expect(screen.getByText('Departments')).toBeInTheDocument();
        expect(screen.getByText('Miknas Industrial')).toBeInTheDocument();
        expect(screen.getByText('Steel tech')).toBeInTheDocument();
    });

    it('counts departments on the card and badges what is inactive', async () => {
        wrap(DesktopCompanyListPage);

        await screen.findByText('Miknas Industrial');
        expect(screen.getByText('2 depts')).toBeInTheDocument();
        // Blade singularised the count and badged inactive rows.
        expect(screen.getByText('0 depts')).toBeInTheDocument();
        expect(screen.getAllByText('Inactive')).toHaveLength(2);
    });

    it('says so when a company has no departments yet', async () => {
        wrap(DesktopCompanyListPage);

        expect(await screen.findByText(/No departments yet/)).toBeInTheDocument();
    });

    it('adds a company through the modal', async () => {
        const post = vi.spyOn(client, 'apiPost').mockResolvedValue({
            data: { id: 3, name: 'New Co', is_active: true, departments: [] },
        });
        wrap(DesktopCompanyListPage);

        await screen.findByText('Miknas Industrial');
        fireEvent.click(screen.getByText('Add Company'));
        fireEvent.change(await screen.findByLabelText(/Company Name/), { target: { value: 'New Co' } });
        fireEvent.click(screen.getByText('Save Company'));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/settings/companies', { name: 'New Co' }));
        expect(await screen.findByText('New Co')).toBeInTheDocument();
    });

    it('refuses to submit an empty company name', async () => {
        const post = vi.spyOn(client, 'apiPost');
        wrap(DesktopCompanyListPage);

        await screen.findByText('Miknas Industrial');
        fireEvent.click(screen.getByText('Add Company'));
        fireEvent.click(await screen.findByText('Save Company'));

        expect(await screen.findByText('Company name is required.')).toBeInTheDocument();
        expect(post).not.toHaveBeenCalled();
    });

    it('surfaces a duplicate name from the server in the modal', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({ errors: { name: ['The name has already been taken.'] } });
        wrap(DesktopCompanyListPage);

        await screen.findByText('Miknas Industrial');
        fireEvent.click(screen.getByText('Add Company'));
        fireEvent.change(await screen.findByLabelText(/Company Name/), { target: { value: 'Steel tech' } });
        fireEvent.click(screen.getByText('Save Company'));

        expect(await screen.findByText('The name has already been taken.')).toBeInTheDocument();
    });

    it('edits a company from the inline strip', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({
            data: { ...COMPANIES[0], name: 'Miknas LLC', is_active: false },
        });
        wrap(DesktopCompanyListPage);

        await screen.findByText('Miknas Industrial');
        fireEvent.click(screen.getAllByText('Edit')[0]);
        fireEvent.change(await screen.findByLabelText('Company name'), { target: { value: 'Miknas LLC' } });
        fireEvent.click(screen.getByText('Save'));

        await waitFor(() => expect(put).toHaveBeenCalledWith('/settings/companies/1', { name: 'Miknas LLC', is_active: true }));
        expect(await screen.findByText('Miknas LLC')).toBeInTheDocument();
    });

    it('adds a department inline and re-renders the card from the response', async () => {
        const post = vi.spyOn(client, 'apiPost').mockResolvedValue({
            data: { ...COMPANIES[1], departments: [{ id: 20, name: 'Logistics', is_active: true }] },
        });
        wrap(DesktopCompanyListPage);

        await screen.findByText('Steel tech');
        fireEvent.click(screen.getAllByText('+ Department')[1]);
        fireEvent.change(await screen.findByLabelText('Department name…'), { target: { value: 'Logistics' } });
        fireEvent.click(screen.getByText('Save'));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/settings/companies/2/departments', { name: 'Logistics' }));
        expect(await screen.findByText('Logistics')).toBeInTheDocument();
    });

    // Deleting a company drops its departments with it, and is refused server-side
    // while it still owns projects — the confirmation has to say both.
    it('warns what a company delete takes with it', async () => {
        wrap(DesktopCompanyListPage);

        await screen.findByText('Miknas Industrial');
        fireEvent.click(screen.getAllByText('Delete')[0]);

        expect(await screen.findByText(/2 department\(s\) will be permanently removed/)).toBeInTheDocument();
        expect(screen.getByText(/still owns projects cannot be deleted/)).toBeInTheDocument();
    });

    it('surfaces the server refusal when a company still owns projects', async () => {
        vi.spyOn(client, 'apiDelete').mockRejectedValue({
            message: '2 project(s) still belong to this company. Move or delete them first.',
        });
        wrap(DesktopCompanyListPage);

        await screen.findByText('Miknas Industrial');
        fireEvent.click(screen.getAllByText('Delete')[0]);
        fireEvent.click(await screen.findByText('Confirm'));

        await waitFor(() => {
            expect(screen.getByText('2 project(s) still belong to this company. Move or delete them first.')).toBeInTheDocument();
        });
        // The card stays put when the delete is refused.
        expect(screen.getByText('Miknas Industrial')).toBeInTheDocument();
    });

    it('says so plainly when there are no companies at all', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [], meta: { total_companies: 0, total_departments: 0 } });
        wrap(DesktopCompanyListPage);

        expect(await screen.findByText('No companies yet. Add your first company above.')).toBeInTheDocument();
    });

    it('mobile stacks the same cards behind a full-width add button', async () => {
        wrap(MobileCompanyListPage);

        const button = await screen.findByText('+ Add Company');
        expect(button).toHaveStyle({ width: '100%' });
        expect(screen.getByText('Miknas Industrial')).toBeInTheDocument();
        expect(screen.getByText('Accounts')).toBeInTheDocument();
    });
});
