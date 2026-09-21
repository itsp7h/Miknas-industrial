import { useEffect, useState } from 'react';
import { apiGet, apiPut } from '../../../api/client';
import { useToast } from '../../ui/Toast';

/**
 * Both money settings behind one request.
 *
 * Each card saves only its own field, so the endpoint takes either and leaves
 * the other alone — typing a VAT rate and not saving it must not be undone by
 * saving the currency.
 */
export default function useFinanceSettings() {
    const [rate, setRate] = useState('');
    const [currency, setCurrency] = useState('BHD');
    const [currencies, setCurrencies] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(null);
    const [errors, setErrors] = useState({});
    const { showToast } = useToast();

    useEffect(() => {
        apiGet('/settings/finance')
            .then((response) => {
                setRate(String(response.vat_rate ?? 0));
                setCurrency(response.currency_code ?? 'BHD');
                setCurrencies(response.currencies ?? []);
            })
            .catch(() => showToast('Failed to load the finance settings.', 'error'))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    async function save(field) {
        const payload = field === 'vat_rate' ? { vat_rate: rate } : { currency_code: currency };
        setSaving(field);
        setErrors((prev) => ({ ...prev, [field]: '' }));
        try {
            const response = await apiPut('/settings/finance', payload);
            if (field === 'vat_rate') setRate(String(response.vat_rate));
            else setCurrency(response.currency_code);
            showToast(response.message, 'success');
        } catch (err) {
            const message = err?.errors?.[field]?.[0] || err?.message || 'Failed to save.';
            setErrors((prev) => ({ ...prev, [field]: message }));
            showToast(message, 'error');
        } finally {
            setSaving(null);
        }
    }

    const symbol = currencies.find((c) => c.code === currency)?.symbol ?? '';

    return {
        rate, setRate, currency, setCurrency, currencies, symbol,
        loading, saving, errors, save,
    };
}
