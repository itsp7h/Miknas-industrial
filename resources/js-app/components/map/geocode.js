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

async function ask(path, params) {
    const query = new URLSearchParams({ format: 'jsonv2', ...params });
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
