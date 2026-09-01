import Modal from '../../../components/ui/Modal';
import StockMovementForm from '../../../components/inventory/movement/StockMovementForm';
import MovementTable from '../../../components/inventory/movement/MovementTable';
import useMovementList from '../../../components/inventory/movement/useMovementList';
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
            <div className="page-header">
                <div>
                    <h1 className="page-title">Stock Movements</h1>
                    <p className="page-subtitle">Track all inventory movements</p>
                </div>
                <button type="button" onClick={() => m.setModalOpen(true)} className="btn-primary">
                    + Manual Adjustment
                </button>
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
                        value={m.query}
                        onChange={(e) => m.setQuery(e.target.value)}
                        placeholder="Search item, warehouse, type, reference…"
                        aria-label="Search movements"
                        autoComplete="off"
                        style={{ padding: '8px 14px 8px 34px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13.5, width: 340, outline: 'none' }}
                    />
                </div>
                <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                    {m.query ? `${m.filtered.length} of ${m.movements.length} movements` : `${m.movements.length} movements`}
                </div>
            </div>

            <MovementTable movements={m.filtered} />

            <Modal open={m.modalOpen} title="Manual Stock Adjustment" onClose={() => m.setModalOpen(false)}>
                <StockMovementForm onSaved={handleSaved} onCancel={() => m.setModalOpen(false)} />
            </Modal>
        </div>
    );
}
