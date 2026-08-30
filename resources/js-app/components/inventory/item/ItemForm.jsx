import { useState } from 'react';
import FormField from '../../ui/FormField';
import Button from '../../ui/Button';
import { apiPost, apiPut } from '../../../api/client';

// Mirrors the enum check constraint on items.category — a value outside this
// set is rejected by the database, not just by validation.
export const CATEGORIES = [
    { value: 'raw_material', label: 'Raw Material' },
    { value: 'wip', label: 'Work In Progress' },
    { value: 'finished_good', label: 'Finished Good' },
];

const EMPTY = {
    item_name: '',
    category: 'raw_material',
    unit_of_measure: '',
    minimum_stock_level: '',
    cost_price: '',
    description: '',
    is_active: true,
};

export default function ItemForm({ item, onSaved, onCancel }) {
    const [values, setValues] = useState(() => ({
        ...EMPTY,
        ...(item ?? {}),
        // Numeric columns come back as strings from the API; keep them as
        // strings in the inputs so React stays in controlled mode.
        minimum_stock_level: item?.minimum_stock_level ?? '',
        cost_price: item?.cost_price ?? '',
        description: item?.description ?? '',
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
            const response = item
                ? await apiPut(`/inventory/items/${item.id}`, values)
                : await apiPost('/inventory/items', values);
            onSaved(response.data);
        } catch (err) {
            // Laravel returns 422 with {errors: {field: [msg]}}; flatten to the
            // first message per field for FormField.
            const flattened = Object.fromEntries(
                Object.entries(err.errors ?? {}).map(([key, messages]) => [key, messages[0]])
            );
            setErrors(flattened);
        } finally {
            setSaving(false);
        }
    }

    return (
        <form onSubmit={handleSubmit}>
            <FormField label="Item Name" name="item_name" value={values.item_name} onChange={setField} error={errors.item_name} />

            <div className="mb-4">
                <label htmlFor="category" className="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select
                    id="category"
                    name="category"
                    value={values.category}
                    onChange={(e) => setField('category', e.target.value)}
                    className={`border rounded-md px-3 py-2 text-sm w-full ${errors.category ? 'border-red-400' : 'border-gray-300'}`}
                >
                    {CATEGORIES.map((option) => (
                        <option key={option.value} value={option.value}>{option.label}</option>
                    ))}
                </select>
                {errors.category && <p className="text-sm text-red-600 mt-1">{errors.category}</p>}
            </div>

            <FormField label="Unit of Measure" name="unit_of_measure" value={values.unit_of_measure} onChange={setField} error={errors.unit_of_measure} />
            <FormField label="Minimum Stock Level" name="minimum_stock_level" type="number" value={values.minimum_stock_level} onChange={setField} error={errors.minimum_stock_level} />
            <FormField label="Cost Price" name="cost_price" type="number" value={values.cost_price} onChange={setField} error={errors.cost_price} />
            <FormField label="Description" name="description" type="textarea" value={values.description} onChange={setField} error={errors.description} />

            <div className="mb-4">
                <label className="flex items-center gap-2 text-sm font-medium text-gray-700">
                    <input
                        type="checkbox"
                        checked={!!values.is_active}
                        onChange={(e) => setField('is_active', e.target.checked)}
                    />
                    Active
                </label>
            </div>

            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                <Button type="submit" loading={saving}>{item ? 'Save Changes' : 'Create Item'}</Button>
            </div>
        </form>
    );
}
