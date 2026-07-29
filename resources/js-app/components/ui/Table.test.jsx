import { describe, it, expect } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import Table from './Table';

const rows = [
    { id: 1, name: 'Acme Steel', category: 'Raw Material' },
    { id: 2, name: 'Bolt & Co', category: 'Fasteners' },
];

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'category', label: 'Category' },
];

describe('Table', () => {
    it('renders all rows and a total count', () => {
        render(<Table columns={columns} rows={rows} rowKey={(r) => r.id} searchPlaceholder="Search…" />);

        expect(screen.getByText('Acme Steel')).toBeInTheDocument();
        expect(screen.getByText('Bolt & Co')).toBeInTheDocument();
        expect(screen.getByText('2')).toBeInTheDocument();
    });

    it('filters rows instantly as the user types, client-side', () => {
        render(<Table columns={columns} rows={rows} rowKey={(r) => r.id} searchPlaceholder="Search…" />);

        fireEvent.change(screen.getByPlaceholderText('Search…'), { target: { value: 'bolt' } });

        expect(screen.queryByText('Acme Steel')).not.toBeInTheDocument();
        expect(screen.getByText('Bolt & Co')).toBeInTheDocument();
        expect(screen.getByText('1 of 2')).toBeInTheDocument();
    });

    it('shows a no-results message when nothing matches', () => {
        render(<Table columns={columns} rows={rows} rowKey={(r) => r.id} searchPlaceholder="Search…" />);

        fireEvent.change(screen.getByPlaceholderText('Search…'), { target: { value: 'zzz' } });

        expect(screen.getByText('No results.')).toBeInTheDocument();
    });
});
