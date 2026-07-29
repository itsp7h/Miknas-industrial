const VARIANT_CLASSES = {
    primary: 'px-4 py-2 rounded-md bg-green-600 hover:bg-green-700 text-white',
    secondary: 'px-4 py-2 rounded-md bg-white hover:bg-gray-50 text-gray-800 border border-gray-300',
    danger: 'px-4 py-2 rounded-md bg-red-600 hover:bg-red-700 text-white',
    link: 'text-blue-600 hover:text-blue-800',
    'link-danger': 'text-red-600 hover:text-red-800',
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
            className={`text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed ${VARIANT_CLASSES[variant]}`}
        >
            {loading ? 'Loading…' : children}
        </button>
    );
}
