import { useRef, useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete, apiPostForm } from '../../../api/client';
import { useToast } from '../../ui/Toast';

/**
 * The list, import and delete behaviour shared by the desktop and mobile
 * supplier pages — the two differ in layout only, so keeping this here stops
 * their behaviour drifting apart.
 */
export default function useSupplierList() {
    const { items: suppliers, upsertItem, removeItem, refetch } = useLiveList({
        endpoint: '/purchase/suppliers',
        channel: 'purchase',
        event: '.supplier.saved',
        deleteEvent: '.supplier.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load suppliers.',
    });
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const [deleteAllOpen, setDeleteAllOpen] = useState(false);
    const fileInputRef = useRef(null);
    const { showToast } = useToast();

    function openCreate() {
        setEditing(null);
        setModalOpen(true);
    }

    function openEdit(supplier) {
        setEditing(supplier);
        setModalOpen(true);
    }

    function handleSaved(supplier) {
        upsertItem(supplier);
        setModalOpen(false);
        showToast('Supplier saved.', 'success');
    }

    async function handleDeleteConfirmed() {
        const supplier = deleting;
        setDeleting(null);
        try {
            await apiDelete(`/purchase/suppliers/${supplier.id}`);
            removeItem(supplier.id);
            showToast('Supplier deleted.', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to delete supplier.', 'error');
        }
    }

    async function handleImport(e) {
        const file = e.target.files?.[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('file', file);
        try {
            const result = await apiPostForm('/purchase/suppliers/import', formData);
            showToast(
                `${result.imported} added, ${result.updated} updated, ${result.skipped} skipped.`,
                'success'
            );
            await refetch();
        } catch (err) {
            showToast(err.message || 'Failed to import suppliers.', 'error');
        } finally {
            // Clear the input so re-picking the same file fires change again.
            if (fileInputRef.current) fileInputRef.current.value = '';
        }
    }

    /**
     * Empty the directory. The server decides which suppliers may actually go
     * — anything the paperwork points at stays — so its count and its wording
     * are what the toast shows, and the list is reloaded from what survived
     * rather than assumed empty.
     */
    async function confirmDeleteAll() {
        setDeleteAllOpen(false);
        try {
            const response = await apiDelete('/purchase/suppliers');
            await refetch();
            showToast(response.message, response.deleted > 0 ? 'success' : 'warn');
        } catch (err) {
            showToast(err?.message || 'Failed to delete suppliers.', 'error');
        }
    }

    return {
        suppliers,
        query, setQuery,
        modalOpen, setModalOpen,
        editing, openCreate, openEdit, handleSaved,
        deleting, setDeleting, handleDeleteConfirmed,
        deleteAllOpen, setDeleteAllOpen, confirmDeleteAll,
        fileInputRef, handleImport,
    };
}
