const BASE = {
    padding: '8px 22px', borderRadius: 999, fontSize: 13, fontWeight: 600,
    cursor: 'pointer', transition: 'all .15s',
};

/** Blade's two pill tabs. */
export default function TabPills({ tab, onChange }) {
    const tabs = [
        { key: 'whatsapp', label: '💬 WhatsApp' },
        { key: 'email', label: '✉️ Email' },
    ];

    return (
        <div style={{ display: 'flex', gap: 8, marginBottom: 24 }}>
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
