import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import MobileShell from './MobileShell';

vi.mock('../echo', () => ({
    echo: { private: () => ({ listen: () => {} }), leave: () => {} },
}));

const renderShell = () =>
    render(
        <MemoryRouter>
            <MobileShell isAdmin userName="Admin User" userEmail="admin@erp.com" logoutUrl="/logout" csrfToken="tok">
                <div>page content</div>
            </MobileShell>
        </MemoryRouter>
    );

describe('MobileShell', () => {
    // "Purchase" also labels a bottom-tab, so every drawer assertion is scoped
    // to the drawer itself rather than the whole document.
    const drawer = () => within(screen.getByTestId('mobile-drawer'));

    it('keeps the drawer closed until the menu is tapped', () => {
        renderShell();
        expect(screen.queryByTestId('mobile-drawer')).not.toBeInTheDocument();

        fireEvent.click(screen.getByLabelText('Menu'));
        expect(drawer().getByText('Purchase')).toBeInTheDocument();
    });

    /**
     * The drawer renders the shared SidebarNav, so it inherits the desktop
     * sidebar's section colours rather than keeping its own copy that could
     * drift.
     */
    it('renders the drawer with the same section colours as the desktop sidebar', () => {
        renderShell();
        fireEvent.click(screen.getByLabelText('Menu'));

        expect(drawer().getByText('Purchase')).toHaveStyle({ color: 'rgb(245, 158, 11)' });
        expect(drawer().getByText('Inventory')).toHaveStyle({ color: 'rgb(16, 185, 129)' });
        expect(drawer().getByText('Pipeline')).toHaveStyle({ color: 'rgb(251, 191, 36)' });
    });

    it('closes the drawer once a router link is followed', () => {
        renderShell();
        fireEvent.click(screen.getByLabelText('Menu'));
        fireEvent.click(drawer().getByText('Items'));

        expect(screen.queryByTestId('mobile-drawer')).not.toBeInTheDocument();
    });

    it('still renders the bottom tab bar and the page content', () => {
        renderShell();
        expect(screen.getByTestId('bottom-tab-bar')).toBeInTheDocument();
        expect(screen.getByText('page content')).toBeInTheDocument();
    });
});
