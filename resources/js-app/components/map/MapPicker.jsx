import { useEffect, useRef, useState } from 'react';
import L from 'leaflet';
import { searchPlaces, describePoint } from './geocode';

// Bahrain. Both existing warehouses are here — Askar and Hidd — so an empty
// picker opens where the next one is likely to be rather than mid-Atlantic.
const DEFAULT_CENTER = [26.0667, 50.5577];
const DEFAULT_ZOOM = 10;
const PINNED_ZOOM = 15;

const TILES = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

/**
 * Leaflet's default marker is a PNG it resolves by URL, which a bundler
 * rewrites and Leaflet then cannot find — the classic broken-image pin. A
 * divIcon sidesteps the asset pipeline entirely, and matches how every other
 * icon in this app is drawn.
 */
const PIN = L.divIcon({
    className: '',
    html: `<svg width="30" height="30" viewBox="0 0 24 24" fill="#dc2626" stroke="#fff" stroke-width="1.5">
        <path d="M12 2C8.1 2 5 5.1 5 9c0 5.2 7 13 7 13s7-7.8 7-13c0-3.9-3.1-7-7-7z"/>
        <circle cx="12" cy="9" r="2.5" fill="#fff" stroke="none"/>
    </svg>`,
    iconSize: [30, 30],
    iconAnchor: [15, 29],
});

const round = (n) => Math.round(n * 1e7) / 1e7;

/**
 * Drop a pin on a map and get back a point and the address that sits under it.
 *
 * `onPick({ latitude, longitude, address })` fires for every way a point can be
 * chosen — clicking the map, dragging the pin, picking a search result, or the
 * browser's own geolocation. `address` is Nominatim's best guess and arrives a
 * moment after the coordinates; the caller keeps it in an editable field,
 * because the label a warehouse goes by ("Yard 3, Gate B") is rarely the one a
 * map knows it by.
 */
