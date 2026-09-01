import { BuildingIcon, CheckCircleIcon, FolderIcon, PinIcon } from './icons';

/** Blade's four stat boxes: blue, green, violet, indigo. */
export default function ProjectStatCards({ meta, columns = 4 }) {
    const cards = [
        { key: 'projects', label: 'Total Projects', value: meta.total_projects, accent: '#3b82f6', tint: '#eff6ff', render: (c) => <FolderIcon size={18} colour={c} /> },
        { key: 'active', label: 'Active', value: meta.active_projects, accent: '#22c55e', tint: '#f0fdf4', render: (c) => <CheckCircleIcon colour={c} /> },
        { key: 'locations', label: 'Locations', value: meta.total_locations, accent: '#8b5cf6', tint: '#f5f3ff', render: (c) => <PinIcon size={18} colour={c} /> },
        { key: 'companies', label: 'Companies', value: meta.total_companies, accent: '#6366f1', tint: '#eef2ff', render: (c) => <BuildingIcon colour={c} /> },
    ];

    return (
        <div style={{ display: 'grid', gridTemplateColumns: `repeat(${columns},1fr)`, gap: 16, marginBottom: 28 }}>
            {cards.map(({ key, label, value, accent, tint, render }) => (
                <div key={key} style={{
                    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                    padding: '1.25rem 1.5rem', borderTop: `3px solid ${accent}`,
                }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                        <div style={{
                            width: 40, height: 40, background: tint, borderRadius: 10,
                            display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0,
                        }}>
                            {render(accent)}
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
