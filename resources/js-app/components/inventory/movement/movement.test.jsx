import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import MovementTable from './MovementTable';
import { formatDate, num, typeBadgeClass, typeLabel } from './movementStyles';

const MOVEMENTS = [
    {
        id: 1, item_code: 'RM-1002', item_name: 'Steel Angle 50x50', warehouse_name: 'Main Warehouse',
        type: 'in', quantity: '40.00', reference: 'Goods Receipt #2', notes: null,
        created_at: '2026-09-01T11:16:00Z',
    },
    {
        id: 2, item_code: 'RM-1001', item_name: 'Steel Plate 10mm', warehouse_name: 'Main Warehouse',
        type: 'out', quantity: '5.50', reference: null, notes: 'Damaged in transit',
        created_at: '2026-08-30T09:00:00Z',
    },
];

describe('movementStyles', () => {
    it('badges IN green and OUT red, as the Blade table did', () => {
        expect(typeBadgeClass('in')).toBe('badge-green');
        expect(typeBadgeClass('out')).toBe('badge-red');
        expect(typeLabel('in')).toBe('IN');
        expect(typeLabel('out')).toBe('OUT');
    });

    it('formats quantities to two decimals and dates as d M Y', () => {
        expect(num('40')).toBe('40.00');
        expect(formatDate('2026-09-01T11:16:00Z')).toBe('01 Sep 2026');
    });
});

describe('MovementTable', () => {
    const renderTable = (rows = MOVEMENTS) => render(<MovementTable movements={rows} />);

    it('renders the Blade columns plus Notes', () => {
        renderTable();
        expect(screen.getAllByRole('columnheader').map((th) => th.textContent)).toEqual([
            'Item', 'Warehouse', 'Type', 'Quantity', 'Reference', 'Notes', 'Date',
        ]);
    });

    it('shows the item code beside its name', () => {
        renderTable([MOVEMENTS[0]]);
        expect(screen.getByText('RM-1002')).toBeInTheDocument();
        expect(screen.getByText('Steel Angle 50x50')).toBeInTheDocument();
    });

    /**
     * The Blade column read a non-existent property and always printed "-";
     * this shows the linked document, or names the movement as manual.
     */
    it('names the linked document, and calls an unlinked movement manual', () => {
        renderTable();
        expect(screen.getByText('Goods Receipt #2')).toBeInTheDocument();
        expect(screen.getByText('Manual adjustment')).toBeInTheDocument();
    });

    it('right-aligns the quantity', () => {
        renderTable([MOVEMENTS[0]]);
        expect(screen.getByText('40.00')).toHaveClass('text-right');
    });

    it('shows a placeholder rather than blank for empty notes', () => {
        renderTable([MOVEMENTS[0]]);
        expect(screen.getByText('—')).toBeInTheDocument();
    });

    it('offers no edit or delete — the ledger is append-only', () => {
        renderTable();
        expect(screen.queryByText('Edit')).not.toBeInTheDocument();
        expect(screen.queryByText('Delete')).not.toBeInTheDocument();
    });

    it('says so when nothing has been recorded', () => {
        renderTable([]);
        expect(screen.getByText('No movements recorded.')).toBeInTheDocument();
    });
});
