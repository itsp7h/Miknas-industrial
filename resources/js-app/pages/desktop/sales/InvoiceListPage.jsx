import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import InvoiceForm from '../../../components/sales/invoice/InvoiceForm';
import InvoiceEditForm from '../../../components/sales/invoice/InvoiceEditForm';
import InvoiceTable from '../../../components/sales/invoice/InvoiceTable';
import useInvoiceList from '../../../components/sales/invoice/useInvoiceList';

export default function InvoiceListPage() {
    const i = useInvoiceList();

    return (
        <div>
            <div className="page-header">
                <div>
                    <h1 className="page-title">Sales Invoices</h1>
                    <p className="page-subtitle">Track customer invoices and payments</p>
                </div>
                <button type="button" onClick={i.openCreate} className="btn-primary">+ New Invoice</button>
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
                        value={i.query}
                        onChange={(e) => i.setQuery(e.target.value)}
                        placeholder="Search invoice #, customer, SO #…"
                        aria-label="Search invoices"
                        autoComplete="off"
                        style={{ padding: '8px 14px 8px 34px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13.5, width: 340, outline: 'none' }}
                    />
                </div>
                <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                    {i.query ? `${i.filtered.length} of ${i.invoices.length} invoices` : `${i.invoices.length} invoices`}
                </div>
            </div>

            <InvoiceTable invoices={i.filtered} onEdit={i.openEdit} onDelete={i.setDeleting} />

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
                    ? `${i.deleting.invoice_number} will be permanently removed, the customer's outstanding balance reduced by ${i.deleting.total_amount}, and its sales order returned to uninvoiced.`
                    : ''}
                onConfirm={i.handleDelete}
                onCancel={() => i.setDeleting(null)}
            />
        </div>
    );
}
