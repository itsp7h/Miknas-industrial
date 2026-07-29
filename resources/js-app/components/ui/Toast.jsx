import { createContext, useCallback, useContext, useState } from 'react';

const ToastContext = createContext(null);

const TYPE_CLASSES = {
    success: 'bg-green-600',
    error: 'bg-red-600',
    info: 'bg-blue-600',
    warn: 'bg-amber-500',
};

export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);

    const showToast = useCallback((message, type = 'info') => {
        const id = `${Date.now()}-${Math.random()}`;
        setToasts((current) => [...current, { id, message, type }]);

        setTimeout(() => {
            setToasts((current) => current.filter((t) => t.id !== id));
        }, 4000);
    }, []);

    const dismiss = (id) => setToasts((current) => current.filter((t) => t.id !== id));

    return (
        <ToastContext.Provider value={{ showToast }}>
            {children}
            <div className="fixed bottom-4 right-4 flex flex-col gap-2 z-50">
                {toasts.map((t) => (
                    <div
                        key={t.id}
                        className={`text-white px-4 py-3 rounded-md shadow-lg cursor-pointer ${TYPE_CLASSES[t.type] ?? TYPE_CLASSES.info}`}
                        onClick={() => dismiss(t.id)}
                    >
                        {t.message}
                    </div>
                ))}
            </div>
        </ToastContext.Provider>
    );
}

export function useToast() {
    const ctx = useContext(ToastContext);
    if (!ctx) throw new Error('useToast must be used within a ToastProvider');
    return ctx;
}
