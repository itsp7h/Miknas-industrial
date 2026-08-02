import { Routes, Route } from 'react-router-dom';
import AppShell from './layouts/AppShell';
import useViewport from './hooks/useViewport';
import DashboardPage from './pages/DashboardPage';
import DesktopSupplierListPage from './pages/desktop/purchase/SupplierListPage';
import MobileSupplierListPage from './pages/mobile/purchase/SupplierListPage';

export default function App({ currentUserId, userName, userEmail, isAdmin, logoutUrl, csrfToken }) {
    const viewport = useViewport();
    const SupplierListPage = viewport === 'mobile' ? MobileSupplierListPage : DesktopSupplierListPage;

    return (
        <AppShell
            currentUserId={currentUserId}
            userName={userName}
            userEmail={userEmail}
            isAdmin={isAdmin}
            logoutUrl={logoutUrl}
            csrfToken={csrfToken}
        >
            <Routes>
                <Route path="/app" element={<DashboardPage currentUserId={currentUserId} />} />
                <Route path="/app/purchase/suppliers" element={<SupplierListPage />} />
                <Route path="*" element={<div>Page not found.</div>} />
            </Routes>
        </AppShell>
    );
}
