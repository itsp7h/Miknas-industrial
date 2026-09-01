import { deriveStats } from './supplierStats';

// The four cards from the Blade page, with their icon tints and figure colours.
const CARDS = [
    {
        key: 'total', label: 'Total Suppliers', tint: '#eff6ff', stroke: '#2563eb', value: '#0f172a',
        path: 'M17 20h5v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2h5M12 12a4 4 0 100-8 4 4 0 000 8z',
    },
    {
        key: 'active', label: 'Active', tint: '#f0fdf4', stroke: '#16a34a', value: '#16a34a',
        path: 'M5 13l4 4L19 7',
    },
    {
        key: 'inactive', label: 'Inactive', tint: '#fef2f2', stroke: '#dc2626', value: '#dc2626',
        path: 'M6 18L18 6M6 6l12 12',
    },
    {
        key: 'categories', label: 'Categories', tint: '#fefce8', stroke: '#ca8a04', value: '#ca8a04',
        path: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    },
];

export default function SupplierStatCards({ suppliers, compact = false }) {
    const stats = deriveStats(suppliers);

    return (
        <div style={{
            display: 'grid',
            // Four across on desktop; two-up on a phone so the figures stay legible.
            gridTemplateColumns: compact ? 'repeat(2, 1fr)' : 'repeat(4, 1fr)',
            gap: compact ? 10 : 14,
            marginBottom: 20,
        }}>
            {CARDS.map((card) => (
                <div key={card.key} style={{
                    background: '#fff', borderRadius: 12, padding: compact ? '14px 14px' : '18px 20px',
                    border: '1px solid #e2e8f0', boxShadow: '0 1px 3px rgba(0,0,0,.04)',
                    display: 'flex', alignItems: 'center', gap: compact ? 10 : 14,
                }}>
                    <div style={{
                        width: compact ? 36 : 44, height: compact ? 36 : 44, borderRadius: 10,
                        background: card.tint, display: 'flex', alignItems: 'center',
                        justifyContent: 'center', flexShrink: 0,
                    }}>
                        <svg width={compact ? 17 : 20} height={compact ? 17 : 20} fill="none" stroke={card.stroke} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={card.path} />
                        </svg>
                    </div>
                    <div style={{ minWidth: 0 }}>
                        <div style={{
                            fontSize: compact ? 20 : 26, fontWeight: 700,
                            color: card.value, lineHeight: 1,
                        }}>
                            {stats[card.key]}
                        </div>
                        <div style={{ fontSize: 12, color: '#64748b', marginTop: 2 }}>{card.label}</div>
                    </div>
                </div>
            ))}
        </div>
    );
}
