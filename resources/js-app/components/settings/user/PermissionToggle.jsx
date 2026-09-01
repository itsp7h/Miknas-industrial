/** Blade's 38×20 switch: label on the left, toggle on the right. */
export default function PermissionToggle({ name, label, checked, onChange }) {
    return (
        <label
            htmlFor={`permission-${name}`}
            style={{
                display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                gap: 12, fontSize: 13, color: '#374151', cursor: 'pointer',
            }}
        >
            <span>{label}</span>
            <span style={{ position: 'relative', display: 'inline-block', width: 38, height: 20, flexShrink: 0 }}>
                <input
                    id={`permission-${name}`} type="checkbox" checked={checked}
                    onChange={(e) => onChange(name, e.target.checked)}
                    style={{ opacity: 0, width: 0, height: 0 }}
                />
                <span style={{
                    position: 'absolute', inset: 0, borderRadius: 999, transition: '.15s',
                    background: checked ? '#2563eb' : '#e2e8f0',
                }} />
                <span style={{
                    position: 'absolute', height: 16, width: 16, top: 2, left: 2,
                    background: '#fff', borderRadius: '50%', transition: '.15s',
                    transform: checked ? 'translateX(18px)' : 'none',
                }} />
            </span>
        </label>
    );
}
