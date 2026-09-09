import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import WarehouseForm from '../../../components/inventory/warehouse/WarehouseForm';
import useWarehouseList from '../../../components/inventory/warehouse/useWarehouseList';

export default function WarehouseListPage() {
    const w = useWarehouseList();

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Warehouses</h1>
                <p className="page-subtitle">Manage storage locations</p>
            </div>

            <button
                type="button" onClick={w.openCreate} className="btn-primary"
                style={{ width: '100%', justifyContent: 'center', marginBottom: 14 }}
            >
                + Add Warehouse
            </button>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={w.query}
                    onChange={(e) => w.setQuery(e.target.value)}
                    placeholder="Search code, name, location…"
                    aria-label="Search warehouses"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {w.query ? `${w.filtered.length} of ${w.warehouses.length} warehouses` : `${w.warehouses.length} warehouses`}
                </div>
            </div>

            {w.filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {w.query ? 'No warehouses match that search.' : 'No warehouses found.'}
                </p>
            )}

            {w.filtered.map((warehouse) => (
                <div key={warehouse.id} style={{
                    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                    padding: 12, marginBottom: 8,
                }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontWeight: 600, color: '#0f172a', fontSize: 14 }}>{warehouse.name}</div>
                            <div className="font-mono" style={{ fontSize: 11, color: '#94a3b8' }}>{warehouse.code}</div>
                        </div>
                        <span className={warehouse.is_active ? 'badge-green' : 'badge-gray'} style={{ flexShrink: 0 }}>
                            {warehouse.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </div>

                    {warehouse.location && (
                        <div style={{ fontSize: 13, color: '#64748b', marginTop: 4 }}>{warehouse.location}</div>
                    )}

                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 10 }}>
                        <button type="button" onClick={() => w.openEdit(warehouse)} className="btn-secondary btn-sm">Edit</button>
                        <button type="button" onClick={() => w.setDeleting(warehouse)} className="btn-danger btn-sm">Delete</button>
                    </div>
                </div>
            ))}

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
