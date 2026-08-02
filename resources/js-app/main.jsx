import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App';
import { ToastProvider } from './components/ui/Toast';

const container = document.getElementById('react-app');
const currentUserId = Number(container.dataset.userId) || null;
const userName = container.dataset.userName || 'User';
const userEmail = container.dataset.userEmail || '';
const isAdmin = container.dataset.isAdmin === '1';
const logoutUrl = container.dataset.logoutUrl || '/logout';
const csrfToken = container.dataset.csrfToken || '';

createRoot(container).render(
    <StrictMode>
        <BrowserRouter>
            <ToastProvider>
                <App
                    currentUserId={currentUserId}
                    userName={userName}
                    userEmail={userEmail}
                    isAdmin={isAdmin}
                    logoutUrl={logoutUrl}
                    csrfToken={csrfToken}
                />
            </ToastProvider>
        </BrowserRouter>
    </StrictMode>
);
