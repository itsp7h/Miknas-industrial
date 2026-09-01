import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import TopBar, { formatTopBarDate } from './TopBar';
import * as client from '../api/client';

vi.mock('../echo', () => ({
    echo: { private: () => ({ listen: () => {} }), leave: () => {} },
}));

const renderBar = (props = {}, path = '/app') =>
    render(
        <MemoryRouter initialEntries={[path]}>
            <TopBar userName="Admin User" currentUserId={1} {...props} />
        </MemoryRouter>
    );

describe('TopBar', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue({ notifications: [] });
    });

    /**
     * The Blade topbar printed PHP's `l, d M Y`. toLocaleDateString('en-GB')
     * renders September as "Sept", which would not match — hence the explicit
     * formatter this asserts.
     */
    it('formats the date exactly as the Blade topbar did', () => {
        expect(formatTopBarDate(new Date(2026, 8, 1))).toBe('Tuesday, 01 Sep 2026');
        expect(formatTopBarDate(new Date(2026, 0, 5))).toBe('Monday, 05 Jan 2026');
    });

    it('shows the page title, date, username and avatar initial', () => {
        renderBar();
        expect(screen.getByText('Dashboard')).toBeInTheDocument();
        expect(screen.getByText(formatTopBarDate(new Date()))).toBeInTheDocument();
        expect(screen.getByText('Admin User')).toBeInTheDocument();
        expect(screen.getByText('A')).toBeInTheDocument();
    });

    it('derives the title from the route', () => {
        renderBar({}, '/app/inventory/items');
        expect(screen.getByText('Items')).toBeInTheDocument();
    });

    // Blade detail pages reused their list page's @section('title').
    it('keeps the list title on a detail route', () => {
        renderBar({}, '/app/purchase/orders/3');
        expect(screen.getByText('Purchase Orders')).toBeInTheDocument();
    });

    it('falls back to Dashboard for an unmapped route', () => {
        renderBar({}, '/app/nowhere');
        expect(screen.getByText('Dashboard')).toBeInTheDocument();
    });

    /**
     * The Blade layout hid the date, the divider and the username below 1024px —
     * a full date string plus a name never fit a phone row. The avatar stayed.
     */
    it('drops the date and username but keeps the avatar when compact', () => {
        renderBar({ compact: true });
        expect(screen.queryByText(formatTopBarDate(new Date()))).not.toBeInTheDocument();
        expect(screen.queryByText('Admin User')).not.toBeInTheDocument();
        expect(screen.getByText('A')).toBeInTheDocument();
    });

    it('only offers the menu button when compact', () => {
        renderBar({ compact: true, onToggleMenu: () => {} });
        expect(screen.getByLabelText('Menu')).toBeInTheDocument();

        renderBar();
        expect(screen.getAllByLabelText('Notifications').length).toBeGreaterThan(0);
    });

    it('falls back to U for a missing user name', () => {
        renderBar({ userName: null });
        expect(screen.getByText('U')).toBeInTheDocument();
        expect(screen.getByText('User')).toBeInTheDocument();
    });
});
