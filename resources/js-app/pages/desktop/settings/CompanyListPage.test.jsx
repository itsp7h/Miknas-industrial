import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import DesktopCompanyListPage from './CompanyListPage';
import MobileCompanyListPage from '../../mobile/settings/CompanyListPage';
import { MemoryRouter } from 'react-router-dom';
import { ToastProvider } from '../../../components/ui/Toast';
import { AccessProvider } from '../../../layouts/AccessContext';
import * as readImage from '../../../components/image/readImage';
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

    it('mobile shows a card per company with its departments and an add-department row', async () => {
        render(
            <ToastProvider>
                <AccessProvider isAdmin><MemoryRouter><MobileCompanyListPage /></MemoryRouter></AccessProvider>
            </ToastProvider>
        );

        expect(await screen.findByText('Miknas Industrial')).toBeInTheDocument();
        expect(screen.getByText('Accounts')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Add company' })).toBeEnabled();
        expect(screen.getAllByRole('button', { name: 'Add department' }).length).toBeGreaterThan(0);
    });

    describe('logo and stamp', () => {
        const PNG = 'data:image/png;base64,iVBORw0KGgo=';
        const withAccess = (permissions, Page = DesktopCompanyListPage) => render(
            <ToastProvider><AccessProvider permissions={permissions}><Page /></AccessProvider></ToastProvider>
        );
        const header = async (name) => (await screen.findByText(name)).closest('div').parentElement;

        it('puts Upload and View for the logo and the stamp on the purple bar', async () => {
            withAccess(['companies.view', 'companies.edit']);

            const bar = await header('Miknas Industrial');
            ['Upload Logo', 'View Logo', 'Upload Stamp', 'View Stamp', '+ Department', 'Edit', 'Delete']
                .forEach((name) => expect(within(bar).getByRole('button', { name })).toBeInTheDocument());
            // Nothing uploaded yet: nothing to view.
            expect(within(bar).getByRole('button', { name: 'View Stamp' })).toBeDisabled();
            expect(within(bar).getByRole('button', { name: 'View Stamp' })).toHaveAttribute('title', 'No stamp uploaded yet');
        });

        it('uploads a logo, which then shows beside the name', async () => {
            vi.spyOn(readImage, 'readImageFile').mockResolvedValue(PNG);
            const put = vi.spyOn(client, 'apiPut').mockResolvedValue({
                data: { ...COMPANIES[0], logo: PNG, stamp: null }, message: 'Logo saved for Miknas Industrial.',
            });
            withAccess(['companies.view', 'companies.edit']);

            const file = new File(['png'], 'logo.png', { type: 'image/png' });
            fireEvent.change(await screen.findByLabelText('Logo image for Miknas Industrial'), { target: { files: [file] } });

            await waitFor(() => expect(put).toHaveBeenCalledWith('/settings/companies/1/images/logo', { image: PNG }));
            expect(readImage.readImageFile).toHaveBeenCalledWith(file, { maxW: 800, maxH: 400 });
            expect(await screen.findByTestId('company-header-logo')).toHaveAttribute('src', PNG);
            expect(screen.getByText('Logo saved for Miknas Industrial.')).toBeInTheDocument();
            expect(screen.getAllByRole('button', { name: 'View Logo' })[0]).toBeEnabled();
        });

        it('shows the stamp in a pop-up, and removes it from there', async () => {
            vi.spyOn(client, 'apiGet').mockResolvedValue({
                ...PAYLOAD, data: [{ ...COMPANIES[0], stamp: PNG }, COMPANIES[1]],
            });
            const del = vi.spyOn(client, 'apiDelete').mockResolvedValue({
                data: { ...COMPANIES[0], stamp: null }, message: 'Stamp removed from Miknas Industrial.',
            });
            withAccess(['companies.view', 'companies.edit']);

            fireEvent.click(within(await header('Miknas Industrial')).getByRole('button', { name: 'View Stamp' }));
            expect(screen.getByRole('heading', { name: 'Miknas Industrial — Stamp' })).toBeInTheDocument();
            expect(screen.getByAltText('Miknas Industrial stamp')).toHaveAttribute('src', PNG);

            fireEvent.click(screen.getByRole('button', { name: 'Remove Stamp' }));

            await waitFor(() => expect(del).toHaveBeenCalledWith('/settings/companies/1/images/stamp'));
            await waitFor(() => expect(screen.queryByAltText('Miknas Industrial stamp')).not.toBeInTheDocument());
            expect(screen.getByText('Stamp removed from Miknas Industrial.')).toBeInTheDocument();
        });

        it('keeps the building icon in the header of a company with no logo', async () => {
            vi.spyOn(client, 'apiGet').mockResolvedValue({
                ...PAYLOAD, data: [{ ...COMPANIES[0], logo: PNG }, { ...COMPANIES[1], logo: null }],
            });
            withAccess(['companies.view']);

            expect(await screen.findAllByTestId('company-header-logo')).toHaveLength(1);
            expect(screen.getByTestId('company-header-logo')).toHaveAttribute('alt', 'Miknas Industrial logo');
        });

        it("toasts an unreadable file's reason, and uploads nothing", async () => {
            vi.spyOn(readImage, 'readImageFile').mockRejectedValue(new Error('Choose a PNG or JPEG image.'));
            const put = vi.spyOn(client, 'apiPut');
            withAccess(['companies.view', 'companies.edit']);

            fireEvent.change(await screen.findByLabelText('Stamp image for Miknas Industrial'), {
                target: { files: [new File(['gif'], 's.gif', { type: 'image/gif' })] },
            });

            expect(await screen.findByText('Choose a PNG or JPEG image.')).toBeInTheDocument();
            expect(put).not.toHaveBeenCalled();
        });

        // CLAUDE.md #14: on the page, disabled, with the reason — but viewing needs nothing more.
        it('without companies.edit, the phone still lets you view an image but not change it', async () => {
            vi.spyOn(client, 'apiGet').mockResolvedValue({ ...PAYLOAD, data: [{ ...COMPANIES[0], stamp: PNG }] });
            render(
                <ToastProvider>
                    <AccessProvider permissions={['companies.view']}><MemoryRouter><MobileCompanyListPage /></MemoryRouter></AccessProvider>
                </ToastProvider>
            );
            await screen.findByText('Miknas Industrial');

            // No logo yet, and no right to add one: disabled, with the reason.
            const logo = screen.getByRole('button', { name: '+ Logo' });
            expect(logo).toBeDisabled();
            expect(logo).toHaveAttribute('title', "You do not have permission to change a company's logo");

            fireEvent.click(screen.getByRole('button', { name: '✓ Stamp' }));
            expect(screen.getByAltText('Miknas Industrial stamp')).toBeInTheDocument();
            expect(screen.getByRole('button', { name: 'Remove Stamp' })).toBeDisabled();
        });
    });
});
