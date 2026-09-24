/**
 * Where Echo connects, read when the page loads rather than when it was built.
 *
 * The app shell renders the address as `data-reverb` on #react-app, from
 * config/reverb.php's `client` block. That is what lets one build serve both
 * boxes: staging dials staging-steelerp.p7h.me, production steelerp.p7h.me,
 * and neither value is compiled into the bundle.
 *
 * `import.meta.env.VITE_REVERB_*` is the fallback, for `npm run dev` against a
 * page that does not carry the attribute. An attribute that is missing,
 * unparseable, or has a field left blank falls through to it field by field.
 */
const blank = (value) => value === undefined || value === null || value === '';
const pick = (value, fallback) => (blank(value) ? fallback : value);

export function reverbConfig(
    container = typeof document === 'undefined' ? null : document.getElementById('react-app'),
    env = import.meta.env,
) {
    let runtime = {};
    try {
        runtime = JSON.parse(container?.dataset?.reverb || '{}') || {};
    } catch {
        runtime = {};
    }

    const scheme = pick(runtime.scheme, pick(env.VITE_REVERB_SCHEME, 'https'));
    const port = Number(pick(runtime.port, pick(env.VITE_REVERB_PORT, 443)));

    return {
        key: pick(runtime.key, env.VITE_REVERB_APP_KEY),
        host: pick(runtime.host, env.VITE_REVERB_HOST),
        port,
        forceTLS: scheme === 'https',
    };
}
