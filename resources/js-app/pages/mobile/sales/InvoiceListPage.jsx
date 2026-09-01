import { Link } from 'react-router-dom';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import InvoiceForm from '../../../components/sales/invoice/InvoiceForm';
import InvoiceEditForm from '../../../components/sales/invoice/InvoiceEditForm';
import useInvoiceList from '../../../components/sales/invoice/useInvoiceList';
import { badgeClassFor, statusLabel } from '../../../components/sales/invoice/statuses';
import { formatDate, money } from '../../../components/sales/order/statuses';

export default function InvoiceListPage() {
    const i = useInvoiceList();

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Sales Invoices</h1>
                <p className="page-subtitle">Track customer invoices and payments</p>
            </div>

            <button
                type="button" onClick={i.openCreate} className="btn-primary"
                style={{ width: '100%', justifyContent: 'center', marginBottom: 14 }}
            >
                + New Invoice
            </button>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={i.query}
                    onChange={(e) => i.setQuery(e.target.value)}
                    placeholder="Search invoice #, customer, SO #…"
                    aria-label="Search invoices"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {i.query ? `${i.filtered.length} of ${i.invoices.length} invoices` : `${i.invoices.length} invoices`}
                </div>
            </div>

            {i.filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {i.query ? 'No invoices match that search.' : 'No invoices found.'}
                </p>
            )}

            {i.filtered.map((invoice) => (
                <div key={invoice.id} style={{
                    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                    padding: 12, marginBottom: 8,
                }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontWeight: 600, color: '#0f172a', fontSize: 14 }}>{invoice.customer_name ?? ''}</div>
                            <div className="font-mono" style={{ fontSize: 11, color: '#94a3b8' }}>{invoice.invoice_number}</div>
                        </div>
                        <span className={badgeClassFor(invoice.status)} style={{ flexShrink: 0 }}>{statusLabel(invoice.status)}</span>
                    </div>

                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5, color: '#64748b', marginTop: 6, gap: 8 }}>
                        {invoice.sales_order_id
                            ? (
                                <Link to={`/app/sales/orders/${invoice.sales_order_id}`} className="text-blue-600 font-mono">
                                    {invoice.order_number}
                                </Link>
                            )
                            : <span>—</span>}
                        <span>{formatDate(invoice.invoice_date)}</span>
                    </div>

                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5, marginTop: 6, gap: 8 }}>
                        <span style={{ color: '#94a3b8' }}>
                            {money(invoice.total_amount)} total · <span className="text-green-700">{money(invoice.paid_amount)} paid</span>
                        </span>
                        {/* The balance is the number that decides what happens next. */}
                        <span className={Number(invoice.balance_due ?? 0) > 0 ? 'text-red-600 font-semibold' : 'text-gray-500'}>
                            {money(invoice.balance_due)}
                        </span>
                    </div>

                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 10, flexWrap: 'wrap' }}>
                        {invoice.status !== 'paid' && (
                            <Link to={`/app/sales/payments?invoice_id=${invoice.id}`} className="btn-success btn-sm">Receive</Link>
                        )}
                        <button type="button" onClick={() => i.openEdit(invoice)} className="btn-secondary btn-sm">Edit</button>
                        {Number(invoice.paid_amount ?? 0) === 0 && (
                            <button type="button" onClick={() => i.setDeleting(invoice)} className="btn-danger btn-sm">Delete</button>
                        )}
                    </div>
                </div>
            ))}

            <Modal
                open={i.modalOpen}
                title={i.editing ? `Edit ${i.editing.invoice_number}` : 'New Invoice'}
                onClose={() => i.setModalOpen(false)}
            >
                {i.editing
                    ? <InvoiceEditForm invoice={i.editing} onSaved={i.handleSaved} onCancel={() => i.setModalOpen(false)} />
                    : <InvoiceForm onSaved={i.handleSaved} onCancel={() => i.setModalOpen(false)} />}
            </Modal>
            <ConfirmModal
                open={!!i.deleting}
                title="Delete this invoice?"
                body={i.deleting
                    ? `${i.deleting.invoice_number} will be permanently removed, the customer's outstanding balance reduced, and its sales order returned to uninvoiced.`
                    : ''}
                onConfirm={i.handleDelete}
                onCancel={() => i.setDeleting(null)}
            />
        </div>
    );
}
