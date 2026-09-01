import { useEffect, useRef, useState } from 'react';
import useLeaflet from './useLeaflet';
import { useToast } from '../../ui/Toast';

const DEFAULT_CENTRE = [25.2048, 55.2708];
const DEFAULT_ZOOM = 11;

/**
 * The Blade location modal: form on the left, a Leaflet map on the right. Click
 * the map to drop the pin, drag it to adjust, or type an address and search it
 * against Nominatim — all as before, with the map wired up through refs instead
 * of module-level globals so two modals can never share one map instance.
 */
export default function LocationMapModal({ open, project, location, onClose, onSave }) {
    const isEdit = !!location;
    const mapNode = useRef(null);
    const map = useRef(null);
    const marker = useRef(null);
    const { ready, failed } = useLeaflet(open);
    const { showToast } = useToast();

    const [name, setName] = useState('');
    const [address, setAddress] = useState('');
    const [lat, setLat] = useState('');
    const [lng, setLng] = useState('');
    const [isActive, setIsActive] = useState(true);
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    // Reset whenever the modal is opened for a different location.
    useEffect(() => {
        if (!open) return;
        setName(location?.name ?? '');
        setAddress(location?.address ?? '');
        setLat(location?.latitude != null ? String(location.latitude) : '');
        setLng(location?.longitude != null ? String(location.longitude) : '');
        setIsActive(location ? location.is_active : true);
        setError('');
    }, [open, location]);

    function placePin(nextLat, nextLng) {
        const L = window.L;
        if (!L || !map.current) return;

        const latlng = L.latLng(nextLat, nextLng);

        if (marker.current) {
            marker.current.setLatLng(latlng);
        } else {
            marker.current = L.marker(latlng, { draggable: true }).addTo(map.current);
            marker.current.on('dragend', (event) => {
                const position = event.target.getLatLng();
                setLat(position.lat.toFixed(7));
                setLng(position.lng.toFixed(7));
            });
        }

        map.current.setView(latlng, Math.max(map.current.getZoom(), 14));
    }

    // Build the map once Leaflet is in and the node exists; tear it down on close
    // so reopening starts clean.
    useEffect(() => {
        if (!open || !ready || !mapNode.current || map.current) return;

        const L = window.L;
        map.current = L.map(mapNode.current).setView(DEFAULT_CENTRE, DEFAULT_ZOOM);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        }).addTo(map.current);

        map.current.on('click', (event) => {
            setLat(event.latlng.lat.toFixed(7));
            setLng(event.latlng.lng.toFixed(7));
            placePin(event.latlng.lat, event.latlng.lng);
        });

        if (location?.latitude != null && location?.longitude != null) {
            placePin(location.latitude, location.longitude);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, ready, location]);

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

    async function geocode() {
        const query = address.trim();
        if (!query) {
            showToast('Enter an address to search.', 'warn');

            return;
        }
        try {
            const response = await fetch(
                `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`,
                { headers: { 'Accept-Language': 'en' } }
            );
            const results = await response.json();
            if (results?.length) {
                const found = [parseFloat(results[0].lat), parseFloat(results[0].lon)];
                setLat(found[0].toFixed(7));
                setLng(found[1].toFixed(7));
                placePin(found[0], found[1]);
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
                address: address.trim() || null,
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
                            <label htmlFor="location-address" className="form-label">Address</label>
                            <div style={{ display: 'flex', gap: 6, alignItems: 'stretch' }}>
                                <input
                                    id="location-address" type="text" className="form-input"
                                    style={{ flex: 1, fontSize: 13 }} placeholder="Street, City, Country"
                                    value={address}
                                    onChange={(e) => setAddress(e.target.value)}
                                    onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); geocode(); } }}
                                />
                                <button
                                    type="button" onClick={geocode} title="Search address on map" aria-label="Search address on map"
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
                                • Click anywhere on the map to place the pin<br />
                                • Drag the pin to fine-tune position<br />
                                • Type an address and press <strong>↵</strong> or click the search button to find it
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
                        {!ready && (
                            <div style={{
                                position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center',
                                background: '#f8fafc', color: '#94a3b8', fontSize: 13, textAlign: 'center', padding: 24,
                            }}>
                                {failed
                                    ? 'The map could not be loaded. Type the latitude and longitude instead.'
                                    : 'Loading map…'}
                            </div>
                        )}
                        {ready && (
                            <div style={{
                                position: 'absolute', bottom: 10, left: '50%', transform: 'translateX(-50%)',
                                background: 'rgba(15,23,42,0.72)', color: '#fff', fontSize: 11, padding: '4px 12px',
                                borderRadius: 20, pointerEvents: 'none', whiteSpace: 'nowrap', zIndex: 1000,
                            }}>
                                Click map to place pin • Drag pin to adjust
                            </div>
                        )}
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
