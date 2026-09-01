import { hasCredit, hostOf } from './supplierStats';

const TH = {
    padding: '10px 12px', textAlign: 'left', fontSize: 10.5, fontWeight: 600,
    letterSpacing: '.05em', textTransform: 'uppercase', color: '#94a3b8', whiteSpace: 'nowrap',
};
const TD = { padding: '10px 12px', verticalAlign: 'top' };
const DASH = <span style={{ color: '#cbd5e1' }}>—</span>;

const COLUMNS = [
    { label: 'Code' },
    { label: 'Company', style: { minWidth: 200 } },
    { label: 'Category' },
    { label: 'Contact & Email', style: { minWidth: 160 } },
    { label: 'Phone / WhatsApp', style: { minWidth: 140 } },
    { label: 'Address', style: { minWidth: 140 } },
    { label: 'Tax / Credit' },
    { label: 'Status' },
    { label: '' },
];

const WhatsAppGlyph = () => (
    <svg width="11" height="11" viewBox="0 0 24 24" fill="#16a34a" style={{ flexShrink: 0 }}>
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
    </svg>
);

/**
 * The Blade suppliers table: dark header, zebra striping, and a light-blue
 * hover — reproduced rather than using the shared `Table` primitive, which has
 * a different (light-header) look and its own search box.
 */
