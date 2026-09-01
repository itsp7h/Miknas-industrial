import { useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete, apiPostForm } from '../../../api/client';
import { useToast } from '../../ui/Toast';
import { categoryLabel } from './itemStyles';

/** List, import and delete behaviour shared by both viewports. */
export default function useItemList() {
    const { items, upsertItem, removeItem, refetch } = useLiveList({
        endpoint: '/inventory/items',
        channel: 'inventory',
        event: '.item.saved',
        deleteEvent: '.item.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load items.',
    });
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const { showToast } = useToast();

    function openCreate() {
        setEditing(null);
        setModalOpen(true);
    }

    function openEdit(item) {
        setEditing(item);
        setModalOpen(true);
    }

    function handleSaved(item) {
        upsertItem(item);
        setModalOpen(false);
        showToast('Item saved.', 'success');
    }

    async function handleDeleteConfirmed() {
        const item = deleting;
        setDeleting(null);
        try {
            const result = await apiDelete(`/inventory/items/${item.id}`);
            if (result.deactivated) {
                // An item with stock history is deactivated rather than removed,
                // so the row stays and is refreshed instead.
                showToast(result.message, 'info');
                await refetch();
            } else {
                removeItem(item.id);
                showToast('Item deleted.', 'success');
            }
        } catch (err) {
            showToast(err.message || 'Failed to delete item.', 'error');
        }
    }

    async function handleImport(file) {
        const formData = new FormData();
        formData.append('file', file);
        try {
            const result = await apiPostForm('/inventory/items/import', formData);
            showToast(`${result.imported} added, ${result.skipped} skipped.`, 'success');
            setImportOpen(false);
            await refetch();
        } catch (err) {
            showToast(err.message || 'Failed to import items.', 'error');
        }
    }

    const filtered = items.filter((item) => {
        const q = query.trim().toLowerCase();
        if (!q) return true;

        return [item.item_code, item.item_name, categoryLabel(item.category), item.unit_of_measure]
            .some((field) => String(field ?? '').toLowerCase().includes(q));
    });

    return {
        items, filtered,
        query, setQuery,
        modalOpen, setModalOpen,
        importOpen, setImportOpen, handleImport,
        editing, openCreate, openEdit, handleSaved,
        deleting, setDeleting, handleDeleteConfirmed,
    };
}
