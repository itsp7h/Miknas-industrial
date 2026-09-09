import { useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete, apiPatch } from '../../../api/client';
import { useToast } from '../../ui/Toast';
import { STATUS_LABELS } from './grnStyles';

/** List, confirm and delete behaviour shared by both viewports. */
export default function useGrnList() {
    const { items: grns, upsertItem, removeItem } = useLiveList({
        endpoint: '/purchase/grns',
        channel: 'purchase',
        event: '.grn.saved',
        deleteEvent: '.grn.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load goods receipt notes.',
    });
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [deleting, setDeleting] = useState(null);
    const [confirming, setConfirming] = useState(null);
    const { showToast } = useToast();

    function handleSaved(grn) {
        upsertItem(grn);
        setModalOpen(false);
        showToast('GRN saved.', 'success');
    }

    async function handleConfirm() {
        const grn = confirming;
        setConfirming(null);
        try {
            const response = await apiPatch(`/purchase/grns/${grn.id}/confirm`);
            upsertItem(response.data);
            showToast(`${grn.grn_number} confirmed — stock updated.`, 'success');
        } catch (err) {
            showToast(err.message || 'Failed to confirm the GRN.', 'error');
        }
    }

    async function handleDeleteConfirmed() {
        const grn = deleting;
        setDeleting(null);
        try {
            await apiDelete(`/purchase/grns/${grn.id}`);
            removeItem(grn.id);
            showToast('GRN deleted.', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to delete the GRN.', 'error');
        }
    }

    const filtered = grns.filter((grn) => {
        const q = query.trim().toLowerCase();
        if (!q) return true;

        return [grn.grn_number, grn.po_number, grn.supplier_name, grn.warehouse_name,
            STATUS_LABELS[grn.status] ?? grn.status]
            .some((field) => String(field ?? '').toLowerCase().includes(q));
    });

    return {
        grns, filtered,
        query, setQuery,
        modalOpen, setModalOpen, handleSaved,
        deleting, setDeleting, handleDeleteConfirmed,
        confirming, setConfirming, handleConfirm,
    };
}