export default function SupplierTable({ suppliers, onEdit, onDelete }) {
    return (
        <div style={{ width: '100%', overflowX: 'auto', borderRadius: 12, border: '1px solid #e2e8f0' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12.5, whiteSpace: 'nowrap' }}>
                <thead>
                    <tr style={{ background: '#1e293b' }}>
                        {COLUMNS.map((col, i) => (
                            <th key={col.label || `blank-${i}`} style={{ ...TH, ...(col.style ?? {}) }}>{col.label}</th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {suppliers.length === 0 && (
                        <tr>
                            <td colSpan={COLUMNS.length} style={{ padding: '32px 12px', textAlign: 'center', color: '#94a3b8' }}>
                                No suppliers found.
                            </td>
                        </tr>
                    )}

                    {suppliers.map((supplier, i) => {
                        const striped = i % 2 !== 0;
                        const base = striped ? '#fafafa' : 'transparent';

                        return (
                            <tr
                                key={supplier.id}
                                style={{ borderBottom: '1px solid #f1f5f9', background: base }}
                                onMouseOver={(e) => { e.currentTarget.style.background = '#f0f9ff'; }}
                                onMouseOut={(e) => { e.currentTarget.style.background = base; }}
                            >
                                <td style={{ ...TD, fontFamily: 'monospace', fontSize: 11, color: '#94a3b8' }}>
                                    {supplier.supplier_code || '—'}
                                </td>

                                <td style={{ ...TD, maxWidth: 220, whiteSpace: 'normal' }}>
                                    <div style={{ fontWeight: 600, color: '#0f172a', fontSize: 13, lineHeight: 1.3 }}>
                                        {supplier.name}
                                    </div>
                                    {supplier.website && (
                                        <a
                                            href={supplier.website} target="_blank" rel="noreferrer" title={supplier.website}
                                            style={{
                                                fontSize: 11, color: '#2563eb', textDecoration: 'none', display: 'block',
                                                marginTop: 2, overflow: 'hidden', textOverflow: 'ellipsis',
                                                whiteSpace: 'nowrap', maxWidth: 200,
                                            }}
                                        >
                                            {hostOf(supplier.website)}
                                        </a>
                                    )}
                                </td>

                                <td style={TD}>
                                    {supplier.category ? (
                                        <span style={{
                                            display: 'inline-block', padding: '2px 9px', borderRadius: 20,
                                            fontSize: 11, fontWeight: 600, background: '#f1f5f9',
                                            color: '#475569', whiteSpace: 'nowrap',
                                        }}>
                                            {supplier.category}
                                        </span>
                                    ) : DASH}
                                </td>

                                <td style={{ ...TD, maxWidth: 200, whiteSpace: 'normal' }}>
                                    {supplier.contact_person && (
                                        <div style={{ fontWeight: 500, color: '#334155', fontSize: 12.5 }}>
                                            {supplier.contact_person}
                                        </div>
                                    )}
                                    {supplier.email && (
                                        <a href={`mailto:${supplier.email}`} title={supplier.email} style={{
                                            display: 'block', color: '#2563eb', textDecoration: 'none', fontSize: 12,
                                            marginTop: 2, overflow: 'hidden', textOverflow: 'ellipsis',
                                            whiteSpace: 'nowrap', maxWidth: 190,
                                        }}>
                                            {supplier.email}
                                        </a>
                                    )}
                                    {supplier.secondary_email && (
                                        <a href={`mailto:${supplier.secondary_email}`} title={supplier.secondary_email} style={{
                                            display: 'block', color: '#94a3b8', textDecoration: 'none', fontSize: 11,
                                            overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', maxWidth: 190,
                                        }}>
                                            {supplier.secondary_email}
                                        </a>
                                    )}
                                    {!supplier.contact_person && !supplier.email && DASH}
                                </td>

                                <td style={{ ...TD, whiteSpace: 'nowrap' }}>
                                    {supplier.phone && (
                                        <a href={`tel:${supplier.phone}`} style={{ display: 'block', color: '#334155', textDecoration: 'none', fontSize: 12.5 }}>
                                            {supplier.phone}
                                        </a>
                                    )}
                                    {supplier.phone2 && (
                                        <a href={`tel:${supplier.phone2}`} style={{ display: 'block', color: '#64748b', textDecoration: 'none', fontSize: 12, marginTop: 2 }}>
                                            {supplier.phone2}
                                        </a>
                                    )}
                                    {supplier.whatsapp && (
                                        <a
                                            href={`https://wa.me/${String(supplier.whatsapp).replace(/^\+/, '')}`}
                                            target="_blank" rel="noreferrer"
                                            style={{
                                                display: 'inline-flex', alignItems: 'center', gap: 3,
                                                color: '#16a34a', textDecoration: 'none', fontSize: 12, marginTop: 2,
                                            }}
                                        >
                                            <WhatsAppGlyph /> {supplier.whatsapp}
                                        </a>
                                    )}
                                    {!supplier.phone && !supplier.phone2 && !supplier.whatsapp && DASH}
                                </td>

                                <td style={{
                                    ...TD, color: '#475569', maxWidth: 160,
                                    whiteSpace: 'normal', fontSize: 12, lineHeight: 1.4,
                                }}>
                                    {supplier.address || '—'}
                                </td>

                                <td style={{ ...TD, whiteSpace: 'nowrap' }}>
                                    {supplier.tax_number && (
                                        <div style={{ fontFamily: 'monospace', fontSize: 11, color: '#64748b' }}>
                                            {supplier.tax_number}
                                        </div>
                                    )}
                                    {hasCredit(supplier) ? (
                                        <div style={{ fontSize: 11, color: '#16a34a', fontWeight: 600, marginTop: 2 }}>
                                            Credit{supplier.credit_days ? ` · ${supplier.credit_days}d` : ''}
                                        </div>
                                    ) : (!supplier.tax_number && DASH)}
                                </td>

                                <td style={TD}>
                                    <span className={supplier.is_active ? 'badge-green' : 'badge-red'}>
                                        {supplier.is_active ? 'Active' : 'Inactive'}
                                    </span>
                                </td>

                                <td style={{ ...TD, whiteSpace: 'nowrap' }}>
                                    <div style={{ display: 'flex', gap: 10 }}>
                                        <button
                                            type="button" onClick={() => onEdit(supplier)}
                                            style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#2563eb', fontSize: 12, fontWeight: 600, padding: 0 }}
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button" onClick={() => onDelete(supplier)}
                                            style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#dc2626', fontSize: 12, fontWeight: 600, padding: 0 }}
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
