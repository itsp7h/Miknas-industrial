import { useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import SupplierPaymentForm from '../../../components/purchase/payment/SupplierPaymentForm';
import useSupplierPaymentList from '../../../components/purchase/payment/useSupplierPaymentList';
import { methodLabel, formatDate, money } from '../../../components/purchase/payment/paymentStyles';

export default function SupplierPaymentListPage() {
    const p = useSupplierPaymentList();
    const [params, setParams] = useSearchParams();
    const presetInvoiceId = params.get('invoice_id');

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
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Supplier Payments</h1>
                <p className="page-subtitle">Payment history to suppliers</p>
            </div>

            <button
                type="button" onClick={p.openCreate} className="btn-primary"
                style={{ width: '100%', justifyContent: 'center', marginBottom: 14 }}
            >
                + Record Payment
            </button>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={p.query}
                    onChange={(e) => p.setQuery(e.target.value)}
                    placeholder="Search invoice, supplier, reference, method…"
                    aria-label="Search supplier payments"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {p.query ? `${p.filtered.length} of ${p.payments.length} payments` : `${p.payments.length} payments`}
                </div>
            </div>

            {p.filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {p.query ? 'No payments match that search.' : 'No payments recorded.'}
                </p>
            )}

            {p.filtered.map((payment) => (
                <div key={payment.id} style={{
                    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                    padding: 12, marginBottom: 8,
                }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <span className="font-mono" style={{ fontWeight: 600, color: '#0f172a' }}>
                            {payment.invoice_number ?? '-'}
                        </span>
                        <span style={{ fontWeight: 700, color: '#1f2937' }}>{money(payment.amount)}</span>
                    </div>
                    <div style={{ fontSize: 13, color: '#64748b', marginTop: 2 }}>{payment.supplier_name ?? '—'}</div>
                    <div style={{ fontSize: 12, color: '#94a3b8' }}>
                        {formatDate(payment.payment_date)} · {methodLabel(payment.payment_method)}
                        {payment.reference_number && ` · ${payment.reference_number}`}
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 10 }}>
                        <button type="button" onClick={() => p.openEdit(payment)} className="btn-secondary btn-sm">Edit</button>
                        <button type="button" onClick={() => p.setDeleting(payment)} className="btn-danger btn-sm">Delete</button>
                    </div>
                </div>
            ))}

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
