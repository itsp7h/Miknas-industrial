import { Link } from 'react-router-dom';
import Modal from '../../ui/Modal';

const money = (value) => Number(value ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: 3, maximumFractionDigits: 3,
});

/**
 * Blade's select-GRN modal: choose which LPO you are receiving against, then
 * hand off to the GRN page with that order preselected. A fully received order
 * drops out — there is nothing left to receive.
 */
export default function RecordGrnModal({ open, request, onClose }) {
    const receivable = (request?.purchase_orders ?? []).filter((po) => po.status !== 'received');

    return (
        <Modal open={open} title="Record Goods Receipt" onClose={onClose}>
            <p style={{ fontSize: 12, color: '#64748b', margin: '-8px 0 14px' }}>
                Choose the supplier / LPO you&apos;re receiving against
            </p>

            {receivable.length === 0 && (
                <p style={{ padding: '32px 0', textAlign: 'center', color: '#94a3b8', fontSize: 13 }}>
                    No outstanding LPOs to receive against.
                </p>
            )}

            {receivable.map((po) => (
                <Link
                    key={po.id}
                    to={`/app/purchase/grns?purchase_order_id=${po.id}`}
                    onClick={onClose}
                    style={{
                        display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12,
                        padding: '14px 4px', textDecoration: 'none', borderBottom: '1px solid #f8fafc',
                    }}
                >
                    <div>
                        <div style={{ fontSize: 14, fontWeight: 700, color: '#0f172a' }}>{po.supplier_name ?? '—'}</div>
                        <div style={{ fontSize: 11, color: '#94a3b8', marginTop: 2 }}>
                            {po.po_number} · BD {money(po.total_amount)}
                        </div>
                    </div>
                    <span style={{ color: '#94a3b8', fontWeight: 700 }}>›</span>
                </Link>
            ))}

            <div className="mt-5 flex justify-end">
                <button type="button" onClick={onClose} className="btn-secondary">Cancel</button>
            </div>
        </Modal>
    );
}
