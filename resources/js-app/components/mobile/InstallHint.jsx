import { useState } from 'react';
import Icon from './icons';
import { C, ZONES } from './theme';

const DISMISSED_KEY = 'steelerp.installHintDismissed';

// iPadOS reports itself as a Mac, so a Mac with a touch screen is an iPad.
const isIos = () =>
    /iPhone|iPad|iPod/.test(navigator.userAgent)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

const isStandalone = () =>
    navigator.standalone === true
    || (typeof window.matchMedia === 'function' && window.matchMedia('(display-mode: standalone)').matches);

const wasDismissed = () => {
    try {
        return localStorage.getItem(DISMISSED_KEY) === '1';
    } catch {
        return false;
    }
};

/**
 * Safari will not let a page hide its own address bar; only a site added to
 * the home screen opens full screen. Nothing in Safari says so, so on an
 * iPhone still in a browser tab this card says how, once — until it is
 * dismissed, or the app is opened from the home screen, where it never shows.
 */
export default function InstallHint() {
    const [show, setShow] = useState(() => isIos() && !isStandalone() && !wasDismissed());

    if (!show) return null;

    const dismiss = () => {
        try {
            localStorage.setItem(DISMISSED_KEY, '1');
        } catch {
            // Private mode: it will simply show again next visit.
        }
        setShow(false);
    };

    return (
        <div
            role="note"
            data-testid="install-hint"
            style={{
                position: 'fixed', left: 12, right: 12, zIndex: 46,
                bottom: 'calc(80px + env(safe-area-inset-bottom, 0px))',
                display: 'flex', gap: 12, alignItems: 'flex-start', padding: '14px 14px 14px 16px',
                background: C.card, borderRadius: 16, border: `1px solid ${C.line}`,
                boxShadow: '0 12px 32px rgba(15,23,42,0.18)',
            }}
        >
            <img src="/icons/icon-180.png" alt="" width={40} height={40} style={{ borderRadius: 10, flexShrink: 0 }} />
            <div style={{ flex: 1, minWidth: 0, fontSize: 13, lineHeight: 1.45, color: C.text2 }}>
                <div style={{ fontWeight: 600, color: C.text, marginBottom: 2 }}>Use SteelERP full screen</div>
                Tap <Icon name="upload" size={15} style={{ display: 'inline-block', verticalAlign: '-2px', color: ZONES.home.solid }} /> Share,
                then <b>Add to Home Screen</b>. Open it from there — no address bar.
            </div>
            <button
                type="button"
                aria-label="Dismiss"
                onClick={dismiss}
                style={{ border: 0, background: 'transparent', padding: 4, margin: -4, color: C.fainter, cursor: 'pointer' }}
            >
                <Icon name="close" size={18} />
            </button>
        </div>
    );
}
