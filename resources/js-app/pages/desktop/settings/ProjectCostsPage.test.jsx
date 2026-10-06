import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import ProjectCostsPage from './ProjectCostsPage';
import MobileProjectCostsPage from '../../mobile/settings/ProjectCostsPage';
import ProjectCard from '../../../components/settings/project/ProjectCard';
import { AccessProvider } from '../../../layouts/AccessContext';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: {
        private: () => ({ listen: () => {}, stopListening: () => {} }),
        channel: () => ({ listen: () => {}, stopListening: () => {} }),
        leave: () => {},
    },
}));

const PAYLOAD = {
    data: { id: 20, name: 'Hidd Yard', company_name: 'Miknas Industrial', is_active: true },
    lines: [
        {
            id: 27, grn_id: 19, grn_number: 'GRN-00019', received_date: '2026-10-05',
            purchase_order_id: 7, po_number: 'MI-LPO-26-0007', supplier_name: 'Gulf Supplies',
            item_code: 'ITEM-00040', item_name: 'Silica Sand', unit_of_measure: 'KG',
            quantity: 1000, rate: 0.006, amount: 6,
        },
        {
            id: 28, grn_id: 21, grn_number: 'GRN-00021', received_date: '2026-10-06',
            purchase_order_id: 8, po_number: 'MI-LPO-26-0008', supplier_name: 'Bahrain Safety',
            item_code: 'ITEM-00041', item_name: 'Work Gloves', unit_of_measure: 'PAIR',
            quantity: 10, rate: 1.25, amount: 12.5,
        },
    ],
    meta: { total: 18.5, line_count: 2, grn_count: 2 },
};

const renderAt = (Page) => render(
    <ToastProvider>
        <MemoryRouter initialEntries={['/app/settings/projects/20/costs']}>
            <Routes>
                <Route path="/app/settings/projects/:id/costs" element={<Page />} />
            </Routes>
        </MemoryRouter>
    </ToastProvider>
);

describe('ProjectCostsPage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue(PAYLOAD);
    });

    it('fetches the project costs and shows each line with its three-decimal rate', async () => {
        renderAt(ProjectCostsPage);
        expect(await screen.findByText('Hidd Yard')).toBeInTheDocument();
        expect(client.apiGet).toHaveBeenCalledWith('/settings/projects/20/costs');
        expect(screen.getByText('Silica Sand')).toBeInTheDocument();
        expect(screen.getByText('0.006')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'GRN-00019' })).toHaveAttribute('href', '/app/purchase/grns/19');
        expect(screen.getByRole('link', { name: 'MI-LPO-26-0007' })).toHaveAttribute('href', '/app/purchase/orders/7');
        expect(screen.getAllByText(/18\.500/).length).toBeGreaterThan(0);
    });

    it('filters instantly and totals only the lines shown', async () => {
        renderAt(ProjectCostsPage);
        await screen.findByText('Hidd Yard');

        fireEvent.change(screen.getByLabelText('Search costs'), { target: { value: 'gloves' } });

        expect(screen.queryByText('Silica Sand')).not.toBeInTheDocument();
        expect(screen.getByText('1 of 2 lines')).toBeInTheDocument();
        expect(screen.getByText('Total (shown lines)')).toBeInTheDocument();
    });

    it('says when nothing has been charged yet', async () => {
        client.apiGet.mockResolvedValue({ ...PAYLOAD, lines: [], meta: { total: 0, line_count: 0, grn_count: 0 } });
        renderAt(ProjectCostsPage);
        expect(await screen.findByText(/Nothing charged to this project yet/)).toBeInTheDocument();
    });

    it('renders on a phone as cards with the total leading', async () => {
        renderAt(MobileProjectCostsPage);
        expect(await screen.findByText('Hidd Yard')).toBeInTheDocument();
        expect(screen.getByText('Total charged')).toBeInTheDocument();
        expect(screen.getByText('Work Gloves')).toBeInTheDocument();
    });
});

describe('ProjectCard Costs action', () => {
    const project = { id: 20, name: 'Hidd Yard', is_active: true, locations: [] };
    const renderCard = (permissions) => render(
        <MemoryRouter>
            <AccessProvider permissions={permissions}>
                <ProjectCard
                    project={project} companies={[]}
                    onSave={() => {}} onDelete={() => {}}
                    onAddLocation={() => {}} onEditLocation={() => {}} onDeleteLocation={() => {}}
                />
            </AccessProvider>
        </MemoryRouter>
    );

    it('links to the project costs for someone allowed to see them', () => {
        renderCard(['projects.view', 'projects.costs']);
        expect(screen.getByRole('link', { name: 'Costs' })).toHaveAttribute('href', '/app/settings/projects/20/costs');
    });

    it('is shown disabled, with the reason, for someone who is not', () => {
        renderCard(['projects.view']);
        expect(screen.queryByRole('link', { name: 'Costs' })).not.toBeInTheDocument();
        const costs = screen.getByText('Costs');
        expect(costs).toHaveAttribute('aria-disabled', 'true');
        expect(costs).toHaveAttribute('title', 'You do not have permission to see project costs');
    });
});
