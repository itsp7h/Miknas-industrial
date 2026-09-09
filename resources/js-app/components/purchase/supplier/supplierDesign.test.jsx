import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import SupplierStatCards from './SupplierStatCards';
import SupplierTable from './SupplierTable';
import SupplierToolbar from './SupplierToolbar';
import { deriveStats, hasCredit, hostOf, matchesQuery } from './supplierStats';

const SUPPLIERS = [
    {
        id: 1, supplier_code: 'SUP-001', name: 'Gulf Metals W.L.L.', category: 'Steel',
        contact_person: 'Ali Hassan', email: 'sales@gulfmetals.example', secondary_email: 'alt@gulfmetals.example',
        phone: '+973 1770 1234', phone2: '+973 1770 9999', whatsapp: '+97317701234',
        address: 'Sitra Industrial Area', website: 'https://gulfmetals.example/about',
        tax_number: 'TX-9911', credit_terms: 'yes', credit_days: 30, is_active: true,
    },
    {
        id: 2, supplier_code: null, name: 'Dormant Co', category: null,
        contact_person: null, email: null, secondary_email: null,
        phone: null, phone2: null, whatsapp: null, address: null, website: null,
        tax_number: null, credit_terms: 'n', credit_days: null, is_active: false,
    },
];

describe('supplierStats', () => {
    // Mirrors the old controller's $stats.
    it('derives the four figures the Blade stat cards showed', () => {
        expect(deriveStats(SUPPLIERS)).toEqual({ total: 2, active: 1, inactive: 1, categories: 1 });
    });

    it('ignores blank categories when counting them', () => {
        expect(deriveStats([{ category: '  ', is_active: true }, { category: null, is_active: true }]).categories).toBe(0);
    });

    it('searches across name, contact, email, phone and address', () => {
        ['gulf', 'ali hassan', 'sales@gulf', '1770', 'sitra'].forEach((q) => {
            expect(matchesQuery(SUPPLIERS[0], q)).toBe(true);
        });
        expect(matchesQuery(SUPPLIERS[0], 'nowhere')).toBe(false);
    });

    it('treats an empty query as matching everything', () => {
        expect(matchesQuery(SUPPLIERS[1], '   ')).toBe(true);
    });

    // The Blade page showed just the host, not the whole URL.
    it('reduces a website to its host', () => {
        expect(hostOf('https://gulfmetals.example/about')).toBe('gulfmetals.example');
        expect(hostOf(null)).toBeNull();
    });

    // Only "y"/"yes" counted as credit terms.
    it('recognises only y and yes as credit terms', () => {
        expect(hasCredit({ credit_terms: 'Yes' })).toBe(true);
        expect(hasCredit({ credit_terms: 'y' })).toBe(true);
        expect(hasCredit({ credit_terms: 'n' })).toBe(false);
        expect(hasCredit({})).toBe(false);
    });
});

describe('SupplierStatCards', () => {
    it('labels and colours each figure as the Blade cards did', () => {
        render(<SupplierStatCards suppliers={SUPPLIERS} />);
        ['Total Suppliers', 'Active', 'Inactive', 'Categories'].forEach((label) => {
            expect(screen.getByText(label)).toBeInTheDocument();
        });
        expect(screen.getByText('Total Suppliers').previousSibling).toHaveStyle({ color: 'rgb(15, 23, 42)' });
        expect(screen.getByText('Active').previousSibling).toHaveStyle({ color: 'rgb(22, 163, 74)' });
        expect(screen.getByText('Inactive').previousSibling).toHaveStyle({ color: 'rgb(220, 38, 38)' });
    });
});

describe('SupplierToolbar', () => {
    it('offers the four Blade actions, with the downloads as real links', () => {
        render(<SupplierToolbar fileInputRef={{ current: null }} onImport={() => {}} onCreate={() => {}} />);
        expect(screen.getByText('Export PDF').closest('a')).toHaveAttribute('href', '/api/v1/purchase/suppliers/export-pdf');
        expect(screen.getByText('Template').closest('a')).toHaveAttribute('href', '/api/v1/purchase/suppliers/template');
        expect(screen.getByLabelText('Import Excel')).toBeInTheDocument();
        expect(screen.getByText('Add Supplier')).toBeInTheDocument();
    });

    it('uses the shared button classes from app.css rather than bespoke styling', () => {
        render(<SupplierToolbar fileInputRef={{ current: null }} onImport={() => {}} onCreate={() => {}} />);
        expect(screen.getByText('Add Supplier').closest('button')).toHaveClass('btn-primary');
        expect(screen.getByText('Export PDF').closest('a')).toHaveClass('btn-secondary');
    });
});

describe('SupplierTable', () => {
    const renderTable = (rows = SUPPLIERS) =>
        render(<SupplierTable suppliers={rows} onEdit={() => {}} onDelete={() => {}} />);

    it('renders all eight Blade columns', () => {
        renderTable();
        ['Code', 'Company', 'Category', 'Contact & Email', 'Phone / WhatsApp', 'Address', 'Tax / Credit', 'Status']
            .forEach((heading) => expect(screen.getByText(heading)).toBeInTheDocument());
    });

    it('stacks both emails and all three phone numbers', () => {
        renderTable();
        expect(screen.getByText('sales@gulfmetals.example')).toBeInTheDocument();
        expect(screen.getByText('alt@gulfmetals.example')).toBeInTheDocument();
        expect(screen.getByText('+973 1770 1234')).toBeInTheDocument();
        expect(screen.getByText('+973 1770 9999')).toBeInTheDocument();
    });

    it('links WhatsApp with the plus stripped, as wa.me requires', () => {
        renderTable();
        expect(screen.getByText('+97317701234').closest('a'))
            .toHaveAttribute('href', 'https://wa.me/97317701234');
    });

    it('shows the credit flag with its day count', () => {
        renderTable();
        expect(screen.getByText('Credit · 30d')).toBeInTheDocument();
    });

    it('badges status with the shared badge classes', () => {
        renderTable();
        expect(screen.getByText('Active')).toHaveClass('badge-green');
        expect(screen.getByText('Inactive')).toHaveClass('badge-red');
    });

    it('says so when nothing matches', () => {
        renderTable([]);
        expect(screen.getByText('No suppliers found.')).toBeInTheDocument();
    });

    it('calls back on edit and delete', () => {
        const onEdit = vi.fn();
        const onDelete = vi.fn();
        render(<SupplierTable suppliers={[SUPPLIERS[0]]} onEdit={onEdit} onDelete={onDelete} />);

        fireEvent.click(screen.getByText('Edit'));
        fireEvent.click(screen.getByText('Delete'));

        expect(onEdit).toHaveBeenCalledWith(SUPPLIERS[0]);
        expect(onDelete).toHaveBeenCalledWith(SUPPLIERS[0]);
    });
});
