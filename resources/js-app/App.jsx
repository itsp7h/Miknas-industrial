import { Routes, Route } from 'react-router-dom';
import DashboardPage from './pages/DashboardPage';

export default function App({ currentUserId }) {
    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white border-b border-gray-100 px-6 py-4">
                <span className="font-bold text-gray-800">SteelERP</span>
            </header>
            <main className="p-6">
                <Routes>
                    <Route path="/app" element={<DashboardPage currentUserId={currentUserId} />} />
                </Routes>
            </main>
        </div>
    );
}
