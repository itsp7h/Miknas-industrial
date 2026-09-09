import { useState } from 'react';
import Modal from '../../ui/Modal';
import ChannelPicker from './ChannelPicker';
import useSupplierSelection from './useSupplierSelection';

const METHODS = [
    {
        key: 'global', emoji: '📦', tint: '#eff6ff', title: 'Full Order',
        caption: 'One set of suppliers handles the entire purchase request',
    },
    {
        key: 'by_item', emoji: '🔀', tint: '#f0fdf4', title: 'By Item',
        caption: 'Assign different suppliers to specific items in this request',
    },
];

function SupplierRow({ supplier, checked, invited, onToggle, channel, onChannel }) {
    return (
        <label
            style={{
                display: 'flex', alignItems: 'center', gap: 10, padding: '10px 12px',
                borderBottom: '1px solid #f1f5f9', cursor: invited ? 'default' : 'pointer',
                opacity: invited ? 0.55 : 1,
            }}
        >
            <input type="checkbox" checked={invited || checked} disabled={invited} onChange={onToggle} />
            <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 13, fontWeight: 600, color: '#0f172a' }}>{supplier.name}</div>
                <div style={{ fontSize: 11, color: '#94a3b8' }}>
                    {[supplier.email, supplier.phone].filter(Boolean).join(' · ') || 'No contact details'}
                </div>
            </div>
            {invited
                ? <span style={{ fontSize: 10, fontWeight: 700, color: '#15803d', background: '#f0fdf4', padding: '3px 8px', borderRadius: 8 }}>Invited</span>
                : checked && <ChannelPicker supplier={supplier} value={channel} onChange={onChannel} />}
        </label>
    );
}

/**
 * The Blade supplier-select modal: pick a method, then choose suppliers — for
 * the whole request, or item by item — with a per-supplier channel. It existed
 * only inside the Blade detail page, so the React timeline used to hand off to
 * that page rather than offer it.
 */
