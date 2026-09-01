import { useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete } from '../../../api/client';
import { useToast } from '../../ui/Toast';
import { methodLabel } from './paymentStyles';

/** List, edit-open and delete behaviour shared by both viewports. */
export default function useSupplierPaymentList() {
    const { items: payments, upsertItem, removeItem, refetch } = useLiveList({
        endpoint: '/purchase/payments',
        channel: 'purchase',
        event: '.supplier-payment.recorded',
        deleteEvent: '.supplier-payment.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load supplier payments.',
    });
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const { showToast } = useToast();

    function openCreate() {
        setEditing(null);
        setModalOpen(true);
    }

    function openEdit(payment) {
        setEditing(payment);
        setModalOpen(true);
    }

    function handleSaved(payment) {
        upsertItem(payment);
        setModalOpen(false);
        showToast('Payment saved.', 'success');
    }

    async function handleDeleteConfirmed() {
        const payment = deleting;
        setDeleting(null);
        try {
            await apiDelete(`/purchase/payments/${payment.id}`);
            removeItem(payment.id);
            showToast('Payment deleted — the invoice balance was updated.', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to delete the payment.', 'error');
        }
    }

    const filtered = payments.filter((payment) => {
        const q = query.trim().toLowerCase();
        if (!q) return true;

        return [payment.invoice_number, payment.supplier_name, payment.reference_number,
            methodLabel(payment.payment_method)]
            .some((field) => String(field ?? '').toLowerCase().includes(q));
    });

    return {
        payments, filtered, refetch,
        query, setQuery,
        modalOpen, setModalOpen,
        editing, openCreate, openEdit, handleSaved,
        deleting, setDeleting, handleDeleteConfirmed,
    };
}
