import { useEffect, useState } from 'react';
import FormField from '../../ui/FormField';
import Button from '../../ui/Button';
import { apiGet, apiPost } from '../../../api/client';

export const TYPE_LABELS = { in: 'Stock In', out: 'Stock Out', adjustment: 'Adjustment' };

const EMPTY = { item_id: '', warehouse_id: '', type: 'in', quantity: '', notes: '' };

export default function StockMovementForm({ onSaved, onCancel }) {
    const [options, setOptions] = useState({ items: [], warehouses: [], types: [] });
    const [values, setValues] = useState(EMPTY);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);
    const [loadError, setLoadError] = useState(null);

    useEffect(() => {
        // Items and warehouses arrive together so the form renders in one trip.
        apiGet('/inventory/movements/form-options')
            .then(setOptions)
            .catch(() => setLoadError('Could not load items and warehouses.'));
    }, []);

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    async function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const response = await apiPost('/inventory/movements', values);
            onSaved(response.data);
        } catch (err) {
            setErrors(Object.fromEntries(
                Object.entries(err.errors ?? {}).map(([key, messages]) => [key, messages[0]])
            ));
        } finally {
            setSaving(false);
        }
    }

    if (loadError) {
        return <p className="text-sm text-red-600">{loadError}</p>;
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="mb-4">
                <label htmlFor="item_id" className="block text-sm font-medium text-gray-700 mb-1">Item</label>
                <select
                    id="item_id"
                    value={values.item_id}
                    onChange={(e) => setField('item_id', e.target.value)}
                    className={`border rounded-md px-3 py-2 text-sm w-full ${errors.item_id ? 'border-red-400' : 'border-gray-300'}`}
                >
                    <option value="">Select an item…</option>
                    {options.items.map((item) => (
                        <option key={item.id} value={item.id}>
                            {item.item_name} ({item.item_code})
                        </option>
                    ))}
                </select>
                {errors.item_id && <p className="text-sm text-red-600 mt-1">{errors.item_id}</p>}
            </div>

            <div className="mb-4">
                <label htmlFor="warehouse_id" className="block text-sm font-medium text-gray-700 mb-1">Warehouse</label>
                <select
                    id="warehouse_id"
                    value={values.warehouse_id}
                    onChange={(e) => setField('warehouse_id', e.target.value)}
                    className={`border rounded-md px-3 py-2 text-sm w-full ${errors.warehouse_id ? 'border-red-400' : 'border-gray-300'}`}
                >
                    <option value="">Select a warehouse…</option>
                    {options.warehouses.map((warehouse) => (
                        <option key={warehouse.id} value={warehouse.id}>{warehouse.name}</option>
                    ))}
                </select>
                {errors.warehouse_id && <p className="text-sm text-red-600 mt-1">{errors.warehouse_id}</p>}
            </div>

            <div className="mb-4">
                <label htmlFor="type" className="block text-sm font-medium text-gray-700 mb-1">Movement Type</label>
                <select
                    id="type"
                    value={values.type}
                    onChange={(e) => setField('type', e.target.value)}
                    className={`border rounded-md px-3 py-2 text-sm w-full ${errors.type ? 'border-red-400' : 'border-gray-300'}`}
                >
                    {(options.types.length ? options.types : ['in', 'out', 'adjustment']).map((type) => (
                        <option key={type} value={type}>{TYPE_LABELS[type] ?? type}</option>
                    ))}
                </select>
                {errors.type && <p className="text-sm text-red-600 mt-1">{errors.type}</p>}
            </div>

            <FormField label="Quantity" name="quantity" type="number" value={values.quantity} onChange={setField} error={errors.quantity} />
            <FormField label="Notes" name="notes" type="textarea" value={values.notes} onChange={setField} error={errors.notes} />

            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                <Button type="submit" loading={saving}>Record Movement</Button>
            </div>
        </form>
    );
}
