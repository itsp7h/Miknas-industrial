import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import SupplierInvoiceForm from '../../../components/purchase/invoice/SupplierInvoiceForm';
import SupplierInvoiceTable from '../../../components/purchase/invoice/SupplierInvoiceTable';
import useSupplierInvoiceList from '../../../components/purchase/invoice/useSupplierInvoiceList';

export default function SupplierInvoiceListPage() {
    const v = useSupplierInvoiceList();

    return (
        <div>
            <div className="page-header">
                <div>
                    <h1 className="page-title">Supplier Invoices</h1>
                    <p className="page-subtitle">Track and manage supplier invoices</p>
                </div>
                <button type="button" onClick={v.openCreate} className="btn-primary">+ New Invoice</button>
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
                        value={v.query}
                        onChange={(e) => v.setQuery(e.target.value)}
                        placeholder="Search invoice, supplier, PO, status…"
                        aria-label="Search supplier invoices"
                        autoComplete="off"
                        style={{ padding: '8px 14px 8px 34px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13.5, width: 340, outline: 'none' }}
                    />
                </div>
                <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                    {v.query ? `${v.filtered.length} of ${v.invoices.length} invoices` : `${v.invoices.length} invoices`}
                </div>
            </div>

            <SupplierInvoiceTable invoices={v.filtered} onEdit={v.openEdit} onDelete={v.setDeleting} />

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
