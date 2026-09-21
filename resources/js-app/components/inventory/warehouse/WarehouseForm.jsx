import { useState } from 'react';
import FormField from '../../ui/FormField';
import Button from '../../ui/Button';
import MapPicker from '../../map/MapPicker';
import { apiPost, apiPut } from '../../../api/client';

const EMPTY = {
    code: '', name: '', location: '', latitude: null, longitude: null, description: '', is_active: true,
};

/** The record arrives with nulls for a warehouse that never had a pin. */
const coordinate = (value) => (Number.isFinite(Number(value)) && value !== null && value !== '' ? Number(value) : null);

export default function WarehouseForm({ warehouse, onSaved, onCancel }) {
    const [values, setValues] = useState(() => ({
        ...EMPTY,
        ...(warehouse ?? {}),
        location: warehouse?.location ?? '',
        latitude: coordinate(warehouse?.latitude),
        longitude: coordinate(warehouse?.longitude),
        description: warehouse?.description ?? '',
    }));
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    /**
     * The map owns the coordinates outright, but only *proposes* the address:
     * it arrives a moment after the pin as `address`, and a pick that carries
     * none (the two coordinate boxes, or Clear pin) leaves whatever the user
     * typed alone. The label a yard goes by is rarely the one Nominatim knows.
     */
    function handlePick({ latitude, longitude, address }) {
        setValues((prev) => ({
            ...prev,
            latitude,
            longitude,
            location: address === undefined ? prev.location : address,
        }));
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

            <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-1">Location</label>
                <MapPicker
                    latitude={values.latitude}
                    longitude={values.longitude}
                    onPick={handlePick}
                    disabled={saving}
                />
                {(errors.latitude || errors.longitude) && (
                    <p className="text-sm text-red-600 mt-1">{errors.latitude || errors.longitude}</p>
                )}
            </div>

            <FormField
                label="Address"
                name="location"
                value={values.location}
                onChange={setField}
                error={errors.location}
                placeholder="Filled in from the map — edit it to suit"
            />
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
