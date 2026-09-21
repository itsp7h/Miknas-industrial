import { useEffect, useState } from 'react';
import { apiGet, apiPut } from '../../../api/client';
import { useToast } from '../../ui/Toast';

/** The two-digit year the preview shows, matching what the server would mint. */
export const shortYear = () => String(new Date().getFullYear()).slice(-2);

/**
 * What an LPO number will read given a code, without asking the server.
 *
 * The sequence shown is the one the server sent for the saved code, so it is
 * honest about where that company has got to; only the letters change as you
 * type. Editing Steel Tech's code to STL does not reset its count.
 */
export function previewFor(code, savedNext) {
    const sequence = String(savedNext ?? '').split('-').pop() || '0001';
    const letters = (code ?? '').trim().toUpperCase();

    return letters
        ? `${letters}-LPO-${shortYear()}-${sequence}`
        : `LPO-${shortYear()}-${sequence}`;
}

/** Each company's LPO code, edited together and saved in one go. */
export default function useLpoNumbering() {
    const [companies, setCompanies] = useState([]);
    const [codes, setCodes] = useState({});
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const { showToast } = useToast();

    function seed(rows) {
        setCompanies(rows);
        setCodes(Object.fromEntries(rows.map((row) => [row.id, row.lpo_code ?? ''])));
    }

    useEffect(() => {
        apiGet('/settings/lpo-numbering')
            .then((response) => seed(response.data))
            .catch((err) => setError(err?.status === 403
                ? 'You do not have permission to view the LPO numbering.'
                : 'Failed to load the LPO numbering.'))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const setCode = (id, value) => setCodes((prev) => ({ ...prev, [id]: value }));

    const dirty = companies.some((row) => (row.lpo_code ?? '') !== (codes[row.id] ?? ''));

    async function save() {
        setSaving(true);
        setError('');
        try {
            const response = await apiPut('/settings/lpo-numbering', {
                codes: companies.map((row) => ({ id: row.id, lpo_code: codes[row.id] ?? '' })),
            });
            seed(response.data);
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

    return { companies, codes, setCode, loading, saving, dirty, error, save };
}
