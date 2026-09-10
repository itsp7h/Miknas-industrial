/**
 * The portal's non-form screens: still loading, link not usable, link expired,
 * quote already in.
 *
 * These are one tree with a `compact` flag rather than a desktop/mobile pair —
 * the same call as RequestModal (CLAUDE.md #10). There is no second layout to
 * design here, only a card that stops being a card on a phone, and two copies
 * of that would drift for nothing. The quote form itself, which really is two
 * different layouts, is a proper pair.
 */

function Shell({ compact, background, children, footer = true }) {
    return (
        <div
            style={{
                minHeight: '100vh',
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: compact ? 'flex-start' : 'center',
                background: compact ? '#fff' : background,
                padding: compact ? 0 : '28px 16px',
            }}
        >
            <div
                style={{
                    background: '#fff',
                    width: '100%',
                    maxWidth: compact ? '100%' : 500,
                    flex: compact ? 1 : 'none',
                    display: 'flex',
                    flexDirection: 'column',
                    borderRadius: compact ? 0 : 24,
                    boxShadow: compact ? 'none' : '0 20px 60px rgba(0,0,0,.10)',
                    overflow: 'hidden',
                }}
            >
                {children}
                {footer && (
                    <div style={{
                        padding: compact ? '14px 20px' : '14px 40px',
                        background: '#f8fafc',
                        borderTop: '1px solid #f1f5f9',
                        textAlign: 'center',
                    }}>
                        <span style={{ fontSize: 11, color: '#cbd5e1', fontWeight: 500, letterSpacing: '.03em' }}>
                            SteelERP · Procurement Portal
                        </span>
                    </div>
                )}
            </div>
        </div>
    );
}

function Body({ compact, children }) {
    return (
        <div style={{
            padding: compact ? '36px 20px 28px' : '44px 40px 36px',
            textAlign: 'center',
            flex: compact ? 1 : 'none',
        }}>
            {children}
        </div>
    );
}

export function LoadingScreen({ compact = false }) {
    return (
        <Shell compact={compact} background="#f1f5f9" footer={false}>
            <Body compact={compact}>
                <div
                    role="status"
                    aria-live="polite"
                    style={{ fontSize: 14, color: '#64748b', padding: '40px 0' }}
                >
                    Loading your quote request…
                </div>
            </Body>
        </Shell>
    );
}

/**
 * The token resolved at the door — the host page 404s an unknown one — so
 * reaching here means the read itself failed. Say that, and give the supplier
 * something to do about it.
 */
export function ErrorScreen({ compact = false, message }) {
    return (
        <Shell compact={compact} background="#f8fafc">
            <Body compact={compact}>
                <div style={{ fontSize: 48, marginBottom: 18 }}>⚠️</div>
                <div style={{ fontSize: 22, fontWeight: 700, color: '#0f172a', marginBottom: 10 }}>
                    Something went wrong
                </div>
                <p style={{ fontSize: 14, color: '#475569', lineHeight: 1.65, margin: '0 auto', maxWidth: 360 }}>
                    {message || 'This quote request could not be loaded.'}
                </p>
                <button
                    type="button"
                    onClick={() => window.location.reload()}
                    style={{
                        marginTop: 24, padding: '10px 20px', borderRadius: 8, border: 'none',
                        background: '#2563eb', color: '#fff', fontSize: 14, fontWeight: 600,
                        cursor: 'pointer',
                    }}
                >
                    Try again
                </button>
            </Body>
        </Shell>
    );
}

export function ExpiredScreen({ compact = false, invitation }) {
    return (
        <Shell compact={compact} background="#fef2f2">
            <Body compact={compact}>
                <div style={{ fontSize: 56, marginBottom: 18 }}>⏰</div>
                <div style={{ fontSize: 24, fontWeight: 700, color: '#dc2626', marginBottom: 10 }}>
                    Link Expired
                </div>
                <p style={{ fontSize: 14, color: '#475569', lineHeight: 1.65, margin: '0 auto', maxWidth: 360 }}>
                    This quote invitation link has expired. Please contact the
                    purchasing team if you still wish to submit a quote.
                </p>
                {invitation?.expires_at_text && (
                    <div style={{
                        marginTop: 28, padding: 14, background: '#fef2f2',
                        borderRadius: 10, fontSize: 12, color: '#64748b',
                    }}>
                        Expired on {invitation.expires_at_text}
                    </div>
                )}
            </Body>
        </Shell>
    );
}

