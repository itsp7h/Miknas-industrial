import { useState } from 'react';

const EYE = 'M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z';
const EYE_OFF = 'M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21';

/**
 * A password field with Blade's show/hide eye. `alreadySet` drives the
 * placeholder: a stored secret is never sent to the browser, so the field starts
 * blank and blank means "leave it as it is".
 */
export default function SecretField({ id, label, value, onChange, alreadySet, placeholder, optional = false }) {
    const [visible, setVisible] = useState(false);

    return (
        <div style={{ marginBottom: 18 }}>
            <label htmlFor={id} className="form-label">
                {label}{optional && <span style={{ color: '#9ca3af', fontWeight: 400 }}> (optional)</span>}
            </label>
            <div style={{ position: 'relative' }}>
                <input
                    id={id} type={visible ? 'text' : 'password'} className="form-input"
                    style={{ paddingRight: 40 }}
                    autoComplete="off"
                    placeholder={alreadySet ? 'Stored — leave blank to keep it' : placeholder}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                />
                <button
                    type="button" onClick={() => setVisible((prev) => !prev)}
                    aria-label={visible ? `Hide ${label}` : `Show ${label}`}
                    style={{
                        position: 'absolute', top: 0, bottom: 0, right: 0, padding: '0 10px',
                        background: 'none', border: 'none', cursor: 'pointer', color: '#9ca3af',
                    }}
                >
                    <svg style={{ width: 16, height: 16 }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={visible ? EYE_OFF : EYE} />
                    </svg>
                </button>
            </div>
        </div>
    );
}
