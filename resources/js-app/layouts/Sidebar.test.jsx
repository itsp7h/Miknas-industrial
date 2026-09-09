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

    it('renders every section heading in its Blade colour', () => {
        renderSidebar();
        const colours = {
            Purchase: 'rgb(245, 158, 11)',
            Inventory: 'rgb(16, 185, 129)',
            Production: 'rgb(249, 115, 22)',
            Sales: 'rgb(167, 139, 250)',
            System: 'rgb(100, 116, 139)',
        };
        Object.entries(colours).forEach(([label, colour]) => {
            expect(screen.getByText(label)).toHaveStyle({ color: colour });
        });
    });

    it('hides the admin-only System group from a non-admin', () => {
        renderSidebar({ isAdmin: false });
        expect(screen.queryByText('System')).not.toBeInTheDocument();
        expect(screen.getByText('Sales')).toBeInTheDocument();
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
        expect(screen.getByText('Items')).toHaveStyle({ background: 'rgb(30, 41, 59)' });
        expect(screen.getByText('Warehouses')).not.toHaveStyle({ background: 'rgb(30, 41, 59)' });
    });
});

describe('SidebarLink', () => {
    const dashboard = { type: 'link', to: '/app', label: 'Dashboard', root: true };
    const pipeline = { type: 'link', to: '/app/purchase/pipeline', label: 'Pipeline', highlight: true };
    const plain = { type: 'link', to: '/app/inventory/items', label: 'Items' };

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
        const link = screen.getByText('Items');
        expect(link).toHaveStyle({ color: 'rgb(148, 163, 184)' });
        fireEvent.mouseEnter(link);
        expect(link).toHaveStyle({ color: 'rgb(226, 232, 240)' });
    });

    it('suppresses the hover once the link is active', () => {
        renderLink(plain, true);
        const link = screen.getByText('Items');
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
