export default function FormField({ label, name, value, onChange, error, type = 'text' }) {
    const inputProps = {
        id: name,
        name,
        value,
        onChange: (e) => onChange(name, e.target.value),
        className: `border rounded-md px-3 py-2 text-sm w-full ${error ? 'border-red-400' : 'border-gray-300'}`,
    };

    return (
        <div className="mb-4">
            <label htmlFor={name} className="block text-sm font-medium text-gray-700 mb-1">
                {label}
            </label>
            {type === 'textarea' ? (
                <textarea {...inputProps} />
            ) : (
                <input type={type} {...inputProps} />
            )}
            {error && <p className="text-sm text-red-600 mt-1">{error}</p>}
        </div>
    );
}
