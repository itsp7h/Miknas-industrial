import { useEffect, useState } from 'react';

const CSS_URL = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
const JS_URL = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';

/**
 * Loads Leaflet from the same CDN the Blade page used, once per document, and
 * reports when window.L is ready. Keeping the CDN (rather than adding leaflet to
 * the bundle) means the page behaves exactly as it did — including offline,
 * where `ready` stays false and the modal falls back to its latitude/longitude
 * fields instead of showing a broken map frame.
 */
export default function useLeaflet(enabled) {
    const [ready, setReady] = useState(() => typeof window !== 'undefined' && !!window.L);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        if (!enabled || ready || failed) return;

        if (!document.querySelector(`link[href="${CSS_URL}"]`)) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = CSS_URL;
            document.head.appendChild(link);
        }

        const existing = document.querySelector(`script[src="${JS_URL}"]`);
        const script = existing ?? document.createElement('script');

        function onLoad() {
            if (window.L) setReady(true);
            else setFailed(true);
        }

        script.addEventListener('load', onLoad);
        script.addEventListener('error', () => setFailed(true));

        if (!existing) {
            script.src = JS_URL;
            script.async = true;
            document.body.appendChild(script);
        } else if (window.L) {
            setReady(true);
        }

        return () => script.removeEventListener('load', onLoad);
    }, [enabled, ready, failed]);

    return { ready, failed };
}
