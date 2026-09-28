import { useEffect, useRef, useState } from 'react';
import L from 'leaflet';
import { useToast } from '../../ui/Toast';
import { addBasemap } from '../../map/basemap';
import { PIN } from '../../map/pin';
import { addressOfPoint, findAddress, searchPlaces } from '../../map/geocode';

const NO_PARTS = { road: '', block: '', city: '', country: '' };
const PART_FIELDS = [
    ['road', 'Road / Street', 'e.g. Road 2734'],
    ['block', 'Block', 'e.g. 338'],
    ['city', 'City', 'e.g. Manama'],
    ['country', 'Country', 'e.g. Bahrain'],
];

const DEFAULT_CENTRE = [25.2048, 55.2708];
const DEFAULT_ZOOM = 11;

/**
 * The Blade location modal: form on the left, a Leaflet map on the right. Click
 * the map to drop the pin, drag it to adjust, or type an address and search it
 * against Nominatim — with the map wired up through refs instead of
 * module-level globals so two modals can never share one map instance. It is
 * the bundled Leaflet the warehouse picker uses, drawing the same English
 * OpenFreeMap basemap (map/basemap.js); it used to load a second copy of
 * Leaflet from a CDN, which the MapLibre layer cannot attach to.
 *
 * Wherever the pin lands — a click, a drag, a search — the road, block, city
 * and country under it are looked up (Nominatim, in English) and fill
 * their fields. It works the other way too: type the city into "Search the
 * map", fill in the road or block, and the search button takes the pin
 * to that address — leaving what was typed as typed. Those four *are* the address: the one-line
 * `address` is saved as them joined, and the box above them is a search box
 * that finds a place and is not saved. A location from before the four
 * fields keeps its typed address until they are filled.
 *
 * Typed coordinates move the pin but look nothing up: Nominatim allows one
 * request a second, not one a keystroke.
 */
