import { Routes, Route } from 'react-router-dom';
import AppShell from './layouts/AppShell';
import DashboardPage from './pages/DashboardPage';
import SupplierListPage from './pages/desktop/purchase/SupplierListPage';

export default function App({ currentUserId }) {
    return (
        <AppShell currentUserId={currentUserId}>
            <Routes>
                <Route path="/app" element={<DashboardPage currentUserId={currentUserId} />} />
                <Route path="/app/purchase/suppliers" element={<SupplierListPage />} />
                <Route path="*" element={<div>Page not found.</div>} />
            </Routes>
        </AppShell>
    );
}
