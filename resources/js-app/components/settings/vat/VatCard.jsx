import { useEffect, useState } from 'react';
import { apiGet, apiPut } from '../../../api/client';
import { useToast } from '../../ui/Toast';

/**
 * The whole page: one rate behind a card. Shared by both viewports because the
 * only difference is the card's width, which the caller sets.
 */
export default function VatCard({ maxWidth = 480 }) {
    const [rate, setRate] = useState('');
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const { showToast } = useToast();

    useEffect(() => {
        apiGet('/settings/vat')
            .then((response) => setRate(String(response.vat_rate ?? 0)))
            .catch(() => showToast('Failed to load the VAT rate.', 'error'))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    async function save() {
        setSaving(true);
        setError('');
        try {
            const response = await apiPut('/settings/vat', { vat_rate: rate });
            setRate(String(response.vat_rate));
            showToast(response.message, 'success');
        } catch (err) {
            const message = err?.errors?.vat_rate?.[0] || err?.message || 'Failed to save.';
            setError(message);
            showToast(message, 'error');
        } finally {
            setSaving(false);
        }
    }

    return (
        <div style={{ maxWidth }}>
            <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: 14, overflow: 'hidden' }}>
                <div style={{ padding: '1.25rem 1.5rem', borderBottom: '1px solid #e2e8f0', background: '#f8fafc' }}>
                    <div style={{
                        fontSize: 11, fontWeight: 700, color: '#64748b',
                        textTransform: 'uppercase', letterSpacing: '.05em',
                    }}>
                        VAT Configuration
                    </div>
                </div>
                <div style={{ padding: '1.5rem' }}>
                    <label htmlFor="vat-rate" className="form-label">VAT Rate (%)</label>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                        <input
                            id="vat-rate" type="number" min="0" max="100" step="0.01" placeholder="e.g. 10"
                            value={loading ? '' : rate}
                            onChange={(e) => setRate(e.target.value)}
                            style={{
                                width: 160, padding: '9px 12px', border: '1.5px solid #e2e8f0',
                                borderRadius: 8, fontSize: 14, fontWeight: 600, outline: 'none',
                            }}
                        />
                        <span style={{ fontSize: 14, color: '#64748b', fontWeight: 500 }}>%</span>
                    </div>
                    <p style={{ fontSize: 12, color: '#94a3b8', marginTop: 8 }}>
                        Enter 0 to disable VAT. Suppliers will see a VAT checkbox on each item when this is greater than 0.
                    </p>
                    {error && <p style={{ fontSize: 12, color: '#dc2626', marginTop: 8 }}>{error}</p>}
                    <button
                        type="button" onClick={save} disabled={loading || saving}
                        style={{
                            marginTop: 20, padding: '10px 24px', background: '#2563eb', color: '#fff',
                            border: 'none', borderRadius: 8, fontSize: 13, fontWeight: 600,
                            cursor: (loading || saving) ? 'default' : 'pointer',
                            opacity: (loading || saving) ? 0.6 : 1,
                        }}
                    >
                        {saving ? 'Saving…' : 'Save VAT Rate'}
                    </button>
                </div>
            </div>
        </div>
    );
}
