import { useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete } from '../../../api/client';
import { useToast } from '../../ui/Toast';

/** Load, search, edit-open and delete — shared by both viewports. */
export default function useCustomerList() {
    const { items: customers, upsertItem, removeItem, refetch } = useLiveList({
        endpoint: '/sales/customers',
        channel: 'sales',
        event: '.customer.saved',
        deleteEvent: '.customer.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load customers.',
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

    function openEdit(customer) {
        setEditing(customer);
        setModalOpen(true);
    }

    function handleSaved(customer) {
        upsertItem(customer);
        setModalOpen(false);
        showToast('Customer saved.', 'success');
    }

    async function handleDeleteConfirmed() {
        const customer = deleting;
        setDeleting(null);
        try {
            const result = await apiDelete(`/sales/customers/${customer.id}`);
            if (result.deactivated) {
                showToast(result.message, 'info');
                await refetch();
            } else {
                removeItem(customer.id);
                showToast('Customer deleted.', 'success');
            }
        } catch (err) {
            showToast(err.message || 'Failed to delete customer.', 'error');
        }
    }

    const q = query.trim().toLowerCase();
    const filtered = q
        ? customers.filter((customer) => [
            customer.name, customer.contact_person, customer.email,
            customer.phone, customer.tax_number,
        ].some((field) => String(field ?? '').toLowerCase().includes(q)))
        : customers;

    return {
        customers, filtered,
        query, setQuery,
        modalOpen, setModalOpen,
        editing, openCreate, openEdit, handleSaved,
        deleting, setDeleting, handleDeleteConfirmed,
    };
}
