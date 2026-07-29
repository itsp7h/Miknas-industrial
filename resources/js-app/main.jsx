import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App';
import { ToastProvider } from './components/ui/Toast';

const container = document.getElementById('react-app');
const currentUserId = Number(container.dataset.userId) || null;

createRoot(container).render(
    <StrictMode>
        <BrowserRouter>
            <ToastProvider>
                <App currentUserId={currentUserId} />
            </ToastProvider>
        </BrowserRouter>
    </StrictMode>
);
