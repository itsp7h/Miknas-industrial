import { useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete, apiGet, apiPatch } from '../../../api/client';
import { useToast } from '../../ui/Toast';
import { statusLabel } from './statuses';

/** Load, search, edit-open, confirm and delete — shared by both viewports. */
export default function useSalesOrderList() {
    const { items: orders, upsertItem, removeItem } = useLiveList({
        endpoint: '/sales/orders',
        channel: 'sales',
        event: '.sales-order.saved',
        deleteEvent: '.sales-order.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load sales orders.',
    });
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [confirming, setConfirming] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const { showToast } = useToast();

    function openCreate() {
        setEditing(null);
        setModalOpen(true);
    }

    async function openEdit(order) {
        // The list is a summary; the form needs the order's lines.
        try {
            const full = await apiGet(`/sales/orders/${order.id}`);
            setEditing(full.data);
            setModalOpen(true);
        } catch (err) {
            showToast(err.message || 'Could not open that order.', 'error');
        }
    }

    function handleSaved(order) {
        upsertItem(order);
        setModalOpen(false);
        showToast('Sales order saved.', 'success');
    }

    async function handleConfirm() {
        const order = confirming;
        setConfirming(null);
        try {
            const response = await apiPatch(`/sales/orders/${order.id}/confirm`);
            upsertItem(response.data);
            showToast(`${order.order_number} confirmed.`, 'success');
        } catch (err) {
            showToast(err.message || 'Failed to confirm the order.', 'error');
        }
    }

    async function handleDeleteConfirmed() {
        const order = deleting;
        setDeleting(null);
        try {
            await apiDelete(`/sales/orders/${order.id}`);
            removeItem(order.id);
            showToast('Sales order deleted.', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to delete the order.', 'error');
        }
    }

    const q = query.trim().toLowerCase();
    const filtered = q
        ? orders.filter((order) => [order.order_number, order.customer_name, statusLabel(order.status)]
            .some((field) => String(field ?? '').toLowerCase().includes(q)))
        : orders;

    return {
        orders, filtered,
        query, setQuery,
        modalOpen, setModalOpen,
        editing, openCreate, openEdit, handleSaved,
        confirming, setConfirming, handleConfirm,
        deleting, setDeleting, handleDeleteConfirmed,
    };
}
