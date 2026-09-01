import Modal from '../../../components/ui/Modal';
import StockMovementForm from '../../../components/inventory/movement/StockMovementForm';
import useMovementList from '../../../components/inventory/movement/useMovementList';
import { formatDate, num, typeBadgeClass, typeLabel } from '../../../components/inventory/movement/movementStyles';
import { useToast } from '../../../components/ui/Toast';

export default function StockMovementPage() {
    const m = useMovementList();
    const { showToast } = useToast();

    function handleSaved(movement) {
        m.handleSaved(movement);
        showToast('Stock movement recorded.', 'success');
    }

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Stock Movements</h1>
                <p className="page-subtitle">Track all inventory movements</p>
            </div>

            <button
                type="button" onClick={() => m.setModalOpen(true)} className="btn-primary"
                style={{ width: '100%', justifyContent: 'center', marginBottom: 14 }}
            >
                + Manual Adjustment
            </button>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={m.query}
                    onChange={(e) => m.setQuery(e.target.value)}
                    placeholder="Search item, warehouse, type, reference…"
                    aria-label="Search movements"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {m.query ? `${m.filtered.length} of ${m.movements.length} movements` : `${m.movements.length} movements`}
                </div>
            </div>

            {m.filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {m.query ? 'No movements match that search.' : 'No movements recorded.'}
                </p>
            )}

            {m.filtered.map((movement) => (
                <div key={movement.id} style={{
                    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                    padding: 12, marginBottom: 8,
                }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontWeight: 600, color: '#0f172a', fontSize: 14 }}>
                                {movement.item_name ?? '—'}
                            </div>
                            <div className="font-mono" style={{ fontSize: 11, color: '#94a3b8' }}>
                                {movement.item_code ?? ''}
                            </div>
                        </div>
                        <div style={{ textAlign: 'right', flexShrink: 0 }}>
                            <span className={typeBadgeClass(movement.type)}>{typeLabel(movement.type)}</span>
                            <div style={{ fontWeight: 700, color: '#1f2937', marginTop: 4 }}>{num(movement.quantity)}</div>
                        </div>
                    </div>

                    <div style={{ fontSize: 12, color: '#64748b', marginTop: 6 }}>
                        {movement.warehouse_name ?? '—'} · {formatDate(movement.created_at)}
                    </div>
                    <div style={{ fontSize: 11, color: '#94a3b8' }}>
                        {movement.reference ?? 'Manual adjustment'}
                    </div>
                    {movement.notes && (
                        <div style={{ fontSize: 11, color: '#94a3b8' }}>{movement.notes}</div>
                    )}
                </div>
            ))}

            <Modal open={m.modalOpen} title="Manual Stock Adjustment" onClose={() => m.setModalOpen(false)}>
                <StockMovementForm onSaved={handleSaved} onCancel={() => m.setModalOpen(false)} />
            </Modal>
        </div>
    );
}