export default function SupplierSelectModal({ open, requestId, items, onClose, onSubmit }) {
    const s = useSupplierSelection(requestId, open);
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    async function submit() {
        setSaving(true);
        setError('');
        try {
            await onSubmit(s.payload());
            onClose();
        } catch (err) {
            const first = Object.values(err?.errors ?? {})[0]?.[0];
            setError(first || err?.message || 'Could not add those suppliers.');
        } finally {
            setSaving(false);
        }
    }

    const title = s.mode
        ? `Select Suppliers — ${s.mode === 'by_item' ? 'By Item' : 'Full Order'}`
        : 'Select Suppliers';

    return (
        <Modal open={open} title={title} onClose={onClose}>
            {s.loading && <p style={{ fontSize: 13, color: '#64748b' }}>Loading suppliers…</p>}

            {/* Step 1: which method. */}
            {!s.loading && !s.mode && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                    {METHODS.map((method) => (
                        <button
                            key={method.key} type="button" onClick={() => s.setMode(method.key)}
                            style={{
                                width: '100%', textAlign: 'left', border: '2px solid #e2e8f0', borderRadius: 12,
                                padding: '18px 20px', cursor: 'pointer', display: 'flex', alignItems: 'center',
                                gap: 16, background: '#fff',
                            }}
                        >
                            <div style={{
                                width: 44, height: 44, background: method.tint, borderRadius: 10, display: 'flex',
                                alignItems: 'center', justifyContent: 'center', fontSize: 22, flexShrink: 0,
                            }}>
                                {method.emoji}
                            </div>
                            <div style={{ flex: 1 }}>
                                <div style={{ fontSize: 14, fontWeight: 700, color: '#0f172a' }}>{method.title}</div>
                                <div style={{ fontSize: 12, color: '#64748b', marginTop: 3 }}>{method.caption}</div>
                            </div>
                            <span style={{ color: '#cbd5e1', fontWeight: 700 }}>›</span>
                        </button>
                    ))}
                </div>
            )}

            {/* Step 2: choose suppliers. */}
            {!s.loading && s.mode && (
                <div>
                    <input
                        type="search" value={s.query} onChange={(e) => s.setQuery(e.target.value)}
                        placeholder="Search suppliers…" aria-label="Search suppliers"
                        className="form-input" style={{ width: '100%', marginBottom: 10 }}
                    />

                    {s.mode === 'global' && (
                        <div style={{ border: '1px solid #e2e8f0', borderRadius: 10, maxHeight: 320, overflowY: 'auto' }}>
                            {s.filtered.length === 0 && (
                                <p style={{ padding: 16, fontSize: 13, color: '#94a3b8', textAlign: 'center' }}>
                                    No suppliers match that search.
                                </p>
                            )}
                            {s.filtered.map((supplier) => (
                                <SupplierRow
                                    key={supplier.id}
                                    supplier={supplier}
                                    invited={s.isInvited(supplier.id)}
                                    checked={s.globalIds.includes(supplier.id)}
                                    onToggle={() => s.toggleGlobal(supplier.id)}
                                    channel={s.channelFor(supplier.id)}
                                    onChannel={(channel) => s.setChannel(supplier.id, channel)}
                                />
                            ))}
                        </div>
                    )}

                    {s.mode === 'by_item' && (
                        <div style={{ maxHeight: 340, overflowY: 'auto' }}>
                            {(items ?? s.options.items).map((item) => (
                                <div key={item.id} style={{ border: '1px solid #e2e8f0', borderRadius: 10, marginBottom: 10 }}>
                                    <div style={{
                                        padding: '8px 12px', background: '#f8fafc', borderBottom: '1px solid #f1f5f9',
                                        fontSize: 12, fontWeight: 700, color: '#334155',
                                    }}>
                                        {item.description}
                                        <span style={{ color: '#94a3b8', fontWeight: 600 }}>
                                            {' '}· {(s.itemSuppliers[item.id] ?? []).length} supplier(s)
                                        </span>
                                    </div>
                                    {s.filtered.map((supplier) => (
                                        <label
                                            key={supplier.id}
                                            style={{
                                                display: 'flex', alignItems: 'center', gap: 8, padding: '7px 12px',
                                                fontSize: 12.5, cursor: s.isInvited(supplier.id) ? 'default' : 'pointer',
                                                opacity: s.isInvited(supplier.id) ? 0.55 : 1,
                                            }}
                                        >
                                            <input
                                                type="checkbox"
                                                disabled={s.isInvited(supplier.id)}
                                                checked={s.isInvited(supplier.id) || (s.itemSuppliers[item.id] ?? []).includes(supplier.id)}
                                                onChange={() => s.toggleForItem(item.id, supplier.id)}
                                            />
                                            {supplier.name}
                                        </label>
                                    ))}
                                </div>
                            ))}

                            {/* Channel is per supplier, not per item, so it is asked
                                once for everyone assigned anywhere. */}
                            {s.assignedSupplierIds.length > 0 && (
                                <div style={{ borderTop: '1px solid #f1f5f9', paddingTop: 10 }}>
                                    <div style={{ fontSize: 10, fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '.06em', marginBottom: 8 }}>
                                        How to reach them
                                    </div>
                                    {s.assignedSupplierIds.map((id) => {
                                        const supplier = s.options.suppliers.find((row) => row.id === id);
                                        if (!supplier) return null;

                                        return (
                                            <div key={id} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 8, padding: '5px 0' }}>
                                                <span style={{ fontSize: 12.5, color: '#334155' }}>{supplier.name}</span>
                                                <ChannelPicker
                                                    supplier={supplier}
                                                    value={s.channelFor(id)}
                                                    onChange={(channel) => s.setChannel(id, channel)}
                                                />
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    )}

                    {error && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 10 }}>{error}</p>}

                    <div className="mt-5" style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}>
                        <button type="button" onClick={() => s.setMode(null)} className="btn-secondary btn-sm">← Back</button>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                            <span style={{ fontSize: 12, color: '#94a3b8' }}>{s.chosenCount} chosen</span>
                            <button type="button" onClick={onClose} className="btn-secondary">Cancel</button>
                            <button type="button" onClick={submit} className="btn-primary" disabled={saving || s.chosenCount === 0}>
                                {saving ? 'Adding…' : 'Add Suppliers'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </Modal>
    );
}
