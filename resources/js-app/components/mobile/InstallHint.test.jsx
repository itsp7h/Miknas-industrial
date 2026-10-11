import { describe, it, expect, afterEach, beforeEach, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import InstallHint from './InstallHint';

const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
const ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36';

const as = (userAgent, { standalone = false } = {}) => {
    Object.defineProperty(navigator, 'userAgent', { value: userAgent, configurable: true });
    Object.defineProperty(navigator, 'standalone', { value: standalone, configurable: true });
};

describe('InstallHint', () => {
    beforeEach(() => {
        const store = new Map();
        vi.stubGlobal('localStorage', {
            getItem: (k) => (store.has(k) ? store.get(k) : null),
            setItem: (k, v) => store.set(k, String(v)),
        });
    });
    afterEach(() => {
        vi.unstubAllGlobals();
        delete navigator.userAgent;
        delete navigator.standalone;
    });

    it('tells an iPhone in a Safari tab how to get the app full screen', () => {
        as(IPHONE);
        render(<InstallHint />);
        expect(screen.getByTestId('install-hint')).toHaveTextContent('Add to Home Screen');
    });

    it('never shows once opened from the home screen', () => {
        as(IPHONE, { standalone: true });
        render(<InstallHint />);
        expect(screen.queryByTestId('install-hint')).not.toBeInTheDocument();
    });

    it('is not offered off iOS', () => {
        as(ANDROID);
        render(<InstallHint />);
        expect(screen.queryByTestId('install-hint')).not.toBeInTheDocument();
    });

    it('stays dismissed', () => {
        as(IPHONE);
        const { unmount } = render(<InstallHint />);
        fireEvent.click(screen.getByRole('button', { name: 'Dismiss' }));
        expect(screen.queryByTestId('install-hint')).not.toBeInTheDocument();
        unmount();

        render(<InstallHint />);
        expect(screen.queryByTestId('install-hint')).not.toBeInTheDocument();
    });
});
