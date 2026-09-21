import { useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete, apiPost, apiPut } from '../../../api/client';
import { useToast } from '../../ui/Toast';

const BLANK = { name: '', parent_type: 'raw_material', sort_order: '' };

/** List, create, rename and delete behaviour shared by both viewports. */
export default function useItemCategories() {
    const { items, meta, upsertItem, removeItem } = useLiveList({
        endpoint: '/settings/item-categories',
        channel: 'inventory',
        event: '.item-category.saved',
        deleteEvent: '.item-category.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load item categories.',
    });
    const [editing, setEditing] = useState(null);
    const [values, setValues] = useState(BLANK);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);
    const [deleting, setDeleting] = useState(null);
    const { showToast } = useToast();

    const parentTypes = meta.parent_types ?? [{ value: 'raw_material', label: 'Raw Materials' }];

    function openNew() {
        setEditing(null);
        setValues(BLANK);
        setErrors({});
    }

    function openEdit(category) {
        setEditing(category);
        setValues({
            name: category.name,
            parent_type: category.parent_type,
            sort_order: category.sort_order ?? '',
        });
        setErrors({});
    }

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    async function save() {
        setSaving(true);
        setErrors({});
        try {
            const payload = { ...values, sort_order: values.sort_order === '' ? null : values.sort_order };
            const response = editing
                ? await apiPut(`/settings/item-categories/${editing.id}`, payload)
                : await apiPost('/settings/item-categories', payload);
            upsertItem(response.data);
            openNew();
            showToast('Category saved.', 'success');
        } catch (err) {
            setErrors(Object.fromEntries(
                Object.entries(err.errors ?? {}).map(([key, messages]) => [key, messages[0]])
            ));
            if (!err.errors) showToast(err.message || 'Failed to save category.', 'error');
        } finally {
            setSaving(false);
        }
    }

    async function confirmDelete() {
        const category = deleting;
        setDeleting(null);
        try {
            await apiDelete(`/settings/item-categories/${category.id}`);
            removeItem(category.id);
            showToast('Category deleted.', 'success');
        } catch (err) {
            // A section still holding items is refused with a count and a
            // reason, not a raw error.
            showToast(err.message || 'Failed to delete category.', 'error');
        }
    }

    return {
        categories: items, parentTypes,
        editing, values, setField, errors, saving,
        openNew, openEdit, save,
        deleting, setDeleting, confirmDelete,
    };
}
