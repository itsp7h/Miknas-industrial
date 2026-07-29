import { Routes, Route, Link } from 'react-router-dom';
import DashboardPage from './pages/DashboardPage';
import SupplierListPage from './pages/purchase/SupplierListPage';

export default function App({ currentUserId }) {
    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white border-b border-gray-100 px-6 py-4 flex gap-6 items-center">
                <span className="font-bold text-gray-800">SteelERP</span>
                <Link to="/app" className="text-sm text-gray-600">Dashboard</Link>
                <Link to="/app/purchase/suppliers" className="text-sm text-gray-600">Suppliers</Link>
            </header>
            <main className="p-6">
                <Routes>
                    <Route path="/app" element={<DashboardPage currentUserId={currentUserId} />} />
                    <Route path="/app/purchase/suppliers" element={<SupplierListPage />} />
                    <Route path="*" element={<div>Page not found.</div>} />
                </Routes>
            </main>
        </div>
    );
}
