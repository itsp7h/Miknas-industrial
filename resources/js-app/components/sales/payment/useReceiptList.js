import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import useLiveList from '../../../hooks/useLiveList';
import { useToast } from '../../ui/Toast';
import { methodLabel } from './methods';

/** Load, search and create-open — shared by both viewports. */
export default function useReceiptList() {
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

    // The invoices page's Receive button links here with ?invoice_id=, the way
    // Blade's create link did.
    const [params] = useSearchParams();
    const presetInvoiceId = params.get('invoice_id');

    useEffect(() => {
        if (presetInvoiceId) setModalOpen(true);
    }, [presetInvoiceId]);

    function handleSaved(receipt) {
        upsertItem(receipt);
        setModalOpen(false);
        showToast('Receipt recorded — the invoice and the customer balance are updated.', 'success');
    }

    const q = query.trim().toLowerCase();
    const filtered = q
        ? receipts.filter((receipt) => [
            receipt.customer_name, receipt.invoice_number,
            receipt.reference_number, methodLabel(receipt.payment_method),
        ].some((field) => String(field ?? '').toLowerCase().includes(q)))
        : receipts;

    return {
        receipts, filtered,
        query, setQuery,
        presetInvoiceId,
        modalOpen, setModalOpen, handleSaved,
    };
}
