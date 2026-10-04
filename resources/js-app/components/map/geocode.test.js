import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest';
import { addressParts, addressOfPoint, findAddress, pacing, searchPlaces } from './geocode';

beforeEach(() => { pacing.gapMs = 0; pacing.nextSlot = 0; });

afterEach(() => vi.unstubAllGlobals());

const answer = (body) => vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve(body) });

describe('addressParts', () => {
    // Nominatim's own answer for a point in Adliya (fetched 2026-09-28).
    it('maps a Manama answer onto road, block, city and country', () => {
        expect(addressParts({
            road: 'Road 2734', suburb: 'Adliya', city: 'Manama', state: 'Capital Governorate',
            postcode: '338', country: 'Bahrain', country_code: 'bh',
        })).toEqual({ road: 'Road 2734', block: '338', city: 'Manama', country: 'Bahrain' });
    });

    it('reads a postcode as a block only in Bahrain', () => {
        expect(addressParts({ road: 'Sheikh Zayed Road', postcode: '00000', city: 'Dubai', country_code: 'ae' }).block).toBe('');
    });

    it('takes a town or village as the city when there is no city', () => {
        expect(addressParts({ suburb: 'Hidd Industrial Area', town: 'Hidd', country: 'Bahrain' }))
            .toEqual({ road: '', block: '', city: 'Hidd', country: 'Bahrain' });
        expect(addressParts({ hamlet: 'Askar', village: 'Askar', country: 'Bahrain' }).city).toBe('Askar');
    });

    it('leaves blank what the answer lacks', () => {
        expect(addressParts({})).toEqual({ road: '', block: '', city: '', country: '' });
        expect(addressParts(undefined)).toEqual({ road: '', block: '', city: '', country: '' });
    });
});

describe('Nominatim requests', () => {
    it('asks for English, with address details, for a reverse lookup', async () => {
        const fetch = answer({ display_name: 'Road 3803, Manama, Bahrain', address: { road: 'Road 3803', city: 'Manama', country: 'Bahrain' } });
        vi.stubGlobal('fetch', fetch);

        const found = await addressOfPoint(26.2155, 50.586);

        const url = new URL(fetch.mock.calls[0][0]);
        expect(url.pathname).toBe('/reverse');
        expect(url.searchParams.get('accept-language')).toBe('en');
        expect(url.searchParams.get('addressdetails')).toBe('1');
        expect(found).toEqual({ road: 'Road 3803', block: '', city: 'Manama', country: 'Bahrain', label: 'Road 3803, Manama, Bahrain' });
    });

    it('asks for English when searching too', async () => {
        const fetch = answer([]);
        vi.stubGlobal('fetch', fetch);

        await searchPlaces('Hidd');

        expect(new URL(fetch.mock.calls[0][0]).searchParams.get('accept-language')).toBe('en');
    });

    it('resolves to null, not a throw, when the lookup fails or finds nothing', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));
        expect(await addressOfPoint(0, 0)).toBeNull();

        vi.stubGlobal('fetch', answer({ error: 'Unable to geocode' }));
        expect(await addressOfPoint(0, 0)).toBeNull();
    });
});

const road = (name, postcode, lat = '26.2', lon = '50.5') => ({ lat, lon, name, address: { road: name, postcode } });
const replies = (...bodies) => {
    const fetch = vi.fn();
    bodies.forEach((body) => fetch.mockResolvedValueOnce({ ok: true, json: () => Promise.resolve(body) }));
    fetch.mockResolvedValue({ ok: true, json: () => Promise.resolve([]) });

    return fetch;
};
const paramsOf = (fetch, call) => new URL(fetch.mock.calls[call][0]).searchParams;

describe('findAddress', () => {
    it('searches the road as a street, in the city, with address details', async () => {
        const fetch = replies([road('Road 2734', '338', '26.2148', '50.5860')]);
        vi.stubGlobal('fetch', fetch);

        const point = await findAddress({ road: 'Road 2734', block: '338', city: 'Manama', country: 'Bahrain' });

        const params = paramsOf(fetch, 0);
        expect(params.get('street')).toBe('Road 2734');
        expect(params.get('city')).toBe('Manama');
        expect(params.get('country')).toBe('Bahrain');
        expect(params.get('addressdetails')).toBe('1');
        expect(point).toEqual({ latitude: 26.2148, longitude: 50.586, matched: 'road' });
    });

    // Nominatim's real answer for street=Road 17, city=Manama (2026-09-28).
    it('refuses a road that is not the one typed, and falls back to the block', async () => {
        const fetch = replies(
            [road('Road 1520', '314'), road('Road 1417', '314'), road('Road 646', '318')],
            [{ lat: '26.212', lon: '50.5923', address: { postcode: '338' } }],
        );
        vi.stubGlobal('fetch', fetch);

        const point = await findAddress({ road: 'Road 17', block: '338', city: 'Manama', country: 'Bahrain' });

        expect(point).toEqual({ latitude: 26.212, longitude: 50.5923, matched: 'block' });
        expect(paramsOf(fetch, 1).get('postalcode')).toBe('338');
    });

    it('takes the matching road, preferring the one in the typed block', async () => {
        vi.stubGlobal('fetch', replies([
            road('Road 1520', '314', '1', '1'), road('road  17', '310', '2', '2'), road('Road 17', '338', '3', '3'),
        ]));

        expect(await findAddress({ road: 'Road 17', block: '338', city: 'Manama' }))
            .toEqual({ latitude: 3, longitude: 3, matched: 'road' });
    });

    it('falls back to the city when neither the road nor a block is found', async () => {
        const fetch = replies([road('Road 1520', '314')], [{ lat: '26.22', lon: '50.58' }]);
        vi.stubGlobal('fetch', fetch);

        expect(await findAddress({ road: 'Road 17', city: 'Manama', country: 'Bahrain' }))
            .toEqual({ latitude: 26.22, longitude: 50.58, matched: 'city' });
        expect(paramsOf(fetch, 1).get('city')).toBe('Manama');
        expect(paramsOf(fetch, 1).get('street')).toBeNull();
    });

    it('asks nothing for a country alone, and gives null when nothing is found', async () => {
        const fetch = replies();
        vi.stubGlobal('fetch', fetch);

        expect(await findAddress({ country: 'Bahrain' })).toBeNull();
        expect(fetch).not.toHaveBeenCalled();
        expect(await findAddress({ road: 'Nowhere Road' })).toBeNull();
    });

    // Nominatim's usage policy: one request a second at most.
    //
    // On the fake clock: measured on the real one, a timer that fired a
    // millisecond early on a busy CI runner failed this (54 ms against 55).
    it('spaces its requests out', async () => {
        vi.useFakeTimers();
        try {
            pacing.gapMs = 1000;
            const times = [];
            vi.stubGlobal('fetch', vi.fn(() => {
                times.push(Date.now());

                return Promise.resolve({ ok: true, json: () => Promise.resolve([]) });
            }));

            const search = findAddress({ road: 'Road 17', block: '338', city: 'Manama' });
            await vi.runAllTimersAsync();
            await search;

            expect(times).toHaveLength(3);
            expect(times[1] - times[0]).toBeGreaterThanOrEqual(1000);
            expect(times[2] - times[1]).toBeGreaterThanOrEqual(1000);
        } finally {
            // The slot was set on the fake clock, ahead of the real one; left
            // there, the next request anywhere in this file would wait for it.
            pacing.gapMs = 0;
            pacing.nextSlot = 0;
            vi.useRealTimers();
        }
    });
});
