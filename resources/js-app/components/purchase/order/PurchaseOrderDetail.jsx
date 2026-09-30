import { Link } from 'react-router-dom';
import LpoDocumentFrame from './LpoDocumentFrame';
import { formatDate } from './statuses';

/**
 * An order's page: the LPO exactly as it prints and downloads (see
 * LpoDocumentFrame), then the goods received against it. Desktop and mobile
 * share it; the frame scales itself to the column.
 */
export default function PurchaseOrderDetail({ order }) {
    if (!order) return null;

    const grns = order.goods_receipt_notes ?? [];

    return (
        <div>
            <LpoDocumentFrame order={order} />

            {grns.length > 0 && (
                <div style={{
                    maxWidth: 794, margin: '0 auto', background: '#fff', borderRadius: 16,
                    boxShadow: '0 4px 24px rgba(0,0,0,.08)', overflow: 'hidden',
                }}>
                    <div style={{ padding: '16px 24px', borderBottom: '1px solid #e2e8f0' }}>
                        <h2 style={{ fontSize: 14, fontWeight: 700, color: '#0f172a', margin: 0 }}>
                            Goods Receipt Notes
                        </h2>
                    </div>
                    <div style={{ padding: '8px 24px 16px' }}>
                        {grns.map((grn) => (
                            <div key={grn.id} style={{
                                display: 'flex', justifyContent: 'space-between', gap: 8,
                                padding: '8px 0', borderBottom: '1px solid #f1f5f9', fontSize: 13, flexWrap: 'wrap',
                            }}>
                                <span style={{ fontFamily: 'monospace', color: '#334155' }}>{grn.grn_number}</span>
                                <span style={{ color: '#64748b' }}>{grn.warehouse_name ?? '—'}</span>
                                <span style={{ color: '#94a3b8' }}>{formatDate(grn.received_date)}</span>
                                <Link to={`/app/purchase/grns/${grn.id}`} style={{ color: '#2563eb' }}>View</Link>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
