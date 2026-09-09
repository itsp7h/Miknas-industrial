import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import BomForm from '../../../components/production/BomForm';
import useBomList, { num } from '../../../components/production/bom/useBomList';

export default function BomListPage() {
    const b = useBomList();

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Bill of Materials</h1>
                <p className="page-subtitle">Define material requirements for each product</p>
            </div>

            <button
                type="button" onClick={b.openCreate} className="btn-primary"
                style={{ width: '100%', justifyContent: 'center', marginBottom: 14 }}
            >
                + Add BOM Entry
            </button>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={b.query}
                    onChange={(e) => b.setQuery(e.target.value)}
                    placeholder="Search product, material, UOM…"
                    aria-label="Search bill of materials"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {b.query ? `${b.filtered.length} of ${b.rows.length} entries` : `${b.rows.length} entries`}
                </div>
            </div>

            {b.groups.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {b.query ? 'No BOM entries match that search.' : 'No BOM entries found.'}
                </p>
            )}

            {/* Same grouping as desktop — the product header stays, its lines
                become cards rather than table rows. */}
            {b.groups.map((group) => (
                <div key={group.key} style={{ marginBottom: 18 }}>
                    <div className="bg-blue-50 border border-blue-100" style={{
                        borderRadius: 10, padding: '8px 12px', marginBottom: 8,
                        display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8,
                    }}>
                        <h3 className="font-semibold text-blue-800 text-sm" style={{ minWidth: 0 }}>{group.productName}</h3>
                        <span className="text-xs text-blue-500" style={{ flexShrink: 0 }}>{group.productCode}</span>
                    </div>

                    {group.lines.map((line) => (
                        <div key={line.id} style={{
                            background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                            padding: 12, marginBottom: 8,
                        }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                                <div style={{ fontWeight: 600, color: '#0f172a', fontSize: 14, minWidth: 0 }}>
                                    {line.raw_material_name ?? ''}
                                </div>
                                <div style={{ fontSize: 13, color: '#334155', flexShrink: 0 }}>
                                    {num(line.quantity_required)} {line.unit_of_measure}
                                </div>
                            </div>

                            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 10 }}>
                                <button type="button" onClick={() => b.openEdit(line)} className="btn-secondary btn-sm">Edit</button>
                                <button type="button" onClick={() => b.setDeleting(line)} className="btn-danger btn-sm">Delete</button>
                            </div>
                        </div>
                    ))}
                </div>
            ))}

            <Modal
                open={b.modalOpen}
                title={b.editing ? 'Edit BOM Entry' : 'Add BOM Entry'}
                onClose={() => b.setModalOpen(false)}
            >
                <BomForm entry={b.editing} onSaved={b.handleSaved} onCancel={() => b.setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!b.deleting}
                title="Delete this BOM entry?"
                body={b.deleting
                    ? `${b.deleting.raw_material_name} will no longer be required for ${b.deleting.product_name}.`
                    : ''}
                onConfirm={b.handleDeleteConfirmed}
                onCancel={() => b.setDeleting(null)}
            />
        </div>
    );
}
