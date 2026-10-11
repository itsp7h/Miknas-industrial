import { useMemo, useState } from 'react';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import { useAccess } from '../../../layouts/AccessContext';
import SupplierModal from '../../../components/purchase/supplier/SupplierModal';
import useSupplierList from '../../../components/purchase/supplier/useSupplierList';
import { deriveStats, matchesQuery } from '../../../components/purchase/supplier/supplierStats';
import {
    ActionSheet, Avatar, BarButton, Card, Chip, ChipRow, EmptyState, Hero, ListRow, MobilePage, Pill,
    SearchField, SectionLabel, StatStrip,
} from '../../../components/mobile/ui';

// Suppliers (SteelERP-Mobile-Designs-V2): the directory A–Z with its figures
// on top. Tapping a supplier offers its contact routes and edit / delete;
// import, export and delete-all sit behind the bar's upload button.

const deny = (what) => `You do not have permission to ${what}`;

export default function SupplierListPage() {
    const { can } = useAccess();
    const s = useSupplierList();
    const [status, setStatus] = useState('all');
    const [category, setCategory] = useState(null);
    const [picked, setPicked] = useState(null);
    const [sheet, setSheet] = useState(null); // 'tools' | 'category'

    const stats = useMemo(() => deriveStats(s.suppliers), [s.suppliers]);
    const categories = useMemo(
        () => [...new Set(s.suppliers.map((x) => x.category).filter((c) => c && String(c).trim()))].sort(),
        [s.suppliers]
    );

    const filtered = useMemo(() => s.suppliers
        .filter((x) => matchesQuery(x, s.query))
        .filter((x) => (status === 'all' ? true : (status === 'active' ? x.is_active : !x.is_active)))
        .filter((x) => !category || x.category === category)
        .sort((a, b) => String(a.name).localeCompare(String(b.name))),
    [s.suppliers, s.query, status, category]);

    // A–Z sections, as the design groups the directory.
    const sections = useMemo(() => {
        const out = [];
        filtered.forEach((x) => {
            const letter = /[a-z]/i.test(x.name?.[0] ?? '') ? x.name[0].toUpperCase() : '#';
            if (!out.length || out[out.length - 1].letter !== letter) out.push({ letter, rows: [] });
            out[out.length - 1].rows.push(x);
        });

        return out;
    }, [filtered]);

    const nothingToDelete = s.suppliers.length === 0;

    return (
        <MobilePage gap={14}>
            <Hero
                zone="purchase"
                back={{ to: '/app/more', label: 'More' }}
                title="Suppliers"
                subtitle="Your supplier directory"
                actions={(
                    <>
                        <BarButton icon="upload" label="Import or export" onClick={() => setSheet('tools')} />
                        <BarButton
                            icon="plus" label="Add supplier" onClick={s.openCreate}
                            disabled={!can('suppliers.create')}
                            title={can('suppliers.create') ? 'Add supplier' : deny('add suppliers')}
                        />
                    </>
                )}
            />

            <StatStrip items={[
                { label: 'Total', value: stats.total.toLocaleString() },
                { label: 'Active', value: stats.active.toLocaleString(), color: '#15803D' },
                { label: 'Inactive', value: stats.inactive.toLocaleString(), color: '#B91C1C' },
                { label: 'Categories', value: stats.categories.toLocaleString(), color: '#B45309' },
            ]} />

            <SearchField value={s.query} onChange={s.setQuery} placeholder="Search suppliers" />

            <ChipRow>
                <Chip active={status === 'all'} onClick={() => setStatus('all')}>All</Chip>
                <Chip active={status === 'active'} onClick={() => setStatus('active')}>Active</Chip>
                <Chip active={status === 'inactive'} onClick={() => setStatus('inactive')}>Inactive</Chip>
                {categories.length > 0 && (
                    <Chip active={!!category} dropdown onClick={() => setSheet('category')}>
                        {category ?? 'Category'}
                    </Chip>
                )}
            </ChipRow>

            {(s.query || status !== 'all' || category) && (
                <span style={{ fontSize: 13, color: '#475569', padding: '0 4px' }}>
                    {filtered.length} of {s.suppliers.length} suppliers
                </span>
            )}

            {filtered.length === 0 ? (
                <EmptyState icon="building" title={s.suppliers.length ? 'No suppliers match' : 'No suppliers yet'} />
            ) : sections.map((section) => (
                <div key={section.letter} style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                    <SectionLabel zone="purchase">{section.letter}</SectionLabel>
                    <Card>
                        {section.rows.map((x, i) => (
                            <ListRow
                                key={x.id}
                                onClick={() => setPicked(x)}
                                chevron={false}
                                leading={<Avatar name={x.name} />}
                                title={x.name}
                                subtitle={x.category || x.contact_person || x.supplier_code}
                                trailing={!x.is_active && <Pill tone="red">Inactive</Pill>}
                                last={i === section.rows.length - 1}
                            />
                        ))}
                    </Card>
                </div>
            ))}

            {/* The import's file picker, opened from the tools sheet. */}
            <input
                ref={s.fileInputRef} type="file" accept=".xlsx,.xls" aria-label="Import Excel"
                style={{ display: 'none' }} onChange={s.handleImport}
            />

            <ActionSheet
                open={sheet === 'tools'}
                onClose={() => setSheet(null)}
                title="Suppliers"
                options={[
                    can('suppliers.import')
                        ? { label: 'Import from Excel', onClick: () => s.fileInputRef.current?.click() }
                        : { label: 'Import from Excel', disabled: true, disabledReason: deny('import suppliers'), onClick: () => {} },
                    { label: 'Download import template', href: '/api/v1/purchase/suppliers/template' },
                    can('suppliers.export')
                        ? { label: 'Export as PDF', href: '/api/v1/purchase/suppliers/export-pdf' }
                        : { label: 'Export as PDF', disabled: true, disabledReason: deny('export suppliers'), onClick: () => {} },
                    {
                        label: 'Delete every supplier', danger: true, onClick: () => s.setDeleteAllOpen(true),
                        disabled: !can('suppliers.delete-all') || nothingToDelete,
                        disabledReason: !can('suppliers.delete-all')
                            ? 'You do not have permission to delete every supplier. Ask an Admin.'
                            : 'There are no suppliers to delete.',
                    },
                ]}
            />

            <ActionSheet
                open={sheet === 'category'}
                onClose={() => setSheet(null)}
                title="Category"
                options={[
                    { label: 'All categories', onClick: () => setCategory(null) },
                    ...categories.map((c) => ({ label: c, onClick: () => setCategory(c) })),
                ]}
            />

            <ActionSheet
                open={!!picked}
                onClose={() => setPicked(null)}
                title={picked ? [picked.name, picked.contact_person].filter(Boolean).join(' · ') : ''}
                options={picked ? [
                    picked.phone && { label: `Call ${picked.phone}`, href: `tel:${picked.phone}` },
                    picked.whatsapp && {
                        label: `WhatsApp ${picked.whatsapp}`,
                        href: `https://wa.me/${String(picked.whatsapp).replace(/[^\d]/g, '')}`, newTab: true,
                    },
                    picked.email && { label: `Email ${picked.email}`, href: `mailto:${picked.email}` },
                    {
                        label: 'Edit supplier', onClick: () => s.openEdit(picked),
                        disabled: !can('suppliers.edit'), disabledReason: deny('edit suppliers'),
                    },
                    {
                        label: 'Delete supplier', danger: true, onClick: () => s.setDeleting(picked),
                        disabled: !can('suppliers.delete'), disabledReason: deny('delete suppliers'),
                    },
                ].filter(Boolean) : []}
            />

            {s.modalOpen && (
                <SupplierModal
                    supplier={s.editing}
                    onSaved={s.handleSaved}
                    onCancel={() => s.setModalOpen(false)}
                />
            )}
            <ConfirmModal
                open={!!s.deleting}
                title="Delete supplier?"
                body={s.deleting ? `This will permanently remove "${s.deleting.name}".` : ''}
                onConfirm={s.handleDeleteConfirmed}
                onCancel={() => s.setDeleting(null)}
            />
            <ConfirmModal
                open={s.deleteAllOpen}
                title="Delete every supplier?"
                body={`This permanently removes all ${s.suppliers.length} supplier(s). Any supplier named by a purchase order, invoice, GRN, payment, RFQ or quote is kept — those records have to keep saying who they were with. This cannot be undone.`}
                confirmWord="DELETE ALL"
                onConfirm={s.confirmDeleteAll}
                onCancel={() => s.setDeleteAllOpen(false)}
            />
        </MobilePage>
    );
}
