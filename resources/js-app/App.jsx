import { Routes, Route } from 'react-router-dom';
import AppShell from './layouts/AppShell';
import DashboardPage from './pages/DashboardPage';

export default function App({ currentUserId }) {
    return (
        <AppShell>
            <Routes>
                <Route path="/app" element={<DashboardPage currentUserId={currentUserId} />} />
                <Route path="*" element={<div>Page not found.</div>} />
            </Routes>
        </AppShell>
    );
}
