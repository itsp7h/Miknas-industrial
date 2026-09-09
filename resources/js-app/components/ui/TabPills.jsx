const BASE = {
    padding: '8px 22px', borderRadius: 999, fontSize: 13, fontWeight: 600,
    cursor: 'pointer', transition: 'all .15s',
};

/**
 * Blade's two pill tabs, as shared vocabulary: pass the tabs in rather than
 * hard-coding a module's own set, so every page that needs a pill switcher
 * renders the identical thing.
 *
 * `style` merges into the row wrapper, for callers that sit the pills inside
 * their own flex header and so need the default bottom margin dropped.
 */
export default function TabPills({ tabs, tab, onChange, style }) {
    return (
        <div style={{ display: 'flex', gap: 8, marginBottom: 24, ...style }}>
            {tabs.map(({ key, label }) => (
                <button
                    key={key} type="button" onClick={() => onChange(key)}
                    aria-pressed={tab === key}
                    style={tab === key
                        ? { ...BASE, background: '#1e293b', color: '#fff', border: '2px solid transparent' }
                        : { ...BASE, background: '#fff', color: '#374151', border: '2px solid #e5e7eb' }}
                >
                    {label}
                </button>
            ))}
        </div>
    );
}
