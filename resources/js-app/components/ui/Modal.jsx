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
    if (!open) return null;

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
