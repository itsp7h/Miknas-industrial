/**
 * A stored pin, as a link anyone can open.
 *
 * Its own module rather than a helper inside MapPicker: a list only wants the
 * URL, and importing it from there would pull Leaflet — and a mocked Leaflet in
 * every list's test — along with it.
 */
export default function mapLink(latitude, longitude) {
    // `Number('')` is 0, and 0,0 is a real place in the Gulf of Guinea — so an
    // empty string has to be rejected before the numeric check, not by it.
    const pair = [latitude, longitude].map((value) => (
        value === null || value === undefined || value === '' ? NaN : Number(value)
    ));

    if (!pair.every(Number.isFinite)) return null;

    return `https://www.openstreetmap.org/?mlat=${latitude}&mlon=${longitude}#map=17/${latitude}/${longitude}`;
}
