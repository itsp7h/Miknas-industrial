import { useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete, apiPost, apiPut } from '../../../api/client';
import { useToast } from '../../ui/Toast';

// One empty number row to type into; more are added as needed.
const BLANK = { name: '', company_ids: [], phones: [''] };

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
        setValues({
            name: requester.name,
            company_ids: [...requester.company_ids],
            phones: requester.phones?.length ? [...requester.phones] : [''],
        });
        setErrors({});
    }

    function setName(name) {
        setValues((prev) => ({ ...prev, name }));
    }

    function setPhone(index, phone) {
        setValues((prev) => ({ ...prev, phones: prev.phones.map((p, i) => (i === index ? phone : p)) }));
    }

    function addPhone() {
        setValues((prev) => ({ ...prev, phones: [...prev.phones, ''] }));
    }

    function removePhone(index) {
        setValues((prev) => {
            const phones = prev.phones.filter((_, i) => i !== index);

            return { ...prev, phones: phones.length ? phones : [''] };
        });
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
        // Blank rows are not sent, so the server's `phones.N` counts only the
        // filled ones; `rowOf` maps each back to the row the user sees.
        const rowOf = [];
        const phones = [];
        values.phones.forEach((phone, row) => {
            if (phone.trim() === '') return;
            rowOf.push(row);
            phones.push(phone.trim());
        });
        const payload = { name: values.name, company_ids: values.company_ids, phones };
        try {
            const response = editing
                ? await apiPut(`/settings/requesters/${editing.id}`, payload)
                : await apiPost('/settings/requesters', payload);
            upsertItem(response.data);
            openNew();
            showToast(response.message || 'Saved.', 'success');
        } catch (err) {
            // `company_ids.0` and friends all belong under the checkboxes; a
            // `phones.N` belongs under the row it came from.
            const next = { phoneRows: {} };
            Object.entries(err.errors ?? {}).forEach(([key, messages]) => {
                const row = key.match(/^phones\.(\d+)$/);
                if (row) {
                    next.phoneRows[rowOf[Number(row[1])] ?? Number(row[1])] = messages[0];

                    return;
                }
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
        editing, values, setName, toggleCompany, setPhone, addPhone, removePhone, errors, saving,
        openNew, openEdit, save,
        deleting, setDeleting, confirmDelete,
    };
}
