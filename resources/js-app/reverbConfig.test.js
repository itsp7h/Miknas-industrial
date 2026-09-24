import { describe, it, expect } from 'vitest';
import { reverbConfig } from './reverbConfig';

const shell = (reverb) => {
    const el = document.createElement('div');
    if (reverb !== undefined) el.dataset.reverb = typeof reverb === 'string' ? reverb : JSON.stringify(reverb);
    return el;
};

const BUILT = {
    VITE_REVERB_APP_KEY: 'built-key',
    VITE_REVERB_HOST: 'built.example',
    VITE_REVERB_PORT: '8080',
    VITE_REVERB_SCHEME: 'http',
};

describe('reverbConfig', () => {
    it('uses what the page says over what the bundle was built with', () => {
        const config = reverbConfig(
            shell({ key: 'k', host: 'staging-steelerp.p7h.me', port: 443, scheme: 'https' }),
            BUILT,
        );

        expect(config).toEqual({ key: 'k', host: 'staging-steelerp.p7h.me', port: 443, forceTLS: true });
    });

    it('falls back to the build values when the page carries nothing', () => {
        expect(reverbConfig(shell(), BUILT))
            .toEqual({ key: 'built-key', host: 'built.example', port: 8080, forceTLS: false });
    });

    it('falls back field by field, so one blank setting does not blank the rest', () => {
        const config = reverbConfig(shell({ key: 'k', host: null, port: 443, scheme: '' }), BUILT);

        expect(config).toEqual({ key: 'k', host: 'built.example', port: 443, forceTLS: false });
    });

    it('survives an attribute that is not JSON', () => {
        expect(reverbConfig(shell('{not json'), BUILT).host).toBe('built.example');
    });

    it('defaults to wss on 443 when nothing says otherwise', () => {
        expect(reverbConfig(shell(), {})).toEqual({ key: undefined, host: undefined, port: 443, forceTLS: true });
    });

    it('works with no shell element at all', () => {
        expect(reverbConfig(null, BUILT).host).toBe('built.example');
    });
});
