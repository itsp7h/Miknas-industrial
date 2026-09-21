const CARD = {
    display: 'flex', alignItems: 'flex-start', gap: 10, padding: '10px 12px',
    border: '1px solid #e2e8f0', borderRadius: 10, cursor: 'pointer', background: '#fff',
};

const CARD_ON = { ...CARD, borderColor: '#2563eb', background: '#eff6ff' };

/**
 * One profile per person, chosen not assembled.
 *
 * The form used to list every role as a checkbox beside every permission as a
 * toggle — eighteen controls to describe one job. A person has one job, so this
 * is a radio, and each option says what it means rather than leaving the reader
 * to infer it from the name.
 */
export default function ProfilePicker({ profiles, value, onChange }) {
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            {profiles.map((profile) => {
                const on = value === profile.name;

                return (
                    <label key={profile.name} style={on ? CARD_ON : CARD}>
                        <input
                            type="radio"
                            name="profile"
                            value={profile.name}
                            checked={on}
                            onChange={() => onChange(profile.name)}
                            style={{ marginTop: 3 }}
                        />
                        <span style={{ minWidth: 0 }}>
                            <span style={{
                                display: 'block', fontSize: 13.5, fontWeight: 600,
                                color: on ? '#1e40af' : '#0f172a',
                            }}>
                                {profile.name}
                            </span>
                            <span style={{ display: 'block', fontSize: 12, color: '#64748b', marginTop: 2 }}>
                                {profile.description}
                            </span>
                        </span>
                    </label>
                );
            })}

            {/* Someone can sign in and be nobody: the account exists, the access
                does not. Leaving it out would make "no profile" unreachable
                once a profile had been set. */}
            <label style={value === '' ? CARD_ON : CARD}>
                <input
                    type="radio" name="profile" value="" checked={value === ''}
                    onChange={() => onChange('')} style={{ marginTop: 3 }}
                />
                <span>
                    <span style={{ display: 'block', fontSize: 13.5, fontWeight: 600, color: '#0f172a' }}>
                        No profile
                    </span>
                    <span style={{ display: 'block', fontSize: 12, color: '#64748b', marginTop: 2 }}>
                        Can sign in, but nothing is granted.
                    </span>
                </span>
            </label>
        </div>
    );
}
