import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App';
import { ToastProvider } from './components/ui/Toast';
import { setActiveCurrency } from './currency';

const container = document.getElementById('react-app');
const currentUserId = Number(container.dataset.userId) || null;
const userName = container.dataset.userName || 'User';
const userEmail = container.dataset.userEmail || '';
const isAdmin = container.dataset.isAdmin === '1';
const logoutUrl = container.dataset.logoutUrl || '/logout';
const csrfToken = container.dataset.csrfToken || '';
const canViewAllPurchaseRequests = container.dataset.canViewAllPurchaseRequests === '1';
const canViewActivePipeline = container.dataset.canViewActivePipeline === '1';
const canViewOwnPurchaseRequests = container.dataset.canViewOwnPurchaseRequests === '1';

// Before the first render: money helpers are plain functions called from
// table column definitions, not hooks, so they read this rather than context.
setActiveCurrency(container.dataset.currency);

let permissions = [];
try {
    permissions = JSON.parse(container.dataset.permissions || '[]');
} catch {
    // A malformed list must not take the whole shell down; an Admin still
    // gets everything, and everyone else sees the Dashboard and no more.
    permissions = [];
}

createRoot(container).render(
    <StrictMode>
        <BrowserRouter>
            <ToastProvider>
                <App
                    currentUserId={currentUserId}
                    userName={userName}
                    userEmail={userEmail}
                    isAdmin={isAdmin}
                    permissions={permissions}
                    logoutUrl={logoutUrl}
                    csrfToken={csrfToken}
                    canViewAllPurchaseRequests={canViewAllPurchaseRequests}
                    canViewActivePipeline={canViewActivePipeline}
                    canViewOwnPurchaseRequests={canViewOwnPurchaseRequests}
                />
            </ToastProvider>
        </BrowserRouter>
    </StrictMode>
);
