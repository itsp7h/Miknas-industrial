import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete } from '../../../api/client';
import { useToast } from '../../ui/Toast';
import { statusLabel } from './statuses';

/** Load, search, create/edit-open, dispatch and delete — shared by both viewports. */
export default function useDeliveryNoteList() {
    const { items: notes, upsertItem, removeItem } = useLiveList({
        endpoint: '/sales/delivery-notes',
        channel: 'sales',
        event: '.delivery-note.saved',
        deleteEvent: '.delivery-note.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load delivery notes.',
    });
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [dispatching, setDispatching] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const { showToast } = useToast();

    // The sales order detail page links here with ?sales_order_id=, the way
    // Blade's "Create Delivery Note" button pointed at the create page.
    const [params] = useSearchParams();
    const presetOrderId = params.get('sales_order_id');

    useEffect(() => {
        if (presetOrderId) {
            setEditing(null);
            setModalOpen(true);
        }
    }, [presetOrderId]);

    function openCreate() {
        setEditing(null);
        setModalOpen(true);
    }

    function openEdit(note) {
        setEditing(note);
        setModalOpen(true);
    }

    function handleSaved(note) {
        upsertItem(note);
        setModalOpen(false);
        showToast(editing ? 'Delivery note updated.' : 'Delivery note created.', 'success');
    }

    async function handleDelete() {
        const note = deleting;
        setDeleting(null);
        try {
            await apiDelete(`/sales/delivery-notes/${note.id}`);
            removeItem(note.id);
            showToast('Delivery note deleted.', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to delete that note.', 'error');
        }
    }

    const q = query.trim().toLowerCase();
    const filtered = q
        ? notes.filter((note) => [
            note.delivery_number, note.order_number, note.customer_name,
            note.warehouse_name, statusLabel(note.status),
        ].some((field) => String(field ?? '').toLowerCase().includes(q)))
        : notes;

    return {
        notes, filtered,
        query, setQuery,
        presetOrderId,
        modalOpen, setModalOpen,
        editing, openCreate, openEdit, handleSaved,
        dispatching, setDispatching, upsertItem,
        deleting, setDeleting, handleDelete,
    };
}
