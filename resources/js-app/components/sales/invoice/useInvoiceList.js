import { useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete } from '../../../api/client';
import { useToast } from '../../ui/Toast';
import { statusLabel } from './statuses';

/** Load, search, create/edit-open and delete — shared by both viewports. */
export default function useInvoiceList() {
    const { items: invoices, upsertItem, removeItem } = useLiveList({
        endpoint: '/sales/invoices',
        channel: 'sales',
        event: '.sales-invoice.saved',
        deleteEvent: '.sales-invoice.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load invoices.',
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

    function openEdit(invoice) {
        setEditing(invoice);
        setModalOpen(true);
    }

    function handleSaved(invoice) {
        upsertItem(invoice);
        setModalOpen(false);
        showToast(editing ? `${invoice.invoice_number} updated.` : `${invoice.invoice_number} created.`, 'success');
    }

    async function handleDelete() {
        const invoice = deleting;
        setDeleting(null);
        try {
            await apiDelete(`/sales/invoices/${invoice.id}`);
            removeItem(invoice.id);
            showToast('Invoice deleted.', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to delete that invoice.', 'error');
        }
    }

    const q = query.trim().toLowerCase();
    const filtered = q
        ? invoices.filter((invoice) => [
            invoice.invoice_number, invoice.customer_name,
            invoice.order_number, statusLabel(invoice.status),
        ].some((field) => String(field ?? '').toLowerCase().includes(q)))
        : invoices;

    return {
        invoices, filtered,
        query, setQuery,
        modalOpen, setModalOpen,
        editing, openCreate, openEdit, handleSaved,
        deleting, setDeleting, handleDelete,
    };
}
