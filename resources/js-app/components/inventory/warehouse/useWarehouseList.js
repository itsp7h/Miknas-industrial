import { useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete } from '../../../api/client';
import { useToast } from '../../ui/Toast';

/** List and delete behaviour shared by both viewports. */
export default function useWarehouseList() {
    const { items: warehouses, upsertItem, removeItem, refetch } = useLiveList({
        endpoint: '/inventory/warehouses',
        channel: 'inventory',
        event: '.warehouse.saved',
        deleteEvent: '.warehouse.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load warehouses.',
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

    function openEdit(warehouse) {
        setEditing(warehouse);
        setModalOpen(true);
    }

    function handleSaved(warehouse) {
        upsertItem(warehouse);
        setModalOpen(false);
        showToast('Warehouse saved.', 'success');
    }

    async function handleDeleteConfirmed() {
        const warehouse = deleting;
        setDeleting(null);
        try {
            const result = await apiDelete(`/inventory/warehouses/${warehouse.id}`);
            if (result.deactivated) {
                // A warehouse holding stock is deactivated rather than removed,
                // so the row stays and is refreshed instead.
                showToast(result.message, 'info');
                await refetch();
            } else {
                removeItem(warehouse.id);
                showToast('Warehouse deleted.', 'success');
            }
        } catch (err) {
            showToast(err.message || 'Failed to delete warehouse.', 'error');
        }
    }

    const filtered = warehouses.filter((warehouse) => {
        const q = query.trim().toLowerCase();
        if (!q) return true;

        return [warehouse.code, warehouse.name, warehouse.location]
            .some((field) => String(field ?? '').toLowerCase().includes(q));
    });

    return {
        warehouses, filtered,
        query, setQuery,
        modalOpen, setModalOpen,
        editing, openCreate, openEdit, handleSaved,
        deleting, setDeleting, handleDeleteConfirmed,
    };
}
