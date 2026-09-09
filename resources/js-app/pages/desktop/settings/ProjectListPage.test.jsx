import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import DesktopProjectListPage from './ProjectListPage';
import MobileProjectListPage from '../../mobile/settings/ProjectListPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => {}, stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const PROJECTS = [
    {
        id: 1, name: 'New Warehouse', is_active: true, company_id: 4, company_name: 'Miknas Industrial',
        locations: [
            { id: 10, name: 'Main Yard', address: 'Industrial Area 4', latitude: 25.2048, longitude: 55.2708, is_active: true },
            { id: 11, name: 'Gate 3', address: null, latitude: null, longitude: null, is_active: false },
        ],
    },
    { id: 2, name: 'Factory Extension', is_active: false, company_id: null, company_name: null, locations: [] },
];

const PAYLOAD = {
    data: PROJECTS,
    companies: [{ id: 4, name: 'Miknas Industrial' }, { id: 5, name: 'Steel tech' }],
    meta: { total_projects: 2, active_projects: 1, total_locations: 2, total_companies: 2 },
};

const wrap = (Page) => render(<ToastProvider><Page /></ToastProvider>);

describe('settings ProjectListPage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue(PAYLOAD);
    });

    it('renders the header, all four stat boxes and a card per project', async () => {
        wrap(DesktopProjectListPage);

        expect(await screen.findByText('Projects')).toBeInTheDocument();
        expect(screen.getByText('Manage projects and their locations.')).toBeInTheDocument();
        ['Total Projects', 'Active', 'Locations', 'Companies'].forEach((label) => {
            expect(screen.getByText(label)).toBeInTheDocument();
        });
        expect(screen.getByText('New Warehouse')).toBeInTheDocument();
        expect(screen.getByText('Factory Extension')).toBeInTheDocument();
    });

    it('shows the company pill, location count and inactive badges', async () => {
        wrap(DesktopProjectListPage);

        await screen.findByText('New Warehouse');
        expect(screen.getByText('Miknas Industrial')).toBeInTheDocument();
        expect(screen.getByText('2 locations')).toBeInTheDocument();
        expect(screen.getByText('0 locations')).toBeInTheDocument();
        // The inactive project and the inactive location.
        expect(screen.getAllByText('Inactive')).toHaveLength(2);
    });

    // Blade printed each location's address and its coordinates to six decimals.
    it('shows each location address and its coordinates', async () => {
        wrap(DesktopProjectListPage);

        await screen.findByText('Main Yard');
        expect(screen.getByText('Industrial Area 4')).toBeInTheDocument();
        expect(screen.getByText('25.204800°, 55.270800°')).toBeInTheDocument();
    });

    it('says so when a project has no locations yet', async () => {
        wrap(DesktopProjectListPage);

        expect(await screen.findByText(/No locations yet/)).toBeInTheDocument();
    });

    it('adds a project, company optional', async () => {
        const post = vi.spyOn(client, 'apiPost').mockResolvedValue({ data: { id: 3 } });
        wrap(DesktopProjectListPage);

        await screen.findByText('New Warehouse');
        fireEvent.click(screen.getByText('Add Project'));
        fireEvent.change(await screen.findByLabelText(/Project Name/), { target: { value: 'Site Expansion' } });
        fireEvent.click(screen.getByText('Save Project'));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/settings/projects', { name: 'Site Expansion', company_id: null }));
    });

    it('refuses to submit an empty project name', async () => {
        const post = vi.spyOn(client, 'apiPost');
        wrap(DesktopProjectListPage);

        await screen.findByText('New Warehouse');
        fireEvent.click(screen.getByText('Add Project'));
        fireEvent.click(await screen.findByText('Save Project'));

        expect(await screen.findByText('Project name is required.')).toBeInTheDocument();
        expect(post).not.toHaveBeenCalled();
    });

    // Blade's controller fell back to the current company when none was sent, so
    // a project could never be moved back to "no company".
    it('can clear a project’s company from the edit strip', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({ data: PROJECTS[0] });
        wrap(DesktopProjectListPage);

        await screen.findByText('New Warehouse');
        fireEvent.click(screen.getAllByText('Edit')[0]);
        fireEvent.change(await screen.findByLabelText('Company'), { target: { value: '' } });
        fireEvent.click(screen.getByText('Save'));

        await waitFor(() => expect(put).toHaveBeenCalledWith('/settings/projects/1', {
            name: 'New Warehouse', company_id: null, is_active: true,
        }));
    });

    it('opens the location modal with its coordinate fields', async () => {
        wrap(DesktopProjectListPage);

        await screen.findByText('New Warehouse');
        fireEvent.click(screen.getAllByText('+ Location')[0]);

        expect(await screen.findByText('New Location — New Warehouse')).toBeInTheDocument();
        expect(screen.getByLabelText(/Location Name/)).toBeInTheDocument();
        expect(screen.getByLabelText('Latitude')).toBeInTheDocument();
        expect(screen.getByLabelText('Longitude')).toBeInTheDocument();
        expect(screen.getByLabelText('Search address on map')).toBeInTheDocument();
    });

    it('edits a location through the same modal, prefilled', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({ data: PROJECTS[0] });
        wrap(DesktopProjectListPage);

        await screen.findByText('Main Yard');
        // The first Edit belongs to the project card; the location's is next.
        fireEvent.click(screen.getAllByText('Edit')[1]);

        expect(await screen.findByText('Edit Location — Main Yard')).toBeInTheDocument();
        expect(screen.getByLabelText('Latitude')).toHaveValue(25.2048);
        fireEvent.change(screen.getByLabelText(/Location Name/), { target: { value: 'North Yard' } });
        fireEvent.click(screen.getByText('Save Location'));

        await waitFor(() => expect(put).toHaveBeenCalledWith('/settings/projects/1/locations/10', {
            name: 'North Yard', address: 'Industrial Area 4', latitude: 25.2048, longitude: 55.2708, is_active: true,
        }));
    });

    it('refuses to save a location with no name', async () => {
        const post = vi.spyOn(client, 'apiPost');
        wrap(DesktopProjectListPage);

        await screen.findByText('New Warehouse');
        fireEvent.click(screen.getAllByText('+ Location')[0]);
        fireEvent.click(await screen.findByText('Save Location'));

        expect(await screen.findByText('Location name is required.')).toBeInTheDocument();
        expect(post).not.toHaveBeenCalled();
    });

    // Deleting a project takes its locations with it, so the warning has to say so.
    it('warns what a project delete takes with it', async () => {
        wrap(DesktopProjectListPage);

        await screen.findByText('New Warehouse');
        fireEvent.click(screen.getAllByText('Delete')[0]);

        expect(await screen.findByText(/2 location\(s\) will be permanently removed/)).toBeInTheDocument();
    });

    it('imports a spreadsheet and reports the server summary', async () => {
        const postForm = vi.spyOn(client, 'apiPostForm').mockResolvedValue({
            message: 'Imported: 3 project(s), 1 new company(s)',
        });
        wrap(DesktopProjectListPage);

        await screen.findByText('New Warehouse');
        fireEvent.click(screen.getByText('Import Excel'));

        const input = await screen.findByLabelText('Import Excel');
        const file = new File(['x'], 'projects.xlsx', { type: 'application/vnd.ms-excel' });
        fireEvent.change(input, { target: { files: [file] } });
        expect(screen.getByText('projects.xlsx')).toBeInTheDocument();
        fireEvent.click(screen.getByText('Import'));

        await waitFor(() => expect(postForm).toHaveBeenCalledWith('/settings/projects/import', expect.any(FormData)));
        expect(await screen.findByText('Imported: 3 project(s), 1 new company(s)')).toBeInTheDocument();
    });

    it('links the template at the API route', async () => {
        wrap(DesktopProjectListPage);

        expect((await screen.findByText('Template')).closest('a'))
            .toHaveAttribute('href', '/api/v1/settings/projects/template');
    });

    it('says so plainly when there are no projects', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [], companies: [], meta: { total_projects: 0, active_projects: 0, total_locations: 0, total_companies: 0 },
        });
        wrap(DesktopProjectListPage);

        expect(await screen.findByText(/No projects yet/)).toBeInTheDocument();
    });

    it('mobile stacks the actions and halves the stat grid', async () => {
        wrap(MobileProjectListPage);

        const add = await screen.findByText('+ Add Project');
        expect(add).toHaveStyle({ width: '100%' });
        expect(screen.getByText('Import Excel')).toBeInTheDocument();
        expect(screen.getByText('New Warehouse')).toBeInTheDocument();
    });
});
