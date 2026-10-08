/**
 * The chrome every auth screen sits in: a brand panel beside the form on
 * desktop, a gradient hero above it on a phone. Both echo the shell's navy
 * sidebar so the app does not change identity at the door.
 *
 * Extracted from the login page when the other four screens were converted —
 * five copies of a brand panel is exactly the drift the Blade pages had
 * (CLAUDE.md #12). The two shells stay separate functions rather than one
 * with a flag, because they are genuinely different layouts; what they share
 * is the palette and the copy, which is the part that must not diverge.
 */

const BRAND = {
    name: 'SteelERP',
    tagline: 'Manufacturing & Trading',
    modules: 'Purchase · Inventory · Production · Sales',
};

const gradient = 'linear-gradient(150deg,#0f172a 0%,#1e293b 55%,#1d4ed8 100%)';

function Mark({ size }) {
    return (
        <div style={{
            width: size, height: size, borderRadius: size * 0.27, background: '#2563eb',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontWeight: 800, fontSize: size * 0.41, color: '#fff',
        }}>
            S
        </div>
    );
}

export function DesktopAuthScreen({ title, subtitle, blurb, children }) {
    return (
        <div style={{
            minHeight: '100vh', display: 'flex', alignItems: 'center',
            justifyContent: 'center', background: '#f1f5f9', padding: 24,
        }}>
            <div style={{
                display: 'grid', gridTemplateColumns: '1fr 1fr',
                width: '100%', maxWidth: 900, minHeight: 520,
                borderRadius: 18, overflow: 'hidden',
                boxShadow: '0 20px 50px rgba(15,23,42,.16)', background: '#fff',
            }}>
                <div style={{
                    position: 'relative', overflow: 'hidden', padding: 40,
                    display: 'flex', flexDirection: 'column', justifyContent: 'space-between',
                    background: gradient, color: '#fff',
                }}>
                    <div style={{
                        position: 'absolute', top: -70, right: -70, width: 220, height: 220,
                        borderRadius: '9999px', background: 'rgba(255,255,255,.06)',
                    }} />
                    <div style={{ position: 'relative' }}>
                        <div style={{ marginBottom: 20 }}><Mark size={44} /></div>
                        <div style={{ fontSize: 24, fontWeight: 700, letterSpacing: '-0.01em' }}>
                            {BRAND.name}
                        </div>
                        <div style={{ fontSize: 13, color: '#cbd5e1', marginTop: 4 }}>
                            {BRAND.tagline}
                        </div>
                    </div>
                    <div style={{ position: 'relative', fontSize: 13, color: '#cbd5e1', lineHeight: 1.7 }}>
                        {BRAND.modules}
                        <div style={{ marginTop: 8, color: '#94a3b8' }}>{blurb}</div>
                    </div>
                </div>

                <div style={{ padding: 40, display: 'flex', flexDirection: 'column', justifyContent: 'center' }}>
                    <h1 style={{ fontSize: 22, fontWeight: 700, color: '#0f172a', margin: '0 0 4px' }}>
                        {title}
                    </h1>
                    <p style={{ fontSize: 14, color: '#64748b', margin: '0 0 26px' }}>{subtitle}</p>
                    {children}
                </div>
            </div>
        </div>
    );
}

/**
 * The phone sign-in frame from SteelERP-Mobile-Designs-V2: the brand on a
 * dark field, the form on a white sheet that rises from the bottom. `m-ui`
 * gives the fields and buttons inside it the design's touch sizes
 * (resources/css/app.css).
 */
export function MobileAuthScreen({ title, subtitle, children }) {
    return (
        <div className="m-ui" style={{
            minHeight: '100vh', background: '#0F172A', display: 'flex', flexDirection: 'column',
            position: 'relative', overflow: 'hidden', fontFamily: "'Inter', system-ui, sans-serif",
        }}>
            <div aria-hidden="true" style={{
                position: 'absolute', right: -90, top: -70, width: 280, height: 280,
                borderRadius: '50%', background: '#1E293B',
            }} />
            <div aria-hidden="true" style={{
                position: 'absolute', left: -60, top: 250, width: 200, height: 200,
                borderRadius: '50%', background: '#1E3A8A', opacity: 0.55,
            }} />

            <div style={{
                position: 'relative', padding: 'calc(env(safe-area-inset-top, 0px) + 56px) 28px 36px',
                display: 'flex', flexDirection: 'column', gap: 18,
            }}>
                <Mark size={56} />
                <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                    <span style={{ fontSize: 30, fontWeight: 700, color: '#FFFFFF', letterSpacing: '-0.02em' }}>
                        {BRAND.name}
                    </span>
                    <span style={{ fontSize: 15, color: '#CBD5E1' }}>{BRAND.tagline}</span>
                </div>
                <span style={{ fontSize: 13, color: '#94A3B8', letterSpacing: '0.02em' }}>{BRAND.modules}</span>
            </div>

            <div style={{
                position: 'relative', flex: 1, background: '#FFFFFF', borderRadius: '28px 28px 0 0',
                padding: '32px 24px calc(env(safe-area-inset-bottom, 0px) + 28px)',
                display: 'flex', flexDirection: 'column', boxSizing: 'border-box',
            }}>
                <h1 style={{ margin: '0 0 6px', fontSize: 26, fontWeight: 700, color: '#0F172A', letterSpacing: '-0.02em' }}>
                    {title}
                </h1>
                <p style={{ margin: '0 0 22px', fontSize: 15, color: '#475569' }}>{subtitle}</p>
                {children}
            </div>
        </div>
    );
}

/** Pushes a mobile action to the bottom without pinning it over the keyboard. */
export function MobileSpacer() {
    return <div style={{ flex: 1, minHeight: 12 }} />;
}
