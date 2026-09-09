import { useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete, apiGet } from '../../../api/client';
import { useToast } from '../../ui/Toast';
import { STATUS_LABELS } from './statuses';

/** List, edit-open and delete behaviour shared by the two viewports. */
export default function usePurchaseOrderList() {
    const { items: orders, upsertItem, removeItem } = useLiveList({
        endpoint: '/purchase/orders',
        channel: 'purchase',
        event: '.purchase-order.saved',
        deleteEvent: '.purchase-order.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load purchase orders.',
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

    async function openEdit(order) {
        // The list row is a summary; the edit form wants the full record.
        try {
            const full = await apiGet(`/purchase/orders/${order.id}`);
            setEditing(full.data);
            setModalOpen(true);
        } catch (err) {
            showToast(err.message || 'Could not open that order.', 'error');
        }
    }

    function handleSaved(order) {
        upsertItem(order);
        setModalOpen(false);
        showToast('Purchase order saved.', 'success');
    }

    async function handleDeleteConfirmed() {
        const order = deleting;
        setDeleting(null);
        try {
            await apiDelete(`/purchase/orders/${order.id}`);
            removeItem(order.id);
            showToast('Purchase order deleted.', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to delete the order.', 'error');
        }
    }

    const filtered = orders.filter((order) => {
        const q = query.trim().toLowerCase();
        if (!q) return true;

        return [order.po_number, order.supplier_name, STATUS_LABELS[order.status] ?? order.status]
            .some((field) => String(field ?? '').toLowerCase().includes(q));
    });

    return {
        orders, filtered,
        query, setQuery,
        modalOpen, setModalOpen,
        editing, openCreate, openEdit, handleSaved,
        deleting, setDeleting, handleDeleteConfirmed,
    };
}
