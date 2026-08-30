import { useMemo, useState } from 'react';
import Card from '../../../components/ui/Card';
import Table from '../../../components/ui/Table';
import Modal from '../../../components/ui/Modal';
import Button from '../../../components/ui/Button';
import PaymentForm, { METHOD_LABELS } from '../../../components/sales/payment/PaymentForm';
import { money } from '../../../components/sales/order/statuses';
import useLiveList from '../../../hooks/useLiveList';
import { useToast } from '../../../components/ui/Toast';

export default function PaymentListPage() {
    const { items: receipts, upsertItem } = useLiveList({
        endpoint: '/sales/payments',
        channel: 'sales',
        event: '.payment-receipt.recorded',
        mergeKey: 'id',
        errorMessage: 'Failed to load payment receipts.',
    });
    const [modalOpen, setModalOpen] = useState(false);
    const { showToast } = useToast();

    function handleSaved(receipt) {
        upsertItem(receipt);
        setModalOpen(false);
        showToast('Payment recorded.', 'success');
    }

    const columns = useMemo(() => [
        { key: 'receipt_date', label: 'Date' },
        { key: 'invoice_number', label: 'Invoice', render: (row) => row.invoice_number ?? '—' },
        { key: 'customer_name', label: 'Customer', render: (row) => row.customer_name ?? '—' },
        { key: 'amount', label: 'Amount', render: (row) => <strong>{money(row.amount)}</strong> },
        { key: 'payment_method', label: 'Method', render: (row) => METHOD_LABELS[row.payment_method] ?? row.payment_method },
        { key: 'reference_number', label: 'Reference', render: (row) => row.reference_number || '—' },
    ], []);

    return (
        <Card title="Payment Receipts">
            <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 12 }}>
                <Button onClick={() => setModalOpen(true)}>Record Payment</Button>
            </div>

            <Table columns={columns} rows={receipts} rowKey={(row) => row.id} searchPlaceholder="Search receipts…" />

            <Modal open={modalOpen} title="Record Payment" onClose={() => setModalOpen(false)}>
                <PaymentForm onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
        </Card>
    );
}
