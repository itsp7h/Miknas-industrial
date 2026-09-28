import L from 'leaflet';

/**
 * The map pin every picker drops. Leaflet's default marker is a PNG it
 * resolves by URL, which a bundler rewrites and Leaflet then cannot find —
 * the classic broken-image pin. A divIcon sidesteps the asset pipeline
 * entirely, and matches how every other icon in this app is drawn.
 */
export const PIN = L.divIcon({
    className: '',
    html: `<svg width="30" height="30" viewBox="0 0 24 24" fill="#dc2626" stroke="#fff" stroke-width="1.5">
        <path d="M12 2C8.1 2 5 5.1 5 9c0 5.2 7 13 7 13s7-7.8 7-13c0-3.9-3.1-7-7-7z"/>
        <circle cx="12" cy="9" r="2.5" fill="#fff" stroke="none"/>
    </svg>`,
    iconSize: [30, 30],
    iconAnchor: [15, 29],
});
