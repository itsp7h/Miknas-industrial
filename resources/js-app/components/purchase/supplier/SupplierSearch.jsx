/** The Blade search box: magnifier inside the field, live count beside it. */
export default function SupplierSearch({ query, onChange, shown, total, fullWidth = false }) {
    return (
        <div style={{
            display: 'flex', alignItems: 'center', justifyContent: 'space-between',
            marginBottom: 12, gap: 12,
        }}>
            <div style={{ position: 'relative', flex: fullWidth ? '1 1 auto' : '0 0 auto' }}>
                <svg
                    style={{
                        position: 'absolute', left: 11, top: '50%', transform: 'translateY(-50%)',
                        color: '#94a3b8', pointerEvents: 'none',
                    }}
                    width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                </svg>
                <input
                    type="text"
                    value={query}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder="Search name, contact, email, phone, address…"
                    aria-label="Search suppliers"
                    autoComplete="off"
                    style={{
                        padding: '8px 14px 8px 34px', border: '1px solid #e2e8f0', borderRadius: 8,
                        fontSize: 13.5, width: fullWidth ? '100%' : 340, outline: 'none',
                    }}
                />
            </div>
            <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                {query ? `${shown} of ${total} suppliers` : `${total} suppliers`}
            </div>
        </div>
    );
}
