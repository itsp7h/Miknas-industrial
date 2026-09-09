import Modal from '../../../components/ui/Modal';
import PaymentForm from '../../../components/sales/payment/PaymentForm';
import ReceiptTable from '../../../components/sales/payment/ReceiptTable';
import useReceiptList from '../../../components/sales/payment/useReceiptList';

export default function PaymentListPage() {
    const r = useReceiptList();

    return (
        <div>
            <div className="page-header">
                <div>
                    <h1 className="page-title">Customer Receipts</h1>
                    <p className="page-subtitle">Payment receipts from customers</p>
                </div>
                <button type="button" onClick={() => r.setModalOpen(true)} className="btn-primary">+ Record Receipt</button>
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
                        value={r.query}
                        onChange={(e) => r.setQuery(e.target.value)}
                        placeholder="Search customer, invoice #, reference…"
                        aria-label="Search receipts"
                        autoComplete="off"
                        style={{ padding: '8px 14px 8px 34px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13.5, width: 340, outline: 'none' }}
                    />
                </div>
                <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                    {r.query ? `${r.filtered.length} of ${r.receipts.length} receipts` : `${r.receipts.length} receipts`}
                </div>
            </div>

            <ReceiptTable receipts={r.filtered} />

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
