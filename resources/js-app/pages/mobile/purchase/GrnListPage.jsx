import { useEffect } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import GrnForm from '../../../components/purchase/grn/GrnForm';
import useGrnList from '../../../components/purchase/grn/useGrnList';
import { STATUS_LABELS, badgeClassFor, formatDate } from '../../../components/purchase/grn/grnStyles';

export default function GrnListPage() {
    const g = useGrnList();
    const [params, setParams] = useSearchParams();
    const presetOrderId = params.get('purchase_order_id');

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
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Goods Receipt Notes</h1>
                <p className="page-subtitle">Record goods received from suppliers</p>
            </div>

            <button
                type="button" onClick={() => g.setModalOpen(true)} className="btn-primary"
                style={{ width: '100%', justifyContent: 'center', marginBottom: 14 }}
            >
                + New GRN
            </button>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={g.query}
                    onChange={(e) => g.setQuery(e.target.value)}
                    placeholder="Search GRN, PO, supplier, warehouse…"
                    aria-label="Search GRNs"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {g.query ? `${g.filtered.length} of ${g.grns.length} GRNs` : `${g.grns.length} GRNs`}
                </div>
            </div>

            {g.filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {g.query ? 'No GRNs match that search.' : 'No GRNs found.'}
                </p>
            )}

            {/* A seven-column table does not fit a phone, so each GRN is a card. */}
            {g.filtered.map((grn) => (
                <div key={grn.id} style={{
                    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                    padding: 12, marginBottom: 8,
                }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <Link to={`/app/purchase/grns/${grn.id}`} className="font-mono" style={{ fontWeight: 600, color: '#2563eb', textDecoration: 'none' }}>
                            {grn.grn_number}
                        </Link>
                        <span className={badgeClassFor(grn.status)} style={{ flexShrink: 0 }}>
                            {STATUS_LABELS[grn.status] ?? grn.status}
                        </span>
                    </div>
                    <div style={{ fontSize: 13, color: '#64748b', marginTop: 2 }}>{grn.supplier_name ?? '—'}</div>
                    <div style={{ fontSize: 12, color: '#94a3b8' }}>
                        <span className="font-mono">{grn.po_number}</span>
                        {grn.warehouse_name && ` · ${grn.warehouse_name}`}
                    </div>
                    <div style={{ fontSize: 12, color: '#94a3b8' }}>{formatDate(grn.received_date)}</div>

                    {grn.status !== 'confirmed' && (
                        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 10 }}>
                            <button type="button" onClick={() => g.setConfirming(grn)} className="btn-success btn-sm">Confirm</button>
                            <button type="button" onClick={() => g.setDeleting(grn)} className="btn-danger btn-sm">Delete</button>
                        </div>
                    )}
                </div>
            ))}

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
