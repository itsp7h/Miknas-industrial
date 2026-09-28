import { describe, it, expect, vi, afterEach } from 'vitest';
import L from 'leaflet';
import { addBasemap, withEnglishLabels, ENGLISH_NAME } from './basemap';

afterEach(() => vi.unstubAllGlobals());

describe('withEnglishLabels', () => {
    const style = {
        version: 8,
        layers: [
            { id: 'water', type: 'fill', paint: {} },
            { id: 'place_city', type: 'symbol', layout: { 'text-field': '{name}', 'text-size': 14 } },
            { id: 'road_label', type: 'symbol', layout: { 'text-field': ['coalesce', ['get', 'name:latin'], ['get', 'name']] } },
            { id: 'road_shield', type: 'symbol', layout: { 'text-field': '{ref}' } },
            { id: 'housenumber', type: 'symbol', layout: { 'text-field': '{housenumber}' } },
            { id: 'poi_icon', type: 'symbol', layout: { 'icon-image': 'shop' } },
        ],
    };

    it('turns every name label English, falling back to the local name', () => {
        const layers = Object.fromEntries(withEnglishLabels(style).layers.map((l) => [l.id, l]));

        expect(layers.place_city.layout['text-field']).toEqual(ENGLISH_NAME);
        expect(layers.road_label.layout['text-field']).toEqual(ENGLISH_NAME);
        expect(ENGLISH_NAME).toEqual(['coalesce', ['get', 'name:en'], ['get', 'name_en'], ['get', 'name:latin'], ['get', 'name']]);
        // The rest of the layout survives.
        expect(layers.place_city.layout['text-size']).toBe(14);
    });

    // The shape OpenFreeMap's Liberty style actually serves (fetched 2026-09-28).
    it("replaces Liberty's Latin-plus-local labels", () => {
        const liberty = { layers: [{ id: 'label_city', type: 'symbol', layout: { 'text-field': ['case', ['has', 'name:nonlatin'], ['concat', ['get', 'name:latin'], '\n', ['get', 'name:nonlatin']], ['coalesce', ['get', 'name_en'], ['get', 'name']]] } }, { id: 'road_shield', type: 'symbol', layout: { 'text-field': ['to-string', ['get', 'ref']] } }] };
        const [city, shield] = withEnglishLabels(liberty).layers;

        expect(city.layout['text-field']).toEqual(ENGLISH_NAME);
        expect(shield.layout['text-field']).toEqual(['to-string', ['get', 'ref']]);
    });

    it('leaves road numbers, house numbers and icons alone', () => {
        const layers = Object.fromEntries(withEnglishLabels(style).layers.map((l) => [l.id, l]));

        expect(layers.road_shield.layout['text-field']).toBe('{ref}');
        expect(layers.housenumber.layout['text-field']).toBe('{housenumber}');
        expect(layers.poi_icon).toBe(style.layers[5]);
        expect(layers.water).toBe(style.layers[0]);
    });

    it('does not change the style it was given', () => {
        withEnglishLabels(style);
        expect(style.layers[1].layout['text-field']).toBe('{name}');
    });
});

describe('addBasemap', () => {
    const tileLayers = (map) => {
        const found = [];
        map.eachLayer((layer) => { if (layer instanceof L.TileLayer) found.push(layer); });

        return found;
    };

    // jsdom has no WebGL, like a browser that cannot run MapLibre.
    it('falls back to OpenStreetMap tiles where MapLibre cannot run, without fetching the style', async () => {
        const fetch = vi.fn();
        vi.stubGlobal('fetch', fetch);
        const map = L.map(document.createElement('div')).setView([26, 50.5], 10);

        addBasemap(map);

        await vi.waitFor(() => expect(tileLayers(map)).toHaveLength(1));
        expect(tileLayers(map)[0]._url).toBe('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');
        expect(fetch).not.toHaveBeenCalled();
        map.remove();
    });

    it('draws nothing onto a map torn down while OpenFreeMap was still loading', async () => {
        // Pretend MapLibre could run, and hold the style request open.
        vi.stubGlobal('WebGLRenderingContext', function WebGLRenderingContext() {});
        vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue({});
        let fail;
        vi.stubGlobal('fetch', vi.fn(() => new Promise((_, reject) => { fail = reject; })));
        const map = L.map(document.createElement('div')).setView([26, 50.5], 10);

        const cancel = addBasemap(map);
        await vi.waitFor(() => expect(fetch).toHaveBeenCalledWith('https://tiles.openfreemap.org/styles/liberty'));
        cancel();
        fail(new Error('offline'));
        await new Promise((resolve) => setTimeout(resolve, 0));

        // A live map would have fallen back; a cancelled one draws nothing.
        expect(tileLayers(map)).toHaveLength(0);
        map.remove();
    });
});
