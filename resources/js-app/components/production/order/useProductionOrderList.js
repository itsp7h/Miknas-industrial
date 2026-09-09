import { useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete, apiGet, apiPatch } from '../../../api/client';
import { useToast } from '../../ui/Toast';
import { statusLabel } from './orderStyles';

/** List, search, start/complete/delete and edit-open behaviour, shared by both viewports. */
export default function useProductionOrderList() {
    const { items: orders, upsertItem, removeItem } = useLiveList({
        endpoint: '/production/orders',
        channel: 'production',
        event: '.production-order.saved',
        deleteEvent: '.production-order.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load production orders.',
    });
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [starting, setStarting] = useState(null);
    const [completing, setCompleting] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const { showToast } = useToast();

    function openCreate() {
        setEditing(null);
        setModalOpen(true);
    }

    async function openEdit(order) {
        try {
            // The list resource omits notes-adjacent detail on purpose; the form
            // wants the full record.
            const full = await apiGet(`/production/orders/${order.id}`);
            setEditing(full.data);
            setModalOpen(true);
        } catch (err) {
            showToast(err.message || 'Could not open that order.', 'error');
        }
    }

    function handleSaved(order) {
        upsertItem(order);
        setModalOpen(false);
        showToast('Production order saved.', 'success');
    }

    async function transition(order, action, message) {
        try {
            const response = await apiPatch(`/production/orders/${order.id}/${action}`);
            upsertItem(response.data);
            showToast(message, 'success');
        } catch (err) {
            showToast(err.message || `Failed to ${action} the order.`, 'error');
        }
    }

    async function handleStart() {
        const order = starting;
        setStarting(null);
        await transition(order, 'start', `${order.order_number} started.`);
    }

    async function handleComplete() {
        const order = completing;
        setCompleting(null);
        await transition(order, 'complete', `${order.order_number} completed.`);
    }

    async function handleDeleteConfirmed() {
        const order = deleting;
        setDeleting(null);
        try {
            await apiDelete(`/production/orders/${order.id}`);
            removeItem(order.id);
            showToast('Production order deleted.', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to delete that order.', 'error');
        }
    }

    const q = query.trim().toLowerCase();
    const filtered = q
        ? orders.filter((order) => [order.order_number, order.product_name, statusLabel(order.status)]
            .some((field) => String(field ?? '').toLowerCase().includes(q)))
        : orders;

    return {
        orders, filtered,
        query, setQuery,
        modalOpen, setModalOpen,
        editing, openCreate, openEdit, handleSaved,
        starting, setStarting, handleStart,
        completing, setCompleting, handleComplete,
        deleting, setDeleting, handleDeleteConfirmed,
    };
}