export default function MapPicker({ latitude, longitude, onPick, disabled = false }) {
    const container = useRef(null);
    const map = useRef(null);
    const marker = useRef(null);
    const pick = useRef(onPick);
    pick.current = onPick;

    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [searching, setSearching] = useState(false);
    const [notice, setNotice] = useState('');

    const hasPoint = Number.isFinite(latitude) && Number.isFinite(longitude);

    // Coordinates land immediately; the address follows when Nominatim answers.
    // Two calls rather than one await, so a slow or blocked lookup never delays
    // the pin the user just placed.
    function choose(lat, lng, address) {
        const point = { latitude: round(lat), longitude: round(lng) };
        pick.current({ ...point, address });

        if (address === undefined) {
            describePoint(point.latitude, point.longitude).then((found) => {
                if (found) pick.current({ ...point, address: found });
            });
        }
    }

    // Built once. A map torn down and rebuilt on every coordinate change would
    // throw away the user's pan and zoom mid-placement.
    useEffect(() => {
        if (!container.current || map.current) return undefined;

        const instance = L.map(container.current, { scrollWheelZoom: false })
            .setView(hasPoint ? [latitude, longitude] : DEFAULT_CENTER, hasPoint ? PINNED_ZOOM : DEFAULT_ZOOM);

        L.tileLayer(TILES, { attribution: ATTRIBUTION, maxZoom: 19 }).addTo(instance);
        instance.on('click', (e) => choose(e.latlng.lat, e.latlng.lng));
        map.current = instance;

        // The map is usually built inside a modal that is still animating, so
        // it measures a box that is not its final size and renders one grey
        // tile. Leaflet re-measures on demand.
        const settle = setTimeout(() => instance.invalidateSize(), 120);

        return () => {
            clearTimeout(settle);
            instance.remove();
            map.current = null;
            marker.current = null;
        };
    }, []);

    // The pin follows the value, wherever the value came from — a map click, a
    // search result, or the two number inputs below the map.
    useEffect(() => {
        if (!map.current) return;

        if (!hasPoint) {
            if (marker.current) {
                marker.current.remove();
                marker.current = null;
            }
            return;
        }

        const point = [latitude, longitude];

        if (marker.current) {
            marker.current.setLatLng(point);
        } else {
            marker.current = L.marker(point, { icon: PIN, draggable: !disabled }).addTo(map.current);
            marker.current.on('dragend', () => {
                const moved = marker.current.getLatLng();
                choose(moved.lat, moved.lng);
            });
        }

        map.current.panTo(point);
    }, [latitude, longitude, hasPoint, disabled]);

    async function runSearch() {
        const term = query.trim();
        if (!term) return;

        setSearching(true);
        setNotice('');
        try {
            const found = await searchPlaces(term);
            setResults(found);
            if (found.length === 0) setNotice('Nothing found for that. Try a town or a landmark, or click the map.');
        } catch (err) {
            setResults([]);
            setNotice(err.message);
        } finally {
            setSearching(false);
        }
    }

    function locateMe() {
        if (!navigator.geolocation) {
            setNotice('This browser will not share a location. Click the map instead.');
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => choose(position.coords.latitude, position.coords.longitude),
            () => setNotice('Location permission was refused. Click the map instead.')
        );
    }

    return (
        <div>
            <div style={{ display: 'flex', gap: 6, marginBottom: 6 }}>
                <input
                    type="text"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    // Enter inside a form submits it. Here it has to search
                    // instead, or looking up an address saves the warehouse.
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            runSearch();
                        }
                    }}
                    placeholder="Search a place — Hidd Industrial Area…"
                    aria-label="Search for a place"
                    disabled={disabled}
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <button type="button" onClick={runSearch} disabled={disabled || searching} className="btn-secondary btn-sm" style={{ flexShrink: 0 }}>
                    {searching ? 'Searching…' : 'Search'}
                </button>
                <button type="button" onClick={locateMe} disabled={disabled} className="btn-secondary btn-sm" style={{ flexShrink: 0 }} title="Use my current location">
                    Locate me
                </button>
            </div>

            {results.length > 0 && (
                <ul style={{ listStyle: 'none', margin: '0 0 6px', padding: 0, border: '1px solid #e2e8f0', borderRadius: 8, maxHeight: 132, overflowY: 'auto' }}>
                    {results.map((result) => (
                        <li key={result.id}>
                            <button
                                type="button"
                                onClick={() => {
                                    choose(result.latitude, result.longitude, result.label);
                                    setResults([]);
                                    setQuery('');
                                }}
                                style={{
                                    display: 'block', width: '100%', textAlign: 'left', padding: '7px 10px',
                                    fontSize: 12.5, color: '#334155', background: 'transparent', border: 'none', cursor: 'pointer',
                                }}
                            >
                                {result.label}
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <div
                ref={container}
                data-testid="warehouse-map"
                role="application"
                aria-label="Warehouse location map"
                style={{ height: 260, borderRadius: 8, border: '1px solid #e2e8f0', overflow: 'hidden', zIndex: 0 }}
            />

            <p style={{ fontSize: 11.5, color: '#94a3b8', margin: '5px 0 0' }}>
                {hasPoint
                    ? `Pin at ${latitude.toFixed(5)}, ${longitude.toFixed(5)} — click the map or drag the pin to move it.`
                    : 'Click the map to drop a pin, or search for a place above.'}
            </p>

            {notice && <p style={{ fontSize: 12, color: '#b45309', margin: '4px 0 0' }}>{notice}</p>}

            {/* The map needs tiles from the internet, and the browser reaching
                this app over the LAN may not have any. Typing a pair of
                coordinates straight in is the fallback that keeps the form
                usable when the map is a grey box — and the only way to clear a
                pin once one is down. */}
            <div style={{ display: 'flex', gap: 6, alignItems: 'flex-end', marginTop: 6 }}>
                <label style={{ fontSize: 11.5, color: '#64748b', flex: 1 }}>
                    Latitude
                    <input
                        type="number" step="any" inputMode="decimal"
                        value={Number.isFinite(latitude) ? latitude : ''}
                        onChange={(e) => onPick({ latitude: e.target.value === '' ? null : Number(e.target.value), longitude })}
                        disabled={disabled}
                        className="border border-gray-300 rounded-md px-2 py-1 text-sm w-full"
                    />
                </label>
                <label style={{ fontSize: 11.5, color: '#64748b', flex: 1 }}>
                    Longitude
                    <input
                        type="number" step="any" inputMode="decimal"
                        value={Number.isFinite(longitude) ? longitude : ''}
                        onChange={(e) => onPick({ latitude, longitude: e.target.value === '' ? null : Number(e.target.value) })}
                        disabled={disabled}
                        className="border border-gray-300 rounded-md px-2 py-1 text-sm w-full"
                    />
                </label>
                <button
                    type="button"
                    onClick={() => onPick({ latitude: null, longitude: null })}
                    disabled={disabled || !hasPoint}
                    className="btn-secondary btn-sm"
                    style={{ flexShrink: 0 }}
                >
                    Clear pin
                </button>
            </div>
        </div>
    );
}
