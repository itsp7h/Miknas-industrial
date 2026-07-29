export default function Modal({ open, title, onClose, children }) {
    if (!open) return null;

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-40" style={{ padding: '1rem' }}>
            <div className="bg-white rounded-lg shadow-xl w-full" style={{ maxWidth: '32rem' }}>
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h2 className="font-semibold text-gray-800">{title}</h2>
                    <button aria-label="Close" onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        ×
                    </button>
                </div>
                <div className="p-6">{children}</div>
            </div>
        </div>
    );
}
