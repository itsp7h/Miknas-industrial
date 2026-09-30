// Address ⇆ coordinate lookups, against OpenStreetMap's Nominatim.
//
// Nominatim is free and needs no API key, which is why the map picker uses it
// rather than Google. The trade is its usage policy: at most one request a
// second, and no bulk querying. So nothing here fires on a keystroke — the
// caller searches on an explicit Enter or button press, and reverse-geocodes
// only when a pin actually lands somewhere new.
//
// (CLAUDE.md #6 bans server round-trips for *search bars*, which filter records
// already on the page. This is not one: no amount of client-side filtering can
// turn "Hidd Industrial Area" into a latitude.)

const BASE = 'https://nominatim.openstreetmap.org';

// Bahrain — where the warehouses are. Nominatim ranks results inside the box
// first without excluding anything outside it.
const VIEWBOX = '50.30,26.40,50.85,25.53';

// Nominatim's usage policy: at most one request a second. A search that
// tries a road, then a block, then a city makes several in a row, so every
// request waits its turn behind the last.
// Exported so a test can shorten the gap rather than wait, and reset the slot.
export const pacing = { gapMs: 1000, nextSlot: 0 };

async function waitTurn() {
    const now = Date.now();
    const at = Math.max(now, pacing.nextSlot);
    pacing.nextSlot = at + pacing.gapMs;
    if (at > now) await new Promise((resolve) => setTimeout(resolve, at - now));
}

async function ask(path, params) {
    await waitTurn();
    // English names wherever OpenStreetMap has them, whatever the browser's
    // own language — the same reason the map tiles are English.
    const query = new URLSearchParams({ format: 'jsonv2', 'accept-language': 'en', ...params });
    const response = await fetch(`${BASE}/${path}?${query}`, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error('The map service is not answering. Drop the pin by hand, or try again.');
    }

    return response.json();
}

/** Free-text place → up to five candidates, nearest-to-Bahrain first. */
export async function searchPlaces(query) {
    const results = await ask('search', {
        q: query,
        limit: '5',
        viewbox: VIEWBOX,
        bounded: '0',
    });

    return (Array.isArray(results) ? results : []).map((result) => ({
        id: `${result.osm_type ?? 'p'}${result.osm_id ?? result.place_id}`,
        label: result.display_name,
        latitude: Number(result.lat),
        longitude: Number(result.lon),
    }));
}

/**
 * Coordinates → a printable address.
 *
 * Returns '' rather than throwing when the point has no address — open water,
 * or desert with nothing named near it. A pin there is still a valid pin, so a
 * blank label must not stop the user saving.
 */
export async function describePoint(latitude, longitude) {
    try {
        const result = await ask('reverse', {
            lat: String(latitude),
            lon: String(longitude),
            zoom: '18',
        });

        return result?.display_name ?? '';
    } catch {
        return '';
    }
}

// Nominatim names the same kind of place differently from country to country
// — a small town comes back as a town or a village — so each part takes the
// first of these it finds.
const PARTS = {
    road: ['road', 'pedestrian', 'street', 'footway', 'path'],
    city: ['city', 'town', 'village', 'municipality', 'county', 'state'],
    country: ['country'],
};

/**
 * Nominatim's `address` object → `{ road, block, city, country }`, ''
 * for what it lacks. `block` is Bahrain's block number, which Nominatim files
 * as the postcode ("Adliya, 338"); elsewhere a postcode is not a block, so it
 * is left blank rather than mislabelled.
 */
export function addressParts(address = {}) {
    const first = (keys) => keys.map((key) => address?.[key]).find((value) => value) ?? '';
    const parts = Object.fromEntries(Object.entries(PARTS).map(([part, keys]) => [part, first(keys)]));
    parts.block = address?.country_code === 'bh' ? String(address?.postcode ?? '') : '';

    return parts;
}

/**
 * Coordinates → the address in parts, plus the one-line label.
 *
 * Resolves to null rather than throwing when the lookup fails or the point
 * has no address (open water, empty desert): the pin is still valid, and the
 * fields stay as they were for the user to fill.
 */
export async function addressOfPoint(latitude, longitude) {
    try {
        const result = await ask('reverse', {
            lat: String(latitude),
            lon: String(longitude),
            zoom: '18',
            addressdetails: '1',
        });
        if (!result?.address) return null;

        return { ...addressParts(result.address), label: result.display_name ?? '' };
    } catch {
        return null;
    }
}

// "Road 17", "road  17" and "ROAD 17" are one road.
const sameRoad = (a, b) => String(a ?? '').toLowerCase().replace(/\s+/g, ' ').trim()
    === String(b ?? '').toLowerCase().replace(/\s+/g, ' ').trim();

const pointOf = (hit, matched) => ({ latitude: Number(hit.lat), longitude: Number(hit.lon), matched });

async function candidates(params) {
    const results = await ask('search', { ...params, addressdetails: '1', limit: '10', viewbox: VIEWBOX, bounded: '0' });

    return Array.isArray(results) ? results : [];
}

/**
 * The other way: typed address parts → the point they name, or null.
 *
 * `matched` says how exact the point is: 'road' when the road itself was
 * found, 'block' or 'city' when it was not and the pin fell back to the
 * block's or the city's centre — for the caller to tell the user.
 *
 * A road is only taken when the answer really is that road. Nominatim reads
 * "Road 17" as house number 17 on a street called "Road" and answers with
 * whichever "Road …" it ranks first (Road 1520, Road 646…); an unchecked
 * first hit is how a pin once landed on Road 508 for Road 17. Of the
 * matching answers, one in the typed block (Bahrain files blocks as
 * postcodes) wins.
 */
export async function findAddress({ road = '', block = '', city = '', country = '' }) {
    const clean = (value) => String(value ?? '').trim();
    const [r, b, c, n] = [road, block, city, country].map(clean);
    if (!r && !b && !c) return null;

    try {
        if (r) {
            const found = (await candidates(Object.fromEntries(Object.entries({
                street: r, city: c, country: n,
            }).filter(([, value]) => value)))).filter((hit) => sameRoad(hit.address?.road ?? hit.name, r));
            const best = found.find((hit) => b && hit.address?.postcode === b) ?? found[0];
            if (best) return pointOf(best, 'road');
        }
        if (b) {
            const [hit] = await candidates({ postalcode: b, country: n || 'Bahrain' });
            if (hit) return pointOf(hit, 'block');
        }
        if (c) {
            const [hit] = await candidates(Object.fromEntries(Object.entries({ city: c, country: n }).filter(([, value]) => value)));
            if (hit) return pointOf(hit, 'city');
        }
    } catch {
        // A lookup that fails leaves the pin where it was.
    }

    return null;
}
