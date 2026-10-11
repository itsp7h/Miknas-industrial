import useViewport from '../../hooks/useViewport';

/**
 * `maxWidth` widens the panel for a form that needs the room — the warehouse
 * map picker is the first. Tailwind JIT would not compile a `max-w-*` class
 * used only in here (CLAUDE.md #1), which is why the width is an inline style.
 *
 * The panel is also capped at the viewport with the body scrolling inside it.
 * Before that a tall form simply ran off the bottom of a laptop screen, taking
 * its Save button with it.
 */
export default function Modal({ open, title, onClose, children, maxWidth = '32rem' }) {
    const compact = useViewport() === 'mobile';
    if (!open) return null;

    // On a phone: a sheet up from the bottom, over the tab bar, in the mobile
    // design's sizes (`m-ui`), rather than a box floating mid-screen.
    if (compact) {
        return (
            <div
                className="m-ui"
                onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}
                style={{
                    position: 'fixed', inset: 0, zIndex: 60, background: 'rgba(15,23,42,0.45)',
                    display: 'flex', alignItems: 'flex-end',
                }}
            >
                <div
                    role="dialog" aria-modal="true" aria-label={title}
                    style={{
                        width: '100%', maxHeight: 'calc(100vh - 40px)', display: 'flex', flexDirection: 'column',
                        background: '#FFFFFF', borderRadius: '22px 22px 0 0', fontFamily: "'Inter', system-ui, sans-serif",
                    }}
                >
                    <div style={{ flexShrink: 0, padding: '8px 16px 0' }}>
                        <div aria-hidden="true" style={{ width: 36, height: 5, borderRadius: 3, background: '#CBD5E1', margin: '0 auto 6px' }} />
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12, minHeight: 44 }}>
                            <h2 style={{ margin: 0, fontSize: 18, fontWeight: 600, color: '#0F172A' }}>{title}</h2>
                            <button
                                aria-label="Close" onClick={onClose}
                                style={{ background: '#F1F5F9', border: 0, width: 32, height: 32, borderRadius: 16, fontSize: 20, color: '#475569', cursor: 'pointer' }}
                            >
                                ×
                            </button>
                        </div>
                    </div>
                    <div style={{ overflowY: 'auto', padding: '12px 16px calc(20px + env(safe-area-inset-bottom, 0px))' }}>{children}</div>
                </div>
            </div>
        );
    }

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-40" style={{ padding: '1rem' }}>
            <div
                className="bg-white rounded-lg shadow-xl w-full"
                style={{ maxWidth, maxHeight: 'calc(100vh - 2rem)', display: 'flex', flexDirection: 'column' }}
            >
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100" style={{ flexShrink: 0 }}>
                    <h2 className="font-semibold text-gray-800">{title}</h2>
                    <button aria-label="Close" onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        ×
                    </button>
                </div>
                <div className="p-6" style={{ overflowY: 'auto' }}>{children}</div>
            </div>
        </div>
    );
}
