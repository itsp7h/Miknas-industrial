import { useEffect, useState } from 'react';
import FormField from '../ui/FormField';
import Button from '../ui/Button';
import { apiGet, apiPost, apiPut } from '../../api/client';

export default function BomForm({ entry, onSaved, onCancel }) {
    const [options, setOptions] = useState({ products: [], raw_materials: [] });
    const [values, setValues] = useState(() => ({
        product_id: entry?.product_id ?? '',
        raw_material_id: entry?.raw_material_id ?? '',
        quantity_required: entry?.quantity_required ?? '',
        unit_of_measure: entry?.unit_of_measure ?? '',
        notes: entry?.notes ?? '',
    }));
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        apiGet('/production/bom/form-options').then(setOptions).catch(() => {});
    }, []);

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    async function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const response = entry
                ? await apiPut(`/production/bom/${entry.id}`, values)
                : await apiPost('/production/bom', values);
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
                <label htmlFor="product_id" className="form-label">
                    Product (Finished Good) <span className="text-red-500">*</span>
                </label>
                <select id="product_id" value={values.product_id} onChange={(e) => setField('product_id', e.target.value)}
                    className={`border rounded-md px-3 py-2 text-sm w-full ${errors.product_id ? 'border-red-400' : 'border-gray-300'}`}>
                    <option value="">-- Select Product --</option>
                    {options.products.map((p) => (
                        <option key={p.id} value={p.id}>{p.item_code} - {p.item_name}</option>
                    ))}
                </select>
                {errors.product_id && <p className="text-sm text-red-600 mt-1">{errors.product_id}</p>}
            </div>

            <div className="mb-4">
                <label htmlFor="raw_material_id" className="form-label">
                    Raw Material <span className="text-red-500">*</span>
                </label>
                <select id="raw_material_id" value={values.raw_material_id} onChange={(e) => setField('raw_material_id', e.target.value)}
                    className={`border rounded-md px-3 py-2 text-sm w-full ${errors.raw_material_id ? 'border-red-400' : 'border-gray-300'}`}>
                    <option value="">-- Select Raw Material --</option>
                    {options.raw_materials.map((m) => (
                        <option key={m.id} value={m.id}>{m.item_code} - {m.item_name}</option>
                    ))}
                </select>
                {errors.raw_material_id && <p className="text-sm text-red-600 mt-1">{errors.raw_material_id}</p>}
            </div>

            <FormField label="Quantity Required" name="quantity_required" type="number" value={values.quantity_required} onChange={setField} error={errors.quantity_required} />
            <FormField label="Unit of Measure" name="unit_of_measure" value={values.unit_of_measure} onChange={setField} error={errors.unit_of_measure} placeholder="e.g. KG, PCS" />
            <FormField label="Notes" name="notes" type="textarea" value={values.notes} onChange={setField} error={errors.notes} />

            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                <Button type="submit" loading={saving}>{entry ? 'Update BOM Entry' : 'Save BOM Entry'}</Button>
            </div>
        </form>
    );
}
