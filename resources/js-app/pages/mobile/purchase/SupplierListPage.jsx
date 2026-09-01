import { useMemo } from 'react';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import SupplierForm from '../../../components/purchase/supplier/SupplierForm';
import SupplierStatCards from '../../../components/purchase/supplier/SupplierStatCards';
import SupplierToolbar from '../../../components/purchase/supplier/SupplierToolbar';
import SupplierSearch from '../../../components/purchase/supplier/SupplierSearch';
import useSupplierList from '../../../components/purchase/supplier/useSupplierList';
import { matchesQuery } from '../../../components/purchase/supplier/supplierStats';

export default function SupplierListPage() {
    const s = useSupplierList();

    const filtered = useMemo(
        () => s.suppliers.filter((supplier) => matchesQuery(supplier, s.query)),
        [s.suppliers, s.query]
    );

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Suppliers</h1>
                <p className="page-subtitle">Manage your supplier directory</p>
            </div>

            {/* The four toolbar actions go two-up rather than in one cramped row. */}
            <div style={{ marginBottom: 16 }}>
                <SupplierToolbar
                    fileInputRef={s.fileInputRef}
                    onImport={s.handleImport}
                    onCreate={s.openCreate}
                    compact
                />
            </div>

            <SupplierStatCards suppliers={s.suppliers} compact />

            <SupplierSearch
                query={s.query}
                onChange={s.setQuery}
                shown={filtered.length}
                total={s.suppliers.length}
                fullWidth
            />

            {filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {s.query ? 'No suppliers match that search.' : 'No suppliers found.'}
                </p>
            )}

            {/* An eight-column table is unreadable on a phone, so each supplier
                becomes a card carrying the same facts. */}
            {filtered.map((supplier) => (
                <div key={supplier.id} style={{
                    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                    padding: 12, marginBottom: 8,
                }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontWeight: 600, color: '#0f172a', fontSize: 14 }}>{supplier.name}</div>
                            {supplier.supplier_code && (
                                <div style={{ fontFamily: 'monospace', fontSize: 11, color: '#94a3b8' }}>
                                    {supplier.supplier_code}
                                </div>
                            )}
                        </div>
                        <span className={supplier.is_active ? 'badge-green' : 'badge-red'} style={{ flexShrink: 0 }}>
                            {supplier.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </div>

                    {supplier.category && (
                        <span style={{
                            display: 'inline-block', marginTop: 6, padding: '2px 9px', borderRadius: 20,
                            fontSize: 11, fontWeight: 600, background: '#f1f5f9', color: '#475569',
                        }}>
                            {supplier.category}
                        </span>
                    )}

                    <div style={{ fontSize: 12.5, color: '#334155', marginTop: 8 }}>
                        {supplier.contact_person && <div>{supplier.contact_person}</div>}
                        {supplier.email && (
                            <a href={`mailto:${supplier.email}`} style={{ color: '#2563eb', textDecoration: 'none', display: 'block' }}>
                                {supplier.email}
                            </a>
                        )}
                        {supplier.phone && (
                            <a href={`tel:${supplier.phone}`} style={{ color: '#334155', textDecoration: 'none', display: 'block' }}>
                                {supplier.phone}
                            </a>
                        )}
                        {supplier.whatsapp && (
                            <a
                                href={`https://wa.me/${String(supplier.whatsapp).replace(/^\+/, '')}`}
                                target="_blank" rel="noreferrer"
                                style={{ color: '#16a34a', textDecoration: 'none', display: 'block', fontWeight: 600 }}
                            >
                                WhatsApp {supplier.whatsapp}
                            </a>
                        )}
                        {supplier.address && (
                            <div style={{ color: '#64748b', fontSize: 12, marginTop: 2 }}>{supplier.address}</div>
                        )}
                    </div>

                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 12, marginTop: 10 }}>
                        <button
                            type="button" onClick={() => s.openEdit(supplier)}
                            style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#2563eb', fontSize: 13, fontWeight: 600, padding: 0 }}
                        >
                            Edit
                        </button>
                        <button
                            type="button" onClick={() => s.setDeleting(supplier)}
                            style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#dc2626', fontSize: 13, fontWeight: 600, padding: 0 }}
                        >
                            Delete
                        </button>
                    </div>
                </div>
            ))}

            <Modal
                open={s.modalOpen}
                title={s.editing ? 'Edit Supplier' : 'New Supplier'}
                onClose={() => s.setModalOpen(false)}
            >
                <SupplierForm supplier={s.editing} onSaved={s.handleSaved} onCancel={() => s.setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!s.deleting}
                title="Delete supplier?"
                body={s.deleting ? `This will permanently remove "${s.deleting.name}".` : ''}
                onConfirm={s.handleDeleteConfirmed}
                onCancel={() => s.setDeleting(null)}
            />
        </div>
    );
}
