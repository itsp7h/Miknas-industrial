import { useMemo, useState } from 'react';
import Card from '../../../components/ui/Card';
import Table from '../../../components/ui/Table';
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
    const [modalOpen, setModalOpen] = useState(false);
    const { showToast } = useToast();

    function handleSaved(invoice) {
        upsertItem(invoice);
        setModalOpen(false);
        showToast(`${invoice.invoice_number} created.`, 'success');
    }

    const columns = useMemo(() => [
        { key: 'invoice_number', label: 'Invoice #' },
        { key: 'customer_name', label: 'Customer', render: (row) => row.customer_name ?? '—' },
        { key: 'invoice_date', label: 'Date' },
        { key: 'total_amount', label: 'Total', render: (row) => money(row.total_amount) },
        { key: 'paid_amount', label: 'Paid', render: (row) => money(row.paid_amount) },
        {
            key: 'balance_due',
            label: 'Balance',
            render: (row) => (
                <span style={{ color: Number(row.balance_due) > 0 ? '#dc2626' : '#16a34a', fontWeight: 600 }}>
                    {money(row.balance_due)}
                </span>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (row) => (
                <span style={{ color: INVOICE_STATUS_COLOURS[row.status], fontWeight: 600 }}>
                    {INVOICE_STATUS_LABELS[row.status] ?? row.status}
                </span>
            ),
        },
    ], []);

    return (
        <Card title="Sales Invoices">
            <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 12 }}>
                <Button onClick={() => setModalOpen(true)}>New Invoice</Button>
            </div>

            <Table columns={columns} rows={invoices} rowKey={(row) => row.id} searchPlaceholder="Search invoices…" />

            <Modal open={modalOpen} title="New Invoice" onClose={() => setModalOpen(false)}>
                <InvoiceForm onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
        </Card>
    );
}
