import { useEffect, useState } from 'react';
import FormField from '../ui/FormField';
import Button from '../ui/Button';
import { apiGet, apiPost, apiPut } from '../../api/client';

export default function ProductionOrderForm({ order, onSaved, onCancel }) {
    const [options, setOptions] = useState({ products: [] });
    const [values, setValues] = useState(() => ({
        product_id: order?.product_id ?? '',
        quantity_to_produce: order?.quantity_to_produce ?? '',
        production_date: order?.production_date ?? new Date().toISOString().slice(0, 10),
        notes: order?.notes ?? '',
    }));
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        apiGet('/production/orders/form-options').then(setOptions).catch(() => {});
    }, []);

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    async function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const response = order
                ? await apiPut(`/production/orders/${order.id}`, values)
                : await apiPost('/production/orders', values);
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
            <div className="mb-4">
                <label htmlFor="product_id" className="block text-sm font-medium text-gray-700 mb-1">Product</label>
                <select
                    id="product_id" value={values.product_id}
                    onChange={(e) => setField('product_id', e.target.value)}
                    className={`border rounded-md px-3 py-2 text-sm w-full ${errors.product_id ? 'border-red-400' : 'border-gray-300'}`}
                >
                    <option value="">Select a product…</option>
                    {options.products.map((p) => (
                        <option key={p.id} value={p.id}>{p.item_name} ({p.unit_of_measure})</option>
                    ))}
                </select>
                {errors.product_id && <p className="text-sm text-red-600 mt-1">{errors.product_id}</p>}
            </div>

            <FormField label="Quantity To Produce" name="quantity_to_produce" type="number" value={values.quantity_to_produce} onChange={setField} error={errors.quantity_to_produce} />
            <FormField label="Production Date" name="production_date" type="date" value={values.production_date} onChange={setField} error={errors.production_date} />
            <FormField label="Notes" name="notes" type="textarea" value={values.notes} onChange={setField} error={errors.notes} />

            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                <Button type="submit" loading={saving}>{order ? 'Save Changes' : 'Create Order'}</Button>
            </div>
        </form>
    );
}
