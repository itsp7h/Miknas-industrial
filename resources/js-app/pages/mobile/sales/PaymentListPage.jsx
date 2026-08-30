import { useMemo, useState } from 'react';
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
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const { showToast } = useToast();

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return receipts;
        return receipts.filter((r) =>
            [r.invoice_number, r.customer_name, r.reference_number, METHOD_LABELS[r.payment_method] ?? r.payment_method]
                .some((field) => String(field ?? '').toLowerCase().includes(q))
        );
    }, [receipts, query]);

    function handleSaved(receipt) {
        upsertItem(receipt);
        setModalOpen(false);
        showToast('Payment recorded.', 'success');
    }

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <h1 style={{ fontSize: 18, fontWeight: 700 }}>Payment Receipts</h1>
                <Button onClick={() => setModalOpen(true)}>New</Button>
            </div>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search" value={query} onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search receipts…" aria-label="Search receipts"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {query ? `${filtered.length} of ${receipts.length} receipts` : `${receipts.length} receipts`}
                </div>
            </div>

            {filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {query ? 'No receipts match that search.' : 'No payments recorded yet.'}
                </p>
            )}

            {filtered.map((receipt) => (
                <div key={receipt.id} style={{ border: '1px solid #e2e8f0', borderRadius: 8, padding: 12, marginBottom: 8 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <span style={{ fontWeight: 600 }}>{receipt.invoice_number ?? '—'}</span>
                        <span style={{ fontWeight: 700, color: '#16a34a' }}>{money(receipt.amount)}</span>
                    </div>
                    <div style={{ fontSize: 13, color: '#64748b' }}>{receipt.customer_name ?? '—'}</div>
                    <div style={{ fontSize: 12, color: '#94a3b8' }}>
                        {receipt.receipt_date} · {METHOD_LABELS[receipt.payment_method] ?? receipt.payment_method}
                        {receipt.reference_number ? ` · ${receipt.reference_number}` : ''}
                    </div>
                </div>
            ))}

            <Modal open={modalOpen} title="Record Payment" onClose={() => setModalOpen(false)}>
                <PaymentForm onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
        </div>
    );
}
