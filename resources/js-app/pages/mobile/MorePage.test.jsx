import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import MorePage from './MorePage';
import { AccessProvider } from '../../layouts/AccessContext';
import { ShellUserProvider } from '../../layouts/ShellUserContext';
import * as client from '../../api/client';

vi.mock('../../echo', () => ({
    echo: { private: () => ({ listen: () => {}, stopListening: () => {} }), leave: () => {} },
}));

const USER = {
    currentUserId: 1, userName: 'Admin User', userEmail: 'admin@erp.com', userRole: 'Admin',
    logoutUrl: '/logout', csrfToken: 'tok',
};

const renderPage = ({ isAdmin = true, permissions = [] } = {}) =>
    render(
        <ShellUserProvider value={USER}>
            <AccessProvider isAdmin={isAdmin} permissions={permissions}>
                <MemoryRouter><MorePage /></MemoryRouter>
            </AccessProvider>
        </ShellUserProvider>
    );

describe('mobile MorePage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            pipeline: null, actions: [], low_stock: 0, counts: { suppliers: 1196, unpaid_invoices: 4, warehouses: 1 },
        });
    });

    it('shows who is signed in, with their role, linking to the profile', () => {
        renderPage();
        expect(screen.getByText('Admin User').closest('a')).toHaveAttribute('href', '/app/profile');
        expect(screen.getByText('admin@erp.com')).toBeInTheDocument();
        expect(screen.getByText('Admin')).toBeInTheDocument();
    });

    it('lists the pages no tab covers, with their counts', async () => {
        renderPage();
        expect(await screen.findByText('1,196')).toBeInTheDocument();
        expect(screen.getByText('Suppliers').closest('a')).toHaveAttribute('href', '/app/purchase/suppliers');
        expect(screen.getByText('Stock movements & report')).toBeInTheDocument();
        expect(screen.getByText('Item categories').closest('a')).toHaveAttribute('href', '/app/settings/item-categories');
        // On a tab already.
        expect(screen.queryByText('Purchase Orders')).not.toBeInTheDocument();
        expect(screen.queryByText('Raw Materials')).not.toBeInTheDocument();
    });

    it('lists only what this person may open', () => {
        renderPage({ isAdmin: false, permissions: ['suppliers.view'] });
        expect(screen.getByText('Suppliers')).toBeInTheDocument();
        expect(screen.queryByText('Warehouses')).not.toBeInTheDocument();
        expect(screen.queryByText('Users')).not.toBeInTheDocument();
    });

    it('signs out with a real POST carrying the token', () => {
        renderPage();
        const form = screen.getByRole('button', { name: 'Sign out' }).closest('form');
        expect(form).toHaveAttribute('action', '/logout');
        expect(form.querySelector('input[name="_token"]')).toHaveValue('tok');
    });
});
