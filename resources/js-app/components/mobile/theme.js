// The mobile design's tokens (SteelERP-Mobile-Designs-V2). Every mobile page
// reads its colours from here rather than spelling hex values out, so a zone
// can be retuned in one place.
//
// The app is split into four colour zones. A tab's root page (Home, Pipeline,
// Purchasing, Inventory, More) gets the zone's gradient hero; a page opened
// from one gets the solid colour, under a back bar of the same colour.

export const ZONES = {
    home: {
        solid: '#1D4ED8',
        gradient: 'linear-gradient(160deg, #1D4ED8, #1E3A8A)',
        label: '#1D4ED8',
        soft: '#DBEAFE',
        softText: '#1E40AF',
    },
    purchase: {
        solid: '#4338CA',
        gradient: 'linear-gradient(160deg, #4338CA, #312E81)',
        label: '#4338CA',
        soft: '#E0E7FF',
        softText: '#3730A3',
    },
    inventory: {
        solid: '#047857',
        gradient: 'linear-gradient(160deg, #047857, #064E3B)',
        label: '#15803D',
        soft: '#DCFCE7',
        softText: '#166534',
    },
    system: {
        solid: '#6D28D9',
        gradient: 'linear-gradient(160deg, #6D28D9, #4C1D95)',
        label: '#6D28D9',
        soft: '#EDE9FE',
        softText: '#5B21B6',
    },
};

export const C = {
    bg: '#F1F5F9',
    card: '#FFFFFF',
    text: '#0F172A',
    text2: '#334155',
    muted: '#475569',
    faint: '#64748B',
    fainter: '#94A3B8',
    line: '#E2E8F0',
    hairline: '#F1F5F9',
    accent: '#2563EB',
    danger: '#B91C1C',
    dangerSolid: '#DC2626',
};

export const MONO = "'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace";

// Pill colours for the statuses every list shows, keyed by tone.
export const TONES = {
    blue: { bg: '#DBEAFE', fg: '#1E40AF' },
    green: { bg: '#DCFCE7', fg: '#166534' },
    amber: { bg: '#FEF3C7', fg: '#92400E' },
    red: { bg: '#FEE2E2', fg: '#991B1B' },
    violet: { bg: '#EDE9FE', fg: '#5B21B6' },
    indigo: { bg: '#E0E7FF', fg: '#3730A3' },
    teal: { bg: '#CCFBF1', fg: '#115E59' },
    pink: { bg: '#FCE7F3', fg: '#9D174D' },
    rose: { bg: '#FFE4E6', fg: '#9F1239' },
    slate: { bg: '#F1F5F9', fg: '#334155' },
};

// The avatar palette the design cycles through on directory rows.
const AVATAR_TONES = ['indigo', 'amber', 'green', 'pink', 'teal', 'violet'];

export function avatarTone(seed) {
    const text = String(seed ?? '');
    let hash = 0;
    for (let i = 0; i < text.length; i += 1) hash = (hash * 31 + text.charCodeAt(i)) % 9973;

    return TONES[AVATAR_TONES[hash % AVATAR_TONES.length]];
}

export function initials(name) {
    return String(name ?? '')
        .replace(/[^A-Za-z ]/g, '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0].toUpperCase())
        .join('') || '?';
}
