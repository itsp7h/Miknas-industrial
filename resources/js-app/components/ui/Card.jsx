export default function Card({ title, children }) {
    return (
        <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
            {title && <h3 className="font-semibold text-gray-800 mb-3">{title}</h3>}
            {children}
        </div>
    );
}
