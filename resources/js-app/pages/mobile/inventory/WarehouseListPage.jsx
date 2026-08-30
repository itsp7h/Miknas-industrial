import { useMemo, useState } from 'react';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import Button from '../../../components/ui/Button';
import WarehouseForm from '../../../components/inventory/warehouse/WarehouseForm';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete } from '../../../api/client';
import { useToast } from '../../../components/ui/Toast';

export default function WarehouseListPage() {
    const { items: warehouses, upsertItem, removeItem, refetch } = useLiveList({
        endpoint: '/inventory/warehouses',
        channel: 'inventory',
        event: '.warehouse.saved',
        deleteEvent: '.warehouse.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load warehouses.',
    });
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const { showToast } = useToast();

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return warehouses;
        return warehouses.filter((w) =>
            [w.code, w.name, w.location].some((field) => String(field ?? '').toLowerCase().includes(q))
        );
    }, [warehouses, query]);

    function handleSaved(warehouse) {
        upsertItem(warehouse);
        setModalOpen(false);
        showToast('Warehouse saved.', 'success');
    }

    async function handleDeleteConfirmed() {
        const warehouse = deleting;
        setDeleting(null);
        try {
            const result = await apiDelete(`/inventory/warehouses/${warehouse.id}`);
            if (result.deactivated) {
                showToast(result.message, 'info');
                await refetch();
            } else {
                removeItem(warehouse.id);
                showToast('Warehouse deleted.', 'success');
            }
        } catch (err) {
            showToast(err.message || 'Failed to delete warehouse.', 'error');
        }
    }

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <h1 style={{ fontSize: 18, fontWeight: 700 }}>Warehouses</h1>
                <Button onClick={() => { setEditing(null); setModalOpen(true); }}>New</Button>
            </div>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search warehouses…"
                    aria-label="Search warehouses"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {query ? `${filtered.length} of ${warehouses.length} warehouses` : `${warehouses.length} warehouses`}
                </div>
            </div>

            {filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {query ? 'No warehouses match that search.' : 'No warehouses yet.'}
                </p>
            )}

            {filtered.map((warehouse) => (
                <div key={warehouse.id} style={{ border: '1px solid #e2e8f0', borderRadius: 8, padding: 12, marginBottom: 8 }}>
                    <div onClick={() => { setEditing(warehouse); setModalOpen(true); }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                            <span style={{ fontWeight: 600 }}>{warehouse.name}</span>
                            <span style={{ fontSize: 12, color: '#64748b' }}>{warehouse.code}</span>
                        </div>
                        <div style={{ fontSize: 13, color: '#64748b' }}>{warehouse.location || '—'}</div>
                        <div style={{ fontSize: 12, color: warehouse.is_active ? '#16a34a' : '#dc2626' }}>
                            {warehouse.is_active ? 'Active' : 'Inactive'}
                        </div>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 8 }}>
                        <Button variant="link-danger" onClick={() => setDeleting(warehouse)}>Delete</Button>
                    </div>
                </div>
            ))}

            <Modal open={modalOpen} title={editing ? 'Edit Warehouse' : 'New Warehouse'} onClose={() => setModalOpen(false)}>
                <WarehouseForm warehouse={editing} onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!deleting}
                title="Delete warehouse?"
                body={deleting ? `This will permanently remove "${deleting.name}". Warehouses holding stock are deactivated instead.` : ''}
                onConfirm={handleDeleteConfirmed}
                onCancel={() => setDeleting(null)}
            />
        </div>
    );
}
