const VARIANT_CLASSES = {
    primary: 'bg-green-600 hover:bg-green-700 text-white',
    secondary: 'bg-white hover:bg-gray-50 text-gray-800 border border-gray-300',
    danger: 'bg-red-600 hover:bg-red-700 text-white',
};

export default function Button({
    children,
    variant = 'primary',
    loading = false,
    disabled = false,
    onClick,
    type = 'button',
}) {
    return (
        <button
            type={type}
            onClick={onClick}
            disabled={disabled || loading}
            className={`px-4 py-2 rounded-md text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed ${VARIANT_CLASSES[variant]}`}
        >
            {loading ? 'Loading…' : children}
        </button>
    );
}
