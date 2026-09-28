// An uploaded image, made small enough to store: scaled to fit a box and
// re-encoded, so a 4 MB phone photo becomes a few kilobytes. Shared by the
// signature pad's upload and a company's logo and stamp.

/** The server refuses anything over 512 KB; stay well under it. */
const MAX_BYTES = 400 * 1024;

/** A data URL's decoded size, near enough. */
export const bytesOf = (dataUrl) => Math.ceil((dataUrl.length - dataUrl.indexOf(',') - 1) * 0.75);

/**
 * Scales a loaded <img> to fit `maxW`×`maxH` and re-encodes it. PNG keeps a
 * transparent background — a stamp or a signature over the page, not in a
 * white box; an image too busy for that falls back to JPEG on white.
 */
export function normaliseImage(image, { maxW, maxH }) {
    const scale = Math.min(1, maxW / image.width, maxH / image.height);
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(image.width * scale));
    canvas.height = Math.max(1, Math.round(image.height * scale));
    const context = canvas.getContext('2d');
    if (!context) return null;
    context.drawImage(image, 0, 0, canvas.width, canvas.height);

    const png = canvas.toDataURL('image/png');
    if (bytesOf(png) <= MAX_BYTES) return png;

    const flat = document.createElement('canvas');
    flat.width = canvas.width;
    flat.height = canvas.height;
    const flatContext = flat.getContext('2d');
    flatContext.fillStyle = '#fff';
    flatContext.fillRect(0, 0, flat.width, flat.height);
    flatContext.drawImage(canvas, 0, 0);

    return flat.toDataURL('image/jpeg', 0.85);
}

/**
 * A chosen file → a normalised data URL. Rejects with a message fit to show
 * the user: the wrong type, too large to read, or not an image at all.
 */
export function readImageFile(file, box) {
    return new Promise((resolve, reject) => {
        if (!['image/png', 'image/jpeg'].includes(file?.type)) {
            reject(new Error('Choose a PNG or JPEG image.'));

            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            reject(new Error('That image is over 5 MB. Choose a smaller one.'));

            return;
        }

        const unreadable = () => reject(new Error('That image could not be read.'));
        const reader = new FileReader();
        reader.onerror = unreadable;
        reader.onload = () => {
            const image = new Image();
            image.onerror = unreadable;
            image.onload = () => {
                const dataUrl = normaliseImage(image, box);
                if (dataUrl) resolve(dataUrl);
                else unreadable();
            };
            image.src = reader.result;
        };
        reader.readAsDataURL(file);
    });
}
