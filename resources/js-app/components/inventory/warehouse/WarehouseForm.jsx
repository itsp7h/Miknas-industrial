import { useState } from 'react';
import FormField from '../../ui/FormField';
import Button from '../../ui/Button';
import { apiPost, apiPut } from '../../../api/client';

const EMPTY = { code: '', name: '', location: '', description: '', is_active: true };

export default function WarehouseForm({ warehouse, onSaved, onCancel }) {
    const [values, setValues] = useState(() => ({
        ...EMPTY,
        ...(warehouse ?? {}),
        location: warehouse?.location ?? '',
        description: warehouse?.description ?? '',
    }));
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    async function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const response = warehouse
                ? await apiPut(`/inventory/warehouses/${warehouse.id}`, values)
                : await apiPost('/inventory/warehouses', values);
            onSaved(response.data);
        } catch (err) {
            setErrors(Object.fromEntries(
                Object.entries(err.errors ?? {}).map(([key, messages]) => [key, messages[0]])
            ));
        } finally {
            setSaving(false);
        }
    }

    return (
        <form onSubmit={handleSubmit}>
            <FormField label="Code" name="code" value={values.code} onChange={setField} error={errors.code} />
            <FormField label="Name" name="name" value={values.name} onChange={setField} error={errors.name} />
            <FormField label="Location" name="location" value={values.location} onChange={setField} error={errors.location} />
            <FormField label="Description" name="description" type="textarea" value={values.description} onChange={setField} error={errors.description} />

            <div className="mb-4">
                <label className="flex items-center gap-2 text-sm font-medium text-gray-700">
                    <input type="checkbox" checked={!!values.is_active} onChange={(e) => setField('is_active', e.target.checked)} />
                    Active
                </label>
            </div>

            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                <Button type="submit" loading={saving}>{warehouse ? 'Save Changes' : 'Create Warehouse'}</Button>
            </div>
        </form>
    );
}
