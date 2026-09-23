import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import useViewport from './hooks/useViewport';
import DesktopQuotePage from './pages/desktop/rfq/QuotePage';
import MobileQuotePage from './pages/mobile/rfq/QuotePage';

/**
 * A third Vite entry, alongside main.jsx (the SPA) and auth.jsx (the login
 * screen), because the supplier portal is the one screen reached by someone
 * with no account at all. It carries only what that visitor needs — no
 * router, no shell, no Echo, no notification bell.
 *
 * The Blade portal chose its layout by sniffing the user agent, which meant a
 * tablet in portrait got the desktop table and a rotated phone kept whatever
 * it was served. useViewport picks on width and re-picks on rotation.
 */
function RfqApp({ token }) {
    const viewport = useViewport();
    const QuotePage = viewport === 'mobile' ? MobileQuotePage : DesktopQuotePage;

    return <QuotePage token={token} />;
}

const container = document.getElementById('rfq-app');

if (container) {
    createRoot(container).render(
        <StrictMode>
            <RfqApp token={container.dataset.token} />
        </StrictMode>
    );
}