export default function LocationMapModal({ open, project, location, onClose, onSave }) {
    const isEdit = !!location;
    const mapNode = useRef(null);
    const map = useRef(null);
    const marker = useRef(null);
    const { showToast } = useToast();

    const [name, setName] = useState('');
    const [search, setSearch] = useState('');
    const [parts, setParts] = useState(NO_PARTS);
    const [lookingUp, setLookingUp] = useState('');
    // Each lookup's number; an answer for a pin since moved is dropped.
    const lookup = useRef(0);
    const [lat, setLat] = useState('');
    const [lng, setLng] = useState('');
    const [isActive, setIsActive] = useState(true);
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    // Seeded when the modal opens, for that location — keyed on its id, not on
    // the object, so a refresh of the list underneath (a new object for the
    // same location) never wipes what is being typed.
    useEffect(() => {
        if (!open) return;
        setName(location?.name ?? '');
        const saved = {
            road: location?.road ?? '', block: location?.block ?? '',
            city: location?.city ?? '', country: location?.country ?? '',
        };
        setParts(saved);
        // An address typed before the four fields existed waits in the search
        // box, one click from filling them.
        setSearch(Object.values(saved).some(Boolean) ? '' : (location?.address ?? ''));
        lookup.current += 1;
        setLookingUp('');
        setLat(location?.latitude != null ? String(location.latitude) : '');
        setLng(location?.longitude != null ? String(location.longitude) : '');
        setIsActive(location ? location.is_active : true);
        setError('');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, location?.id]);

    /** The address under a pin the user just placed fills the four fields. */
    async function fillAddressFrom(nextLat, nextLng) {
        const ticket = ++lookup.current;
        setLookingUp('Looking up the address under the pin…');
        const found = await addressOfPoint(nextLat, nextLng);
        if (ticket !== lookup.current) return;
        setLookingUp('');
        if (!found) return;
        setParts({ road: found.road, block: found.block, city: found.city, country: found.country });
    }

    /** Moves the pin to the address `target` names; the fields keep what was typed. */
    async function movePinToParts(target) {
        const ticket = ++lookup.current;
        setLookingUp('Finding that address on the map…');
        const found = await findAddress(target);
        if (ticket !== lookup.current) return;
        if (!found) {
            setLookingUp('');
            showToast('The map could not find that address — drop the pin by hand.', 'warn');

            return;
        }
        setLookingUp('');
        setLat(found.latitude.toFixed(7));
        setLng(found.longitude.toFixed(7));
        placePin(found.latitude, found.longitude);
        // Close is not exact: say so, rather than leave a pin that looks sure.
        if (found.matched !== 'road' && target.road?.trim()) {
            const fallback = found.matched === 'block' ? `block ${target.block.trim()}` : target.city.trim();
            showToast(`${target.road.trim()} is not on the map — the pin is at ${fallback}. Drag it to the exact spot.`, 'warn');
        }
    }

    /** The one-line address: the four parts joined, else what was saved before. */
    function addressLine() {
        const joined = PART_FIELDS
            .map(([part]) => (part === 'block' && parts.block.trim() ? `Block ${parts.block.trim()}` : parts[part].trim()))
            .filter(Boolean).join(', ');

        return joined || location?.address || null;
    }

    function placePin(nextLat, nextLng) {
        if (!map.current) return;

        const latlng = L.latLng(nextLat, nextLng);

        if (marker.current) {
            marker.current.setLatLng(latlng);
        } else {
            marker.current = L.marker(latlng, { icon: PIN, draggable: true }).addTo(map.current);
            marker.current.on('dragend', (event) => {
                const position = event.target.getLatLng();
                setLat(position.lat.toFixed(7));
                setLng(position.lng.toFixed(7));
                fillAddressFrom(position.lat, position.lng);
            });
        }

        map.current.setView(latlng, Math.max(map.current.getZoom(), 14));
    }

    // Build the map when the modal opens; tear it down on close so reopening
    // starts clean.
    useEffect(() => {
        if (!open || !mapNode.current || map.current) return undefined;

        map.current = L.map(mapNode.current).setView(DEFAULT_CENTRE, DEFAULT_ZOOM);
        const cancelBasemap = addBasemap(map.current);
        // The modal is still laying out when the map measures its box, which
        // leaves grey tiles; Leaflet re-measures on demand.
        const settle = setTimeout(() => map.current?.invalidateSize(), 120);

        map.current.on('click', (event) => {
            setLat(event.latlng.lat.toFixed(7));
            setLng(event.latlng.lng.toFixed(7));
            placePin(event.latlng.lat, event.latlng.lng);
            fillAddressFrom(event.latlng.lat, event.latlng.lng);
        });

        if (location?.latitude != null && location?.longitude != null) {
            placePin(location.latitude, location.longitude);
        }

        return () => {
            clearTimeout(settle);
            cancelBasemap();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, location?.id]);

    useEffect(() => {
        if (open) return;
        if (map.current) {
            map.current.remove();
            map.current = null;
            marker.current = null;
        }
    }, [open]);

    /** Typed coordinates move the pin, as the Blade page's oninput did. */
    function onCoordinateInput(nextLat, nextLng) {
        const parsedLat = parseFloat(nextLat);
        const parsedLng = parseFloat(nextLng);
        const valid = !Number.isNaN(parsedLat) && !Number.isNaN(parsedLng)
            && parsedLat >= -90 && parsedLat <= 90 && parsedLng >= -180 && parsedLng <= 180;

        if (valid) placePin(parsedLat, parsedLng);
    }

    /**
     * The search button. With a road or block filled in, the search box
     * is the city and the button goes to the whole address. With only the
     * search box, it finds that place and the pin fills the fields.
     */
    async function geocode() {
        const query = search.trim();
        const street = ['road', 'block'].some((part) => parts[part].trim());

        if (street) {
            const target = { ...parts, city: query || parts.city };
            if (query) setParts((prev) => ({ ...prev, city: query }));
            await movePinToParts(target);

            return;
        }
        if (!query) {
            showToast('Type a city or a place, or fill in the address, to search.', 'warn');

            return;
        }
        try {
            const [first] = await searchPlaces(query);
            if (first) {
                setLat(first.latitude.toFixed(7));
                setLng(first.longitude.toFixed(7));
                placePin(first.latitude, first.longitude);
                fillAddressFrom(first.latitude, first.longitude);
            } else {
                showToast('Address not found — try a more specific query.', 'warn');
            }
        } catch {
            showToast('Geocoding failed. Check your connection.', 'error');
        }
    }

    async function save() {
        if (!name.trim()) {
            setError('Location name is required.');

            return;
        }
        setSaving(true);
        setError('');
        try {
            await onSave(project, location, {
                name: name.trim(),
                address: addressLine(),
                road: parts.road.trim() || null,
                block: parts.block.trim() || null,
                city: parts.city.trim() || null,
                country: parts.country.trim() || null,
                latitude: lat !== '' ? parseFloat(lat) : null,
                longitude: lng !== '' ? parseFloat(lng) : null,
                is_active: isActive,
            });
            onClose();
        } catch (err) {
            const errors = err?.errors;
            const key = errors ? Object.keys(errors)[0] : null;
            setError(key ? errors[key][0] : (err?.message || 'Something went wrong.'));
        } finally {
            setSaving(false);
        }
    }

    if (!open) return null;

    return (
        <div
            role="presentation"
            onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}
            style={{
                position: 'fixed', inset: 0, background: 'rgba(15,23,42,0.55)', zIndex: 9999,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
            }}
        >
            <div style={{
                background: '#fff', borderRadius: 16, width: 940, maxWidth: '96vw', maxHeight: '93vh',
                overflow: 'hidden', display: 'flex', flexDirection: 'column', boxShadow: '0 25px 60px rgba(0,0,0,0.25)',
            }}>
                <div style={{
                    display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                    padding: '1rem 1.5rem', borderBottom: '1px solid #e2e8f0', flexShrink: 0,
                }}>
                    <h2 style={{ fontSize: 16, fontWeight: 700, color: '#0f172a', margin: 0 }}>
                        {isEdit ? `Edit Location — ${location.name}` : `New Location — ${project?.name ?? ''}`}
                    </h2>
                    <button type="button" onClick={onClose} aria-label="Close" style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#94a3b8', padding: 4, lineHeight: 0 }}>
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div style={{ display: 'flex', flex: 1, overflow: 'hidden', minHeight: 0, flexWrap: 'wrap' }}>
                    <div style={{
                        width: 310, flexShrink: 0, padding: '1.25rem 1.25rem 1rem', overflowY: 'auto',
                        borderRight: '1px solid #e2e8f0', display: 'flex', flexDirection: 'column', gap: 14,
                    }}>
                        <div>
                            <label htmlFor="location-name" className="form-label">
                                Location Name <span className="text-red-500">*</span>
                            </label>
                            <input
                                id="location-name" type="text" className="form-input" autoFocus
                                style={{ width: '100%', fontSize: 13 }} placeholder="e.g. Main Warehouse"
                                value={name} onChange={(e) => setName(e.target.value)}
                            />
                        </div>

                        <div>
                            <label htmlFor="location-search" className="form-label">Search the map</label>
                            <div style={{ display: 'flex', gap: 6, alignItems: 'stretch' }}>
                                <input
                                    id="location-search" type="search" className="form-input"
                                    style={{ flex: 1, fontSize: 13 }} placeholder="City or place, e.g. Manama"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); geocode(); } }}
                                />
                                <button
                                    type="button" onClick={geocode} title="Find this place on the map" aria-label="Find this place on the map"
                                    style={{
                                        flexShrink: 0, padding: '0 11px', background: '#f1f5f9', border: '1px solid #e2e8f0',
                                        borderRadius: 6, cursor: 'pointer', color: '#475569', display: 'flex', alignItems: 'center',
                                    }}
                                >
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <fieldset style={{ border: 0, padding: 0, margin: 0 }}>
                            <legend className="form-label">Address</legend>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                                {PART_FIELDS.map(([part, label, placeholder]) => (
                                    <div key={part}>
                                        <label htmlFor={`location-${part}`} className="form-label">{label}</label>
                                        <input
                                            id={`location-${part}`} type="text" className="form-input"
                                            style={{ width: '100%', fontSize: 13 }} placeholder={placeholder}
                                            value={parts[part]}
                                            onChange={(e) => setParts((prev) => ({ ...prev, [part]: e.target.value }))}
                                            // Enter searches, as it does in the search box.
                                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); geocode(); } }}
                                        />
                                    </div>
                                ))}
                            </div>
                            <p style={{ fontSize: 11, color: '#94a3b8', margin: '4px 0 0' }} aria-live="polite">
                                {lookingUp || 'Filled from the pin — or fill it in, with the city above, and search.'}
                            </p>
                        </fieldset>

                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                            <div>
                                <label htmlFor="location-lat" className="form-label">Latitude</label>
                                <input
                                    id="location-lat" type="number" step="any" className="form-input font-mono"
                                    style={{ width: '100%', fontSize: 12 }} placeholder="25.2048"
                                    value={lat}
                                    onChange={(e) => { setLat(e.target.value); onCoordinateInput(e.target.value, lng); }}
                                />
                            </div>
                            <div>
                                <label htmlFor="location-lng" className="form-label">Longitude</label>
                                <input
                                    id="location-lng" type="number" step="any" className="form-input font-mono"
                                    style={{ width: '100%', fontSize: 12 }} placeholder="55.2708"
                                    value={lng}
                                    onChange={(e) => { setLng(e.target.value); onCoordinateInput(lat, e.target.value); }}
                                />
                            </div>
                        </div>

                        <div style={{ background: '#f0f9ff', border: '1px solid #bae6fd', borderRadius: 8, padding: '9px 11px' }}>
                            <p style={{ fontSize: 11, color: '#0369a1', margin: 0, lineHeight: 1.6 }}>
                                <strong>Map tips:</strong><br />
                                • Click anywhere on the map to place the pin — the address fills in<br />
                                • Or type the city above, fill in the road or block, and click search<br />
                                • Drag the pin to fine-tune position
                            </p>
                        </div>

                        <label style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer', fontSize: 13, color: '#374151' }}>
                            <input type="checkbox" style={{ width: 14, height: 14 }} checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />
                            Active
                        </label>

                        {error && <p style={{ color: '#dc2626', fontSize: 12, margin: 0 }}>{error}</p>}
                    </div>

                    <div style={{ flex: 1, position: 'relative', minWidth: 280, minHeight: 420 }}>
                        <div ref={mapNode} style={{ height: '100%', width: '100%', minHeight: 420 }} />
                        <div style={{
                            position: 'absolute', bottom: 10, left: '50%', transform: 'translateX(-50%)',
                            background: 'rgba(15,23,42,0.72)', color: '#fff', fontSize: 11, padding: '4px 12px',
                            borderRadius: 20, pointerEvents: 'none', whiteSpace: 'nowrap', zIndex: 1000,
                        }}>
                            Click map to place pin • Drag pin to adjust
                        </div>
                    </div>
                </div>

                <div style={{
                    display: 'flex', alignItems: 'center', justifyContent: 'flex-end', gap: 10,
                    padding: '0.875rem 1.5rem', borderTop: '1px solid #e2e8f0', flexShrink: 0,
                }}>
                    <button type="button" onClick={onClose} className="btn-secondary">Cancel</button>
                    <button type="button" onClick={save} className="btn-primary" disabled={saving}>
                        {saving ? 'Saving…' : 'Save Location'}
                    </button>
                </div>
            </div>
        </div>
    );
}
