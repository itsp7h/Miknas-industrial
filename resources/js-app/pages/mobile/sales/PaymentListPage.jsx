import Modal from '../../../components/ui/Modal';
import PaymentForm from '../../../components/sales/payment/PaymentForm';
import useReceiptList from '../../../components/sales/payment/useReceiptList';
import { methodLabel } from '../../../components/sales/payment/methods';
import { formatDate, money } from '../../../components/sales/order/statuses';

export default function PaymentListPage() {
    const r = useReceiptList();

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Customer Receipts</h1>
                <p className="page-subtitle">Payment receipts from customers</p>
            </div>

            <button
                type="button" onClick={() => r.setModalOpen(true)} className="btn-primary"
                style={{ width: '100%', justifyContent: 'center', marginBottom: 14 }}
            >
                + Record Receipt
            </button>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={r.query}
                    onChange={(e) => r.setQuery(e.target.value)}
                    placeholder="Search customer, invoice #, reference…"
                    aria-label="Search receipts"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {r.query ? `${r.filtered.length} of ${r.receipts.length} receipts` : `${r.receipts.length} receipts`}
                </div>
            </div>

            {r.filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {r.query ? 'No receipts match that search.' : 'No receipts recorded.'}
                </p>
            )}

            {r.filtered.map((receipt) => (
                <div key={receipt.id} style={{
                    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                    padding: 12, marginBottom: 8,
                }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontWeight: 600, color: '#0f172a', fontSize: 14 }}>{receipt.customer_name ?? ''}</div>
                            <div className="font-mono" style={{ fontSize: 11, color: '#94a3b8' }}>{receipt.invoice_number ?? '-'}</div>
                        </div>
                        {/* Money coming in — read as a credit. */}
                        <div style={{ color: '#16a34a', fontWeight: 700, flexShrink: 0 }}>{money(receipt.amount)}</div>
                    </div>

                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5, color: '#64748b', marginTop: 6, gap: 8 }}>
                        <span>{methodLabel(receipt.payment_method)}</span>
                        <span>{formatDate(receipt.receipt_date)}</span>
                    </div>

                    {receipt.reference_number && (
                        <div style={{ fontSize: 12, color: '#94a3b8', marginTop: 4 }}>Ref {receipt.reference_number}</div>
                    )}
                </div>
            ))}

            <Modal open={r.modalOpen} title="Record Customer Receipt" onClose={() => r.setModalOpen(false)}>
                <PaymentForm
                    presetInvoiceId={r.presetInvoiceId}
                    onSaved={r.handleSaved}
                    onCancel={() => r.setModalOpen(false)}
                />
            </Modal>
        </div>
    );
}
