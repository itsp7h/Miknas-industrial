import { describe, it, expect } from 'vitest';
import mapLink from './mapLink';

describe('mapLink', () => {
    it('builds an OpenStreetMap link centred on the pin', () => {
        expect(mapLink(26.1421, 50.5832)).toBe(
            'https://www.openstreetmap.org/?mlat=26.1421&mlon=50.5832#map=17/26.1421/50.5832'
        );
    });

    // A warehouse recorded before the map picker existed has no pin at all, and
    // a row must not offer a link to 0,0 in the Gulf of Guinea.
    it('returns nothing without a complete pair', () => {
        expect(mapLink(null, null)).toBeNull();
        expect(mapLink(26.1421, null)).toBeNull();
        expect(mapLink(undefined, 50.5832)).toBeNull();
        expect(mapLink('', '')).toBeNull();
    });

    it('accepts the strings an API payload can carry', () => {
        expect(mapLink('26.1421', '50.5832')).toContain('mlat=26.1421');
    });
});
