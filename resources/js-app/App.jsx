import { Routes, Route } from 'react-router-dom';
import DashboardPage from './pages/DashboardPage';
import SupplierListPage from './pages/purchase/SupplierListPage';

// No header/nav here — this mounts inside layouts/app.blade.php's existing
// sidebar and topbar (see resources/views/app-shell.blade.php), so this
// component owns only the routed page content, not the app chrome.
export default function App({ currentUserId }) {
    return (
        <Routes>
            <Route path="/app" element={<DashboardPage currentUserId={currentUserId} />} />
            <Route path="/app/purchase/suppliers" element={<SupplierListPage />} />
            <Route path="*" element={<div>Page not found.</div>} />
        </Routes>
    );
}
