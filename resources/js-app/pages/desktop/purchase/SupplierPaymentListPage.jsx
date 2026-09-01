import { useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import SupplierPaymentForm from '../../../components/purchase/payment/SupplierPaymentForm';
import SupplierPaymentTable from '../../../components/purchase/payment/SupplierPaymentTable';
import useSupplierPaymentList from '../../../components/purchase/payment/useSupplierPaymentList';

export default function SupplierPaymentListPage() {
    const p = useSupplierPaymentList();
    const [params, setParams] = useSearchParams();
    const presetInvoiceId = params.get('invoice_id');

    // The invoices page's "Pay" button arrives with ?invoice_id=…, which the
    // Blade create page also honoured.
    useEffect(() => {
        if (presetInvoiceId) p.setModalOpen(true);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [presetInvoiceId]);

    function closeModal() {
        p.setModalOpen(false);
        if (presetInvoiceId) {
            params.delete('invoice_id');
            setParams(params, { replace: true });
        }
    }

    return (
        <div>
            <div className="page-header">
                <div>
                    <h1 className="page-title">Supplier Payments</h1>
                    <p className="page-subtitle">Payment history to suppliers</p>
                </div>
                <button type="button" onClick={p.openCreate} className="btn-primary">+ Record Payment</button>
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
                        value={p.query}
                        onChange={(e) => p.setQuery(e.target.value)}
                        placeholder="Search invoice, supplier, reference, method…"
                        aria-label="Search supplier payments"
                        autoComplete="off"
                        style={{ padding: '8px 14px 8px 34px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13.5, width: 340, outline: 'none' }}
                    />
                </div>
                <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                    {p.query ? `${p.filtered.length} of ${p.payments.length} payments` : `${p.payments.length} payments`}
                </div>
            </div>

            <SupplierPaymentTable payments={p.filtered} onEdit={p.openEdit} onDelete={p.setDeleting} />

            <Modal
                open={p.modalOpen}
                title={p.editing ? 'Edit Payment' : 'Record Supplier Payment'}
                onClose={closeModal}
            >
                <SupplierPaymentForm
                    payment={p.editing}
                    presetInvoiceId={presetInvoiceId}
                    onSaved={p.handleSaved}
                    onCancel={closeModal}
                />
            </Modal>
            <ConfirmModal
                open={!!p.deleting}
                title="Delete this payment?"
                body={p.deleting
                    ? `This removes the payment and restores the outstanding balance on ${p.deleting.invoice_number ?? 'its invoice'}.`
                    : ''}
                onConfirm={p.handleDeleteConfirmed}
                onCancel={() => p.setDeleting(null)}
            />
        </div>
    );
}
