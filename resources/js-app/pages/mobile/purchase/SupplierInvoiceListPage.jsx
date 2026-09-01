import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import SupplierInvoiceForm from '../../../components/purchase/invoice/SupplierInvoiceForm';
import useSupplierInvoiceList from '../../../components/purchase/invoice/useSupplierInvoiceList';
import { STATUS_LABELS, badgeClassFor, formatDate, money } from '../../../components/purchase/invoice/invoiceStyles';

export default function SupplierInvoiceListPage() {
    const v = useSupplierInvoiceList();

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Supplier Invoices</h1>
                <p className="page-subtitle">Track and manage supplier invoices</p>
            </div>

            <button
                type="button" onClick={v.openCreate} className="btn-primary"
                style={{ width: '100%', justifyContent: 'center', marginBottom: 14 }}
            >
                + New Invoice
            </button>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={v.query}
                    onChange={(e) => v.setQuery(e.target.value)}
                    placeholder="Search invoice, supplier, PO, status…"
                    aria-label="Search supplier invoices"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {v.query ? `${v.filtered.length} of ${v.invoices.length} invoices` : `${v.invoices.length} invoices`}
                </div>
            </div>

            {v.filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {v.query ? 'No invoices match that search.' : 'No invoices found.'}
                </p>
            )}

            {/* A nine-column table cannot work on a phone; the money figures are the
                point, so each card leads with total / paid / outstanding. */}
            {v.filtered.map((invoice) => {
                const outstanding = Number(invoice.outstanding ?? 0);

                return (
                    <div key={invoice.id} style={{
                        background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                        padding: 12, marginBottom: 8,
                    }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                            <span className="font-mono" style={{ fontWeight: 600, color: '#0f172a' }}>
                                {invoice.invoice_number}
                            </span>
                            <span className={badgeClassFor(invoice.status)} style={{ flexShrink: 0 }}>
                                {STATUS_LABELS[invoice.status] ?? invoice.status}
                            </span>
                        </div>
                        <div style={{ fontSize: 13, color: '#64748b', marginTop: 2 }}>{invoice.supplier_name ?? '—'}</div>
                        <div style={{ fontSize: 12, color: '#94a3b8' }}>
                            {invoice.po_number && <span className="font-mono">{invoice.po_number} · </span>}
                            {formatDate(invoice.invoice_date)}
                        </div>

                        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8, marginTop: 8, fontSize: 12 }}>
                            <div>
                                <div style={{ color: '#94a3b8' }}>Total</div>
                                <div style={{ fontWeight: 600, color: '#1f2937' }}>{money(invoice.total_amount)}</div>
                            </div>
                            <div>
                                <div style={{ color: '#94a3b8' }}>Paid</div>
                                <div style={{ fontWeight: 600, color: '#15803d' }}>{money(invoice.paid_amount)}</div>
                            </div>
                            <div style={{ textAlign: 'right' }}>
                                <div style={{ color: '#94a3b8' }}>Outstanding</div>
                                <div style={{ fontWeight: 700, color: outstanding > 0 ? '#dc2626' : '#6b7280' }}>
                                    {money(invoice.outstanding)}
                                </div>
                            </div>
                        </div>

                        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 10 }}>
                            <a href={`/purchase/payments/create?invoice_id=${invoice.id}`} className="btn-success btn-sm">Pay</a>
                            <button type="button" onClick={() => v.openEdit(invoice)} className="btn-secondary btn-sm">Edit</button>
                            <button type="button" onClick={() => v.setDeleting(invoice)} className="btn-danger btn-sm">Delete</button>
                        </div>
                    </div>
                );
            })}

            <Modal
                open={v.modalOpen}
                title={v.editing ? `Edit ${v.editing.invoice_number}` : 'New Supplier Invoice'}
                onClose={() => v.setModalOpen(false)}
            >
                <SupplierInvoiceForm invoice={v.editing} onSaved={v.handleSaved} onCancel={() => v.setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!v.deleting}
                title="Delete this invoice?"
                body={v.deleting ? `${v.deleting.invoice_number} will be permanently removed.` : ''}
                onConfirm={v.handleDeleteConfirmed}
                onCancel={() => v.setDeleting(null)}
            />
        </div>
    );
}
