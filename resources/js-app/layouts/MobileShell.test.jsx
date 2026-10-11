import { describe, it, expect, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import MobileShell from './MobileShell';
import { Hero } from '../components/mobile/ui';

vi.mock('../echo', () => ({
    echo: { private: () => ({ listen: () => {}, stopListening: () => {} }), leave: () => {} },
}));

const renderShell = ({ path = '/app', isAdmin = true, permissions = [], children = <div>page content</div> } = {}) =>
    render(
        <MemoryRouter initialEntries={[path]}>
            <MobileShell isAdmin={isAdmin} permissions={permissions}>{children}</MobileShell>
        </MemoryRouter>
    );

const tabBar = () => within(screen.getByTestId('bottom-tab-bar'));

describe('MobileShell', () => {
    it('offers the five tabs of the design to an Admin', () => {
        renderShell();
        expect(tabBar().getAllByRole('link').map((a) => a.textContent))
            .toEqual(['Home', 'Pipeline', 'Purchasing', 'Inventory', 'More']);
    });

    it('points each tab at the first page under it this person can open', () => {
        renderShell({ isAdmin: false, permissions: ['goods-receipts.view', 'finished-goods.view'] });

        expect(tabBar().queryByText('Pipeline')).not.toBeInTheDocument();
        expect(tabBar().getByText('Purchasing').closest('a')).toHaveAttribute('href', '/app/purchase/grns');
        expect(tabBar().getByText('Inventory').closest('a')).toHaveAttribute('href', '/app/inventory/finished-goods');
        expect(tabBar().getByText('Home')).toBeInTheDocument();
        expect(tabBar().getByText('More')).toBeInTheDocument();
    });

    it('marks the tab a page lives under, and More for anything no tab claims', () => {
        renderShell({ path: '/app/purchase/grns/4' });
        expect(tabBar().getByText('Purchasing').closest('a')).toHaveAttribute('aria-current', 'page');

        renderShell({ path: '/app/settings/users' });
        expect(within(screen.getAllByTestId('bottom-tab-bar')[1]).getByText('More').closest('a'))
            .toHaveAttribute('aria-current', 'page');
    });

    it('gives a page without a header of its own a fallback one, titled from the route', () => {
        renderShell({ path: '/app/settings/users' });
        expect(screen.getByRole('heading', { name: 'Users' })).toBeInTheDocument();
        expect(screen.getByText('page content')).toBeInTheDocument();
    });

    it('steps the fallback aside once the page draws its own', () => {
        renderShell({ path: '/app/settings/users', children: <Hero title="My own header" /> });
        expect(screen.getAllByTestId('mobile-hero')).toHaveLength(1);
        expect(screen.getByRole('heading', { name: 'My own header' })).toBeInTheDocument();
    });
});
