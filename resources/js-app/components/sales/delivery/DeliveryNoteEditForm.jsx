import { useEffect, useState } from 'react';
import { apiGet, apiPut } from '../../../api/client';

/**
 * Blade's edit page: warehouse, delivery date and notes. Its controller saved
 * only the first two, so a note typed here vanished on submit — this saves all
 * three. The lines themselves are not editable, as in Blade.
 */
export default function DeliveryNoteEditForm({ note, onSaved, onCancel }) {
    const [warehouses, setWarehouses] = useState([]);
    const [values, setValues] = useState({
        warehouse_id: note.warehouse_id ?? '',
        delivery_date: note.delivery_date ?? '',
        notes: note.notes ?? '',
    });
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        apiGet('/sales/delivery-notes/form-options')
            .then((response) => setWarehouses(response.warehouses ?? []))
            .catch(() => {});
    }, []);

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    async function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const response = await apiPut(`/sales/delivery-notes/${note.id}`, values);
            onSaved(response.data);
        } catch (err) {
            setErrors(Object.fromEntries(
                Object.entries(err.errors ?? {}).map(([key, messages]) => [key, messages[0]])
            ));
            if (err.message && !err.errors) setErrors({ warehouse_id: err.message });
        } finally {
            setSaving(false);
        }
    }

    const messages = Object.values(errors);

    return (
        <form onSubmit={handleSubmit}>
            {messages.length > 0 && (
                <div className="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                    <ul className="list-disc list-inside space-y-1">
                        {messages.map((message) => <li key={message}>{message}</li>)}
                    </ul>
                </div>
            )}

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label htmlFor="warehouse_id" className="form-label">
                        Warehouse <span className="text-red-500">*</span>
                    </label>
                    <select
                        id="warehouse_id" className="form-select" required
                        value={values.warehouse_id} onChange={(e) => setField('warehouse_id', e.target.value)}
                    >
                        <option value="">-- Select Warehouse --</option>
                        {warehouses.map((warehouse) => (
                            <option key={warehouse.id} value={warehouse.id}>{warehouse.name}</option>
                        ))}
                    </select>
                </div>

                <div>
                    <label htmlFor="delivery_date" className="form-label">
                        Delivery Date <span className="text-red-500">*</span>
                    </label>
                    <input
                        id="delivery_date" className="form-input" type="date" required
                        value={values.delivery_date} onChange={(e) => setField('delivery_date', e.target.value)}
                    />
                </div>

                <div className="sm:col-span-2">
                    <label htmlFor="notes" className="form-label">Notes</label>
                    <textarea
                        id="notes" className="form-textarea" rows={3}
                        value={values.notes} onChange={(e) => setField('notes', e.target.value)}
                    />
                </div>
            </div>

            <div className="mt-6 flex items-center gap-3">
                <button type="submit" className="btn-primary" disabled={saving}>
                    {saving ? 'Saving…' : 'Update Delivery Note'}
                </button>
                <button type="button" onClick={onCancel} className="btn-secondary">Cancel</button>
            </div>
        </form>
    );
}