function DetailRow({ label, value, divided }) {
    return (
        <div style={{
            display: 'flex', alignItems: 'center', justifyContent: 'space-between',
            gap: 12, fontSize: 13, padding: '8px 0',
            borderTop: divided ? '1px solid #f1f5f9' : 'none',
        }}>
            <span style={{ color: '#64748b' }}>{label}</span>
            <span style={{ fontWeight: 700, color: '#0f172a' }}>{value}</span>
        </div>
    );
}

export function SubmittedScreen({ compact = false, invitation }) {
    return (
        <Shell compact={compact} background="linear-gradient(135deg,#f0fdf4 0%,#ecfdf5 50%,#d1fae5 100%)">
            {/* Keyframes cannot be expressed inline, and Tailwind's JIT never
                sees a class that only exists on this page (CLAUDE.md #1). */}
            <style>{`
                @keyframes rfqCheckDraw { from { stroke-dashoffset: 60 } to { stroke-dashoffset: 0 } }
                @keyframes rfqRingPulse { 0%,100% { transform: scale(1); opacity: .3 } 50% { transform: scale(1.2); opacity: .1 } }
            `}</style>

            <div style={{ height: 6, background: 'linear-gradient(90deg,#16a34a,#22c55e,#4ade80)' }} />

            <Body compact={compact}>
                <div style={{ position: 'relative', display: 'inline-block', marginBottom: compact ? 20 : 28 }}>
                    <div style={{
                        position: 'absolute', inset: -12, borderRadius: '50%', background: '#dcfce7',
                        animation: 'rfqRingPulse 2.5s ease-in-out infinite',
                    }} />
                    <div style={{
                        width: 80, height: 80, borderRadius: '50%',
                        background: 'linear-gradient(135deg,#16a34a,#22c55e)',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        position: 'relative', boxShadow: '0 8px 24px rgba(22,163,74,.3)',
                    }}>
                        <svg width="38" height="38" viewBox="0 0 38 38" fill="none" aria-hidden="true">
                            <path
                                d="M10 19.5L16 25.5L28 13"
                                stroke="#fff" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"
                                style={{
                                    strokeDasharray: 60, strokeDashoffset: 60,
                                    animation: 'rfqCheckDraw .6s .35s ease forwards',
                                }}
                            />
                        </svg>
                    </div>
                </div>

                <div style={{
                    fontSize: 11, fontWeight: 700, letterSpacing: '.1em',
                    textTransform: 'uppercase', color: '#16a34a', marginBottom: 8,
                }}>
                    Quote Received
                </div>
                <h1 style={{ fontSize: 26, fontWeight: 800, color: '#0f172a', margin: '0 0 14px', lineHeight: 1.25 }}>
                    Thank you,<br />{invitation?.supplier_name}!
                </h1>
                <p style={{
                    fontSize: 15, color: '#475569', lineHeight: 1.75,
                    maxWidth: 360, margin: '0 auto 24px',
                }}>
                    Your quote for{' '}
                    <strong style={{ color: '#0f172a' }}>{invitation?.request?.request_number}</strong>{' '}
                    has been successfully received. Our team will review all
                    submitted quotes and get back to you shortly.
                </p>

                <div style={{
                    background: '#f8fafc', border: '1.5px solid #e2e8f0', borderRadius: 12,
                    padding: '4px 20px', textAlign: 'left', marginBottom: 24,
                }}>
                    <DetailRow label="Reference" value={invitation?.request?.request_number} />
                    {invitation?.submitted_at_text && (
                        <DetailRow label="Submitted" value={invitation.submitted_at_text} divided />
                    )}
                    {invitation?.request?.project_name && (
                        <DetailRow label="Project" value={invitation.request.project_name} divided />
                    )}
                </div>

                <p style={{ fontSize: 13, color: '#94a3b8', lineHeight: 1.65, margin: 0 }}>
                    This link has now been closed. No further action is required.
                    <br />
                    We appreciate your time and look forward to working with you.
                </p>
            </Body>
        </Shell>
    );
}
