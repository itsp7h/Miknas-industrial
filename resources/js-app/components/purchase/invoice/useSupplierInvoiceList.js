import { useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete, apiGet } from '../../../api/client';
import { useToast } from '../../ui/Toast';
import { STATUS_LABELS } from './invoiceStyles';

/** List, edit-open and delete behaviour shared by both viewports. */
export default function useSupplierInvoiceList() {
    const { items: invoices, upsertItem, removeItem } = useLiveList({
        endpoint: '/purchase/invoices',
        channel: 'purchase',
        event: '.supplier-invoice.saved',
        deleteEvent: '.supplier-invoice.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load supplier invoices.',
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

    async function openEdit(invoice) {
        try {
            const full = await apiGet(`/purchase/invoices/${invoice.id}`);
            setEditing(full.data);
            setModalOpen(true);
        } catch (err) {
            showToast(err.message || 'Could not open that invoice.', 'error');
        }
    }

    function handleSaved(invoice) {
        upsertItem(invoice);
        setModalOpen(false);
        showToast('Supplier invoice saved.', 'success');
    }

    async function handleDeleteConfirmed() {
        const invoice = deleting;
        setDeleting(null);
        try {
            await apiDelete(`/purchase/invoices/${invoice.id}`);
            removeItem(invoice.id);
            showToast('Supplier invoice deleted.', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to delete the invoice.', 'error');
        }
    }

    const filtered = invoices.filter((invoice) => {
        const q = query.trim().toLowerCase();
        if (!q) return true;

        return [invoice.invoice_number, invoice.supplier_name, invoice.po_number,
            STATUS_LABELS[invoice.status] ?? invoice.status]
            .some((field) => String(field ?? '').toLowerCase().includes(q));
    });

    return {
        invoices, filtered,
        query, setQuery,
        modalOpen, setModalOpen,
        editing, openCreate, openEdit, handleSaved,
        deleting, setDeleting, handleDeleteConfirmed,
    };
}
