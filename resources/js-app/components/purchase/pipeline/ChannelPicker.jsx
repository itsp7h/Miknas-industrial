const CHANNELS = [
    { key: 'email', label: 'Email' },
    { key: 'whatsapp', label: 'WhatsApp' },
    { key: 'both', label: 'Both' },
];

const ON = { background: '#eff6ff', color: '#2563eb' };
const OFF = { background: '#fff', color: '#94a3b8' };

/**
 * Blade's three-way channel segment. A channel the supplier cannot be reached on
 * — no email, or no phone — is disabled rather than silently failing at send.
 */
export default function ChannelPicker({ supplier, value, onChange }) {
    const allowed = (key) => {
        if (key === 'email') return supplier.can_email;
        if (key === 'whatsapp') return supplier.can_whatsapp;

        return supplier.can_email && supplier.can_whatsapp;
    };

    return (
        <div style={{ display: 'inline-flex', border: '1px solid #e2e8f0', borderRadius: 7, overflow: 'hidden' }}>
            {CHANNELS.map(({ key, label }, index) => {
                const enabled = allowed(key);

                return (
                    <button
                        key={key} type="button" disabled={!enabled}
                        title={enabled ? label : `${supplier.name} has no ${key === 'whatsapp' ? 'phone number' : 'email address'}`}
                        onClick={() => onChange(key)}
                        style={{
                            padding: '5px 9px', fontSize: 10, fontWeight: 700, border: 'none',
                            borderLeft: index ? '1px solid #e2e8f0' : 'none',
                            cursor: enabled ? 'pointer' : 'not-allowed',
                            opacity: enabled ? 1 : 0.4,
                            ...(value === key ? ON : OFF),
                        }}
                    >
                        {label}
                    </button>
                );
            })}
        </div>
    );
}
