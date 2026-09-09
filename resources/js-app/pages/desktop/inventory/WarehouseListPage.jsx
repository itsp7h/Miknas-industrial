import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import WarehouseForm from '../../../components/inventory/warehouse/WarehouseForm';
import WarehouseTable from '../../../components/inventory/warehouse/WarehouseTable';
import useWarehouseList from '../../../components/inventory/warehouse/useWarehouseList';

export default function WarehouseListPage() {
    const w = useWarehouseList();

    return (
        <div>
            <div className="page-header">
                <div>
                    <h1 className="page-title">Warehouses</h1>
                    <p className="page-subtitle">Manage storage locations</p>
                </div>
                <button type="button" onClick={w.openCreate} className="btn-primary">+ Add Warehouse</button>
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
                        value={w.query}
                        onChange={(e) => w.setQuery(e.target.value)}
                        placeholder="Search code, name, location…"
                        aria-label="Search warehouses"
                        autoComplete="off"
                        style={{ padding: '8px 14px 8px 34px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13.5, width: 340, outline: 'none' }}
                    />
                </div>
                <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                    {w.query ? `${w.filtered.length} of ${w.warehouses.length} warehouses` : `${w.warehouses.length} warehouses`}
                </div>
            </div>

            <WarehouseTable warehouses={w.filtered} onEdit={w.openEdit} onDelete={w.setDeleting} />

            <Modal
                open={w.modalOpen}
                title={w.editing ? `Edit ${w.editing.name}` : 'New Warehouse'}
                onClose={() => w.setModalOpen(false)}
            >
                <WarehouseForm warehouse={w.editing} onSaved={w.handleSaved} onCancel={() => w.setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!w.deleting}
                title="Delete this warehouse?"
                body={w.deleting
                    ? `"${w.deleting.name}" will be permanently removed. A warehouse holding stock is deactivated instead.`
                    : ''}
                onConfirm={w.handleDeleteConfirmed}
                onCancel={() => w.setDeleting(null)}
            />
        </div>
    );
}
