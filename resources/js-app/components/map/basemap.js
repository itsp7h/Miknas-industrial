// The map every map picker draws — the warehouse picker and the project
// location modal — so both show the same map rather than two near-misses.
//
// OpenFreeMap: free with no limits, no key and no registration, commercial
// use included (https://openfreemap.org). Its tiles are vector, drawn in the
// browser by MapLibre, which is what lets the labels be English: every label
// is rewritten to a place's English name, falling back to its own. The
// raster tiles OpenStreetMap serves are pictures, labelled in the local
// script — Arabic, in Bahrain — with no way to change it.
//
// MapLibre is large, so it is imported only when a map is actually drawn.
// Where it cannot run (no WebGL, or OpenFreeMap unreachable) the map falls
// back to those OpenStreetMap tiles: a map in the local script beats none.

import L from 'leaflet';

export const STYLE_URL = 'https://tiles.openfreemap.org/styles/liberty';
const ATTRIBUTION = '<a href="https://openfreemap.org" target="_blank">OpenFreeMap</a> '
    + '&copy; <a href="https://www.openmaptiles.org/" target="_blank">OpenMapTiles</a> '
    + 'Data from <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a>';

const OSM_TILES = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const OSM_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

// OpenMapTiles carries a name per language. English where there is one; then
// `name:latin`, the name in Latin script — readable where no one has entered
// an English name; `name`, the local one, only when there is neither. The
// style as served prints Latin and local side by side ("Manama المنامة").
export const ENGLISH_NAME = ['coalesce', ['get', 'name:en'], ['get', 'name_en'], ['get', 'name:latin'], ['get', 'name']];

/**
 * A copy of `style` with every place label in English. Only labels built from
 * a name are touched — a road shield's number (`ref`) or a house number stays.
 */
export function withEnglishLabels(style) {
    return {
        ...style,
        layers: (style.layers ?? []).map((layer) => {
            const field = layer.layout?.['text-field'];
            if (layer.type !== 'symbol' || !field || !/name/.test(JSON.stringify(field))) return layer;

            return { ...layer, layout: { ...layer.layout, 'text-field': ENGLISH_NAME } };
        }),
    };
}

const webgl = () => typeof WebGLRenderingContext !== 'undefined'
    && !!document.createElement('canvas').getContext('webgl');

function fallback(map) {
    L.tileLayer(OSM_TILES, { attribution: OSM_ATTRIBUTION, maxZoom: 19 }).addTo(map);
}

/**
 * Draws the basemap onto a Leaflet `map`. Returns a function that cancels a
 * draw still loading, for a map torn down before MapLibre arrived.
 */
export function addBasemap(map) {
    let cancelled = false;

    (async () => {
        try {
            if (!webgl()) throw new Error('No WebGL');

            const [{ maplibreGL }, style] = await Promise.all([
                import('@maplibre/maplibre-gl-leaflet'),
                fetch(STYLE_URL).then((response) => {
                    if (!response.ok) throw new Error(`OpenFreeMap answered ${response.status}`);

                    return response.json();
                }),
                import('maplibre-gl/dist/maplibre-gl.css'),
            ]);
            if (cancelled) return;

            maplibreGL({ style: withEnglishLabels(style), attribution: ATTRIBUTION }).addTo(map);
        } catch {
            if (!cancelled) fallback(map);
        }
    })();

    return () => { cancelled = true; };
}
