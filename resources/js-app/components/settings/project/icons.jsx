/** The icons the Blade projects page used. */
export function FolderIcon({ size = 16, colour = '#2563eb' }) {
    return (
        <svg width={size} height={size} fill="none" stroke={colour} viewBox="0 0 24 24" style={{ flexShrink: 0 }}>
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
        </svg>
    );
}

export function PinIcon({ size = 13, colour = '#22c55e', style }) {
    return (
        <svg width={size} height={size} fill="none" stroke={colour} viewBox="0 0 24 24" style={{ flexShrink: 0, ...style }}>
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
    );
}

export function CheckCircleIcon({ size = 18, colour = '#22c55e' }) {
    return (
        <svg width={size} height={size} fill="none" stroke={colour} viewBox="0 0 24 24" style={{ flexShrink: 0 }}>
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    );
}

export function BuildingIcon({ size = 18, colour = '#6366f1' }) {
    return (
        <svg width={size} height={size} fill="none" stroke={colour} viewBox="0 0 24 24" style={{ flexShrink: 0 }}>
            <path
                strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
            />
        </svg>
    );
}

export function DownloadIcon() {
    return (
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
        </svg>
    );
}

export function UploadIcon() {
    return (
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l4-4m0 0l4 4m-4-4v12" />
        </svg>
    );
}

export function PlusIcon() {
    return (
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
        </svg>
    );
}
