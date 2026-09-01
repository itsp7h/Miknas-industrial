/** Blade's 44×24 track-and-thumb switch, used for WhatsApp and per account. */
export default function Switch({ on, onClick, label, colour = '#22c55e' }) {
    return (
        <div
            role="switch" aria-checked={on} aria-label={label} tabIndex={0}
            onClick={onClick}
            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onClick(); } }}
            style={{
                width: 44, height: 24, borderRadius: 12, cursor: 'pointer', flexShrink: 0,
                background: on ? colour : '#d1d5db', position: 'relative', transition: 'background .2s',
            }}
        >
            <div style={{
                position: 'absolute', top: 2, left: on ? 22 : 2, width: 20, height: 20,
                borderRadius: '50%', background: '#fff', boxShadow: '0 1px 3px rgba(0,0,0,.2)',
                transition: 'left .2s',
            }} />
        </div>
    );
}
