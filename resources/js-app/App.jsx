import { Routes, Route } from 'react-router-dom';

export default function App() {
    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white border-b border-gray-100 px-6 py-4">
                <span className="font-bold text-gray-800">SteelERP</span>
            </header>
            <main className="p-6">
                <Routes>
                    <Route path="/app" element={<div>Welcome to the new SteelERP app.</div>} />
                </Routes>
            </main>
        </div>
    );
}
