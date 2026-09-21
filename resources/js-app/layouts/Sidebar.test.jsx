import { describe, it, expect } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import Sidebar, { SidebarLink } from './Sidebar';

const renderSidebar = (props = {}) =>
    render(
        <MemoryRouter>
            <Sidebar
                isAdmin
                isActive={() => false}
                userName="Admin User"
                userEmail="admin@erp.com"
                logoutUrl="/logout"
                csrfToken="tok"
                {...props}
            />
        </MemoryRouter>
    );

const renderLink = (item, active = false) =>
    render(
        <MemoryRouter>
            <SidebarLink item={item} active={active} />
        </MemoryRouter>
    );

describe('Sidebar', () => {
    it('renders the brand block', () => {
        renderSidebar();
        expect(screen.getByText('SteelERP')).toBeInTheDocument();
        expect(screen.getByText('Manufacturing & Trading')).toBeInTheDocument();
    });

    it('renders every visible section heading in its Blade colour', () => {
        renderSidebar();
        const colours = {
            Purchase: 'rgb(245, 158, 11)',
            Inventory: 'rgb(16, 185, 129)',
            System: 'rgb(100, 116, 139)',
        };
        Object.entries(colours).forEach(([label, colour]) => {
            expect(screen.getByText(label)).toHaveStyle({ color: colour });
        });
    });

    // Production and Sales carry `hidden: true` in navItems — the modules are
    // built and still routable, they are just not in use yet.
    it('leaves a hidden group and all of its links out of the menu', () => {
        renderSidebar();
        expect(screen.queryByText('Production')).not.toBeInTheDocument();
        expect(screen.queryByText('Sales')).not.toBeInTheDocument();
        expect(screen.queryByText('Bill of Materials')).not.toBeInTheDocument();
        expect(screen.queryByText('Customers')).not.toBeInTheDocument();
    });

    /**
     * The menu is what this person can open, not the whole app with a couple of
     * entries removed. Someone granted one tab sees one tab.
     */
    it('shows only the tabs the user may open', () => {
        renderSidebar({
            isAdmin: false,
            can: (permission) => permission === 'pipeline.view',
        });

        expect(screen.getByText('Pipeline')).toBeInTheDocument();
        expect(screen.getByText('Purchase')).toBeInTheDocument();
        // Granted nothing in Inventory, so the heading goes with its links —
        // an empty group says there is something there when there is not.
        expect(screen.queryByText('Inventory')).not.toBeInTheDocument();
        expect(screen.queryByText('Suppliers')).not.toBeInTheDocument();
    });

    it('hides Users and Integrations from everyone but an Admin', () => {
        renderSidebar({
            isAdmin: false,
            // Every grantable square in the System group.
            can: (permission) => ['companies.view', 'projects.view', 'finance.view', 'item-categories.view']
                .includes(permission),
        });

        expect(screen.getByText('Companies')).toBeInTheDocument();
        expect(screen.getByText('Finance')).toBeInTheDocument();
        // These carry no permission name at all, so nothing can grant them.
        expect(screen.queryByText('Users')).not.toBeInTheDocument();
        expect(screen.queryByText('Integrations')).not.toBeInTheDocument();
    });

    it('gives an Admin the whole menu without granting anything', () => {
        renderSidebar({ isAdmin: true, can: () => false });

        expect(screen.getByText('System')).toBeInTheDocument();
        expect(screen.getByText('Users')).toBeInTheDocument();
        expect(screen.getByText('Inventory')).toBeInTheDocument();
    });

    it('shows the user footer with the avatar initial and a sign-out control', () => {
        renderSidebar();
        expect(screen.getByText('A')).toBeInTheDocument();
        expect(screen.getByText('Admin User')).toBeInTheDocument();
        expect(screen.getByText('admin@erp.com')).toBeInTheDocument();
        expect(screen.getByLabelText('Sign out')).toBeInTheDocument();
    });

    it('marks the active link and only that link', () => {
        render(
            <MemoryRouter>
                <Sidebar isAdmin isActive={(to) => to === '/app/inventory/items'} userName="A" />
            </MemoryRouter>
        );
        expect(screen.getByText('Raw Materials')).toHaveStyle({ background: 'rgb(30, 41, 59)' });
        expect(screen.getByText('Warehouses')).not.toHaveStyle({ background: 'rgb(30, 41, 59)' });
    });
});

describe('SidebarLink', () => {
    const dashboard = { type: 'link', to: '/app', label: 'Dashboard', root: true };
    const pipeline = { type: 'link', to: '/app/purchase/pipeline', label: 'Pipeline', highlight: true };
    const plain = { type: 'link', to: '/app/inventory/items', label: 'Raw Materials' };

    it('gives the Dashboard row its own padding and the blue active fill', () => {
        renderLink(dashboard, true);
        expect(screen.getByText('Dashboard')).toHaveStyle({
            padding: '8px 12px',
            background: 'rgb(37, 99, 235)',
        });
    });

    /**
     * Pipeline takes pill *colours* but keeps the 24px item indent — conflating
     * the two left it visibly under-indented against the Blade sidebar.
     */
    it('indents Pipeline like a module link while colouring it amber', () => {
        renderLink(pipeline);
        expect(screen.getByText('Pipeline')).toHaveStyle({
            padding: '7px 12px 7px 24px',
            color: 'rgb(251, 191, 36)',
            fontWeight: '600',
        });
    });

    it('gives Pipeline the blue active fill, not the slate one', () => {
        renderLink(pipeline, true);
        expect(screen.getByText('Pipeline')).toHaveStyle({ background: 'rgb(37, 99, 235)' });
    });

    // Blade's two distinct hovers: pill links darken their background, module
    // links lighten their text.
    it('darkens the background on hover for a pill link', () => {
        renderLink(pipeline);
        const link = screen.getByText('Pipeline');
        fireEvent.mouseEnter(link);
        expect(link).toHaveStyle({ background: 'rgb(30, 41, 59)' });
        fireEvent.mouseLeave(link);
        expect(link).toHaveStyle({ background: 'transparent' });
    });

    it('lightens the text on hover for a module link', () => {
        renderLink(plain);
        const link = screen.getByText('Raw Materials');
        expect(link).toHaveStyle({ color: 'rgb(148, 163, 184)' });
        fireEvent.mouseEnter(link);
        expect(link).toHaveStyle({ color: 'rgb(226, 232, 240)' });
    });

    it('suppresses the hover once the link is active', () => {
        renderLink(plain, true);
        const link = screen.getByText('Raw Materials');
        fireEvent.mouseEnter(link);
        expect(link).toHaveStyle({ background: 'rgb(30, 41, 59)', color: 'rgb(255, 255, 255)' });
    });

    // Links into still-Blade pages must be real anchors, not router links.
    it('uses a real anchor for a Blade destination', () => {
        renderLink({ type: 'href', to: '/settings/users', label: 'Users' });
        expect(screen.getByText('Users').tagName).toBe('A');
        expect(screen.getByText('Users')).toHaveAttribute('href', '/settings/users');
    });

    // Nothing linked to the profile page before, in either chrome.
    it('links the user card to the profile page', () => {
        renderSidebar();

        expect(screen.getByTitle('Your profile')).toHaveAttribute('href', '/app/profile');
    });
});
