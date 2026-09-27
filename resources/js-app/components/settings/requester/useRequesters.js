import { useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete, apiPost, apiPut } from '../../../api/client';
import { useToast } from '../../ui/Toast';

const BLANK = { name: '', company_ids: [] };

/** List, add, edit and delete behaviour shared by both viewports. */
export default function useRequesters() {
    const { items, meta, upsertItem, removeItem } = useLiveList({
        endpoint: '/settings/requesters',
        channel: 'purchase',
        event: '.requester.saved',
        deleteEvent: '.requester.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load the Requested By list.',
    });
    const [editing, setEditing] = useState(null);
    const [values, setValues] = useState(BLANK);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);
    const [deleting, setDeleting] = useState(null);
    const { showToast } = useToast();

    // A live save lands wherever it sorts, not at the bottom.
    const requesters = [...items].sort((a, b) => a.name.localeCompare(b.name));
    const companies = meta.companies ?? [];

    function openNew() {
        setEditing(null);
        setValues(BLANK);
        setErrors({});
    }

    function openEdit(requester) {
        setEditing(requester);
        setValues({ name: requester.name, company_ids: [...requester.company_ids] });
        setErrors({});
    }

    function setName(name) {
        setValues((prev) => ({ ...prev, name }));
    }

    function toggleCompany(id) {
        setValues((prev) => ({
            ...prev,
            company_ids: prev.company_ids.includes(id)
                ? prev.company_ids.filter((c) => c !== id)
                : [...prev.company_ids, id],
        }));
    }

    async function save() {
        setSaving(true);
        setErrors({});
        try {
            const response = editing
                ? await apiPut(`/settings/requesters/${editing.id}`, values)
                : await apiPost('/settings/requesters', values);
            upsertItem(response.data);
            openNew();
            showToast(response.message || 'Saved.', 'success');
        } catch (err) {
            // `company_ids.0` and friends all belong under the checkboxes.
            const next = {};
            Object.entries(err.errors ?? {}).forEach(([key, messages]) => {
                const field = key.startsWith('company_ids') ? 'company_ids' : key;
                next[field] ??= messages[0];
            });
            setErrors(next);
            if (!err.errors) showToast(err.message || 'Failed to save.', 'error');
        } finally {
            setSaving(false);
        }
    }

    async function confirmDelete() {
        const requester = deleting;
        setDeleting(null);
        try {
            const response = await apiDelete(`/settings/requesters/${requester.id}`);
            removeItem(requester.id);
            if (editing?.id === requester.id) openNew();
            showToast(response?.message || 'Removed.', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to remove.', 'error');
        }
    }

    return {
        requesters, companies,
        editing, values, setName, toggleCompany, errors, saving,
        openNew, openEdit, save,
        deleting, setDeleting, confirmDelete,
    };
}
