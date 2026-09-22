import { useEffect, useState } from 'react';
import { apiGet, apiPut } from '../../../api/client';
import { useToast } from '../../ui/Toast';

/**
 * Each company's receiving warehouse, edited together and saved in one go.
 *
 * The empty string is the unlinked state in the form, and null is what the
 * server is told: a select cannot hold null, and a company with no warehouse
 * is a real state rather than a missing answer.
 */
export default function useCompanyWarehouse() {
    const [companies, setCompanies] = useState([]);
    const [warehouses, setWarehouses] = useState([]);
    const [links, setLinks] = useState({});
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const { showToast } = useToast();

    function seed(response) {
        setCompanies(response.data);
        setWarehouses(response.warehouses ?? []);
        setLinks(Object.fromEntries(
            response.data.map((row) => [row.id, row.warehouse_id == null ? '' : String(row.warehouse_id)])
        ));
    }

    useEffect(() => {
        apiGet('/settings/company-warehouses')
            .then(seed)
            .catch((err) => setError(err?.status === 403
                ? 'You do not have permission to view the company warehouses.'
                : 'Failed to load the company warehouses.'))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const setLink = (id, value) => setLinks((prev) => ({ ...prev, [id]: value }));

    const dirty = companies.some(
        (row) => (row.warehouse_id == null ? '' : String(row.warehouse_id)) !== (links[row.id] ?? '')
    );

    async function save() {
        setSaving(true);
        setError('');
        try {
            const response = await apiPut('/settings/company-warehouses', {
                links: companies.map((row) => ({
                    id: row.id,
                    warehouse_id: links[row.id] === '' || links[row.id] == null ? null : Number(links[row.id]),
                })),
            });
            seed(response);
            showToast(response.message, 'success');
        } catch (err) {
            const message = err?.errors
                ? Object.values(err.errors)[0][0]
                : (err?.message || 'Failed to save.');
            setError(message);
            showToast(message, 'error');
        } finally {
            setSaving(false);
        }
    }

    return { companies, warehouses, links, setLink, loading, saving, dirty, error, save };
}
