import { describe, it, expect, afterEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import AppShell from './AppShell';

function setWidth(width) {
    window.innerWidth = width;
    window.dispatchEvent(new Event('resize'));
}

describe('AppShell', () => {
    afterEach(() => setWidth(1024));

    it('renders the desktop sidebar shell above the breakpoint', () => {
        setWidth(1024);
        render(
            <MemoryRouter>
                <AppShell><div>page content</div></AppShell>
            </MemoryRouter>
        );
        expect(screen.getByTestId('desktop-shell')).toBeInTheDocument();
        expect(screen.getByText('page content')).toBeInTheDocument();
    });

    it('renders the mobile shell below the breakpoint', () => {
        setWidth(500);
        render(
            <MemoryRouter>
                <AppShell><div>page content</div></AppShell>
            </MemoryRouter>
        );
        expect(screen.getByTestId('mobile-shell')).toBeInTheDocument();
        expect(screen.getByText('page content')).toBeInTheDocument();
    });
});
