import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import App from './App';

describe('App', () => {
    it('renders the SteelERP app shell', () => {
        render(
            <MemoryRouter initialEntries={['/app']}>
                <App />
            </MemoryRouter>
        );

        expect(screen.getByText('SteelERP')).toBeInTheDocument();
    });
});
