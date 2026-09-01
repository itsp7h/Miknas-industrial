import { useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import GrnForm from '../../../components/purchase/grn/GrnForm';
import GrnTable from '../../../components/purchase/grn/GrnTable';
import useGrnList from '../../../components/purchase/grn/useGrnList';

export default function GrnListPage() {
    const g = useGrnList();
    const [params, setParams] = useSearchParams();
    const presetOrderId = params.get('purchase_order_id');

    // Arriving with ?purchase_order_id=… (from a purchase order or the pipeline)
    // opens the form with that order preselected — what the Blade
    // grns/create?purchase_order_id=… page did.
    useEffect(() => {
        if (presetOrderId) g.setModalOpen(true);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [presetOrderId]);

    function closeModal() {
        g.setModalOpen(false);
        if (presetOrderId) {
            params.delete('purchase_order_id');
            setParams(params, { replace: true });
        }
    }

    return (
        <div>
            <div className="page-header">
                <div>
                    <h1 className="page-title">Goods Receipt Notes</h1>
                    <p className="page-subtitle">Record goods received from suppliers</p>
                </div>
                <button type="button" onClick={() => g.setModalOpen(true)} className="btn-primary">+ New GRN</button>
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
                        value={g.query}
                        onChange={(e) => g.setQuery(e.target.value)}
                        placeholder="Search GRN, PO, supplier, warehouse…"
                        aria-label="Search GRNs"
                        autoComplete="off"
                        style={{ padding: '8px 14px 8px 34px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13.5, width: 340, outline: 'none' }}
                    />
                </div>
                <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                    {g.query ? `${g.filtered.length} of ${g.grns.length} GRNs` : `${g.grns.length} GRNs`}
                </div>
            </div>

            <GrnTable grns={g.filtered} onConfirm={g.setConfirming} onDelete={g.setDeleting} />

            <Modal open={g.modalOpen} title="New Goods Receipt Note" onClose={closeModal}>
                <GrnForm presetOrderId={presetOrderId} onSaved={g.handleSaved} onCancel={closeModal} />
            </Modal>
            <ConfirmModal
                open={!!g.confirming}
                title="Confirm this GRN?"
                body={g.confirming
                    ? `${g.confirming.grn_number} will raise stock at ${g.confirming.warehouse_name ?? 'the warehouse'} and update the purchase order. This cannot be undone.`
                    : ''}
                onConfirm={g.handleConfirm}
                onCancel={() => g.setConfirming(null)}
            />
            <ConfirmModal
                open={!!g.deleting}
                title="Delete this GRN?"
                body={g.deleting ? `${g.deleting.grn_number} will be permanently removed.` : ''}
                onConfirm={g.handleDeleteConfirmed}
                onCancel={() => g.setDeleting(null)}
            />
        </div>
    );
}
