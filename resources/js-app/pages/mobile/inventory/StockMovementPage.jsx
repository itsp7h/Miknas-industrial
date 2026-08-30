import { useMemo, useState } from 'react';
import Modal from '../../../components/ui/Modal';
import Button from '../../../components/ui/Button';
import StockMovementForm, { TYPE_LABELS } from '../../../components/inventory/movement/StockMovementForm';
import useLiveList from '../../../hooks/useLiveList';
import { useToast } from '../../../components/ui/Toast';

const TYPE_COLOURS = { in: '#16a34a', out: '#dc2626', adjustment: '#ca8a04' };

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: '2-digit', year: 'numeric' });
}

export default function StockMovementPage() {
    const { items: movements, upsertItem } = useLiveList({
        endpoint: '/inventory/movements',
        channel: 'inventory',
        event: '.stock-movement.recorded',
        mergeKey: 'id',
        errorMessage: 'Failed to load stock movements.',
    });
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const { showToast } = useToast();

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return movements;
        return movements.filter((m) =>
            [m.item_name, m.warehouse_name, TYPE_LABELS[m.type] ?? m.type, m.notes]
                .some((field) => String(field ?? '').toLowerCase().includes(q))
        );
    }, [movements, query]);

    function handleSaved(movement) {
        upsertItem(movement);
        setModalOpen(false);
        showToast('Stock movement recorded.', 'success');
    }

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <h1 style={{ fontSize: 18, fontWeight: 700 }}>Stock Movements</h1>
                <Button onClick={() => setModalOpen(true)}>New</Button>
            </div>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search movements…"
                    aria-label="Search movements"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {query ? `${filtered.length} of ${movements.length} movements` : `${movements.length} movements`}
                </div>
            </div>

            {filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {query ? 'No movements match that search.' : 'No stock movements yet.'}
                </p>
            )}

            {filtered.map((movement) => (
                <div key={movement.id} style={{ border: '1px solid #e2e8f0', borderRadius: 8, padding: 12, marginBottom: 8 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <span style={{ fontWeight: 600 }}>{movement.item_name ?? '—'}</span>
                        <span style={{ color: TYPE_COLOURS[movement.type], fontWeight: 600, fontSize: 13 }}>
                            {TYPE_LABELS[movement.type] ?? movement.type} {movement.quantity}
                        </span>
                    </div>
                    <div style={{ fontSize: 13, color: '#64748b' }}>{movement.warehouse_name ?? '—'}</div>
                    <div style={{ fontSize: 12, color: '#94a3b8' }}>{formatDate(movement.created_at)}</div>
                    {movement.notes && <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>{movement.notes}</div>}
                </div>
            ))}

            <Modal open={modalOpen} title="Record Stock Movement" onClose={() => setModalOpen(false)}>
                <StockMovementForm onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
        </div>
    );
}
