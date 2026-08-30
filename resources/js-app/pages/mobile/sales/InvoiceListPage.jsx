import { useMemo, useState } from 'react';
import Modal from '../../../components/ui/Modal';
import Button from '../../../components/ui/Button';
import InvoiceForm from '../../../components/sales/invoice/InvoiceForm';
import { INVOICE_STATUS_LABELS, INVOICE_STATUS_COLOURS } from '../../../components/sales/invoice/statuses';
import { money } from '../../../components/sales/order/statuses';
import useLiveList from '../../../hooks/useLiveList';
import { useToast } from '../../../components/ui/Toast';

export default function InvoiceListPage() {
    const { items: invoices, upsertItem } = useLiveList({
        endpoint: '/sales/invoices',
        channel: 'sales',
        event: '.sales-invoice.saved',
        mergeKey: 'id',
        errorMessage: 'Failed to load invoices.',
    });
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const { showToast } = useToast();

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return invoices;
        return invoices.filter((i) =>
            [i.invoice_number, i.customer_name, i.order_number, INVOICE_STATUS_LABELS[i.status] ?? i.status]
                .some((field) => String(field ?? '').toLowerCase().includes(q))
        );
    }, [invoices, query]);

    function handleSaved(invoice) {
        upsertItem(invoice);
        setModalOpen(false);
        showToast(`${invoice.invoice_number} created.`, 'success');
    }

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <h1 style={{ fontSize: 18, fontWeight: 700 }}>Sales Invoices</h1>
                <Button onClick={() => setModalOpen(true)}>New</Button>
            </div>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search" value={query} onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search invoices…" aria-label="Search invoices"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {query ? `${filtered.length} of ${invoices.length} invoices` : `${invoices.length} invoices`}
                </div>
            </div>

            {filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {query ? 'No invoices match that search.' : 'No invoices yet.'}
                </p>
            )}

            {filtered.map((invoice) => (
                <div key={invoice.id} style={{ border: '1px solid #e2e8f0', borderRadius: 8, padding: 12, marginBottom: 8 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <span style={{ fontWeight: 600 }}>{invoice.invoice_number}</span>
                        <span style={{ fontWeight: 700 }}>{money(invoice.total_amount)}</span>
                    </div>
                    <div style={{ fontSize: 13, color: '#64748b' }}>{invoice.customer_name ?? '—'}</div>
                    <div style={{ fontSize: 12, color: '#94a3b8' }}>{invoice.invoice_date}</div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 4 }}>
                        <span style={{ fontSize: 12, fontWeight: 600, color: INVOICE_STATUS_COLOURS[invoice.status] }}>
                            {INVOICE_STATUS_LABELS[invoice.status] ?? invoice.status}
                        </span>
                        <span style={{ fontSize: 12, color: Number(invoice.balance_due) > 0 ? '#dc2626' : '#16a34a' }}>
                            {money(invoice.balance_due)} due
                        </span>
                    </div>
                </div>
            ))}

            <Modal open={modalOpen} title="New Invoice" onClose={() => setModalOpen(false)}>
                <InvoiceForm onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
        </div>
    );
}
