import { BuildingIcon, PeopleIcon } from './icons';

/** Blade's two stat boxes: indigo for companies, cyan for departments. */
export default function StatCards({ meta, columns = 2 }) {
    const cards = [
        { key: 'companies', label: 'Companies', value: meta.total_companies, accent: '#6366f1', tint: '#eef2ff', Icon: BuildingIcon },
        { key: 'departments', label: 'Departments', value: meta.total_departments, accent: '#06b6d4', tint: '#ecfeff', Icon: PeopleIcon },
    ];

    return (
        <div style={{
            display: 'grid', gridTemplateColumns: `repeat(${columns},1fr)`, gap: 16,
            marginBottom: 28, maxWidth: columns === 2 ? 480 : undefined,
        }}>
            {cards.map(({ key, label, value, accent, tint, Icon }) => (
                <div
                    key={key}
                    style={{
                        background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                        padding: '1.25rem 1.5rem', borderTop: `3px solid ${accent}`,
                    }}
                >
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                        <div style={{
                            width: 40, height: 40, background: tint, borderRadius: 10,
                            display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0,
                        }}>
                            <Icon colour={accent} />
                        </div>
                        <div>
                            <div style={{ fontSize: 28, fontWeight: 700, color: '#1e293b', lineHeight: 1 }}>{value}</div>
                            <div style={{ fontSize: 12, color: '#64748b', marginTop: 3 }}>{label}</div>
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
}
