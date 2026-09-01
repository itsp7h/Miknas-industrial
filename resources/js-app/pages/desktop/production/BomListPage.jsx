import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import BomForm from '../../../components/production/BomForm';
import BomProductCard from '../../../components/production/bom/BomProductCard';
import useBomList from '../../../components/production/bom/useBomList';

export default function BomListPage() {
    const b = useBomList();

    return (
        <div>
            <div className="page-header">
                <div>
                    <h1 className="page-title">Bill of Materials</h1>
                    <p className="page-subtitle">Define material requirements for each product</p>
                </div>
                <button type="button" onClick={b.openCreate} className="btn-primary">+ Add BOM Entry</button>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12, gap: 12 }}>
                <div style={{ position: 'relative' }}>
                    <svg
                        style={{ position: 'absolute', left: 11, top: '50%', transform: 'translateY(-50%)', color: '#94a3b8', pointerEvents: 'none' }}
                        width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                    </svg>
                    <input
                        type="text"
                        value={b.query}
                        onChange={(e) => b.setQuery(e.target.value)}
                        placeholder="Search product, material, UOM…"
                        aria-label="Search bill of materials"
                        autoComplete="off"
                        style={{ padding: '8px 14px 8px 34px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13.5, width: 340, outline: 'none' }}
                    />
                </div>
                <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                    {b.query ? `${b.filtered.length} of ${b.rows.length} entries` : `${b.rows.length} entries`}
                </div>
            </div>

            {b.groups.map((group) => (
                <BomProductCard key={group.key} group={group} onEdit={b.openEdit} onDelete={b.setDeleting} />
            ))}

            {b.groups.length === 0 && (
                <div className="card card-body text-center text-gray-400">
                    {b.query ? 'No BOM entries match that search.' : (
                        <>
                            No BOM entries found.{' '}
                            <button type="button" onClick={b.openCreate} className="text-blue-600 hover:underline">Add the first one</button>.
                        </>
                    )}
                </div>
            )}

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
