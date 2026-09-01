/** The two icons the Blade page used: a building for companies, people for departments. */
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

export function PeopleIcon({ size = 18, colour = '#06b6d4' }) {
    return (
        <svg width={size} height={size} fill="none" stroke={colour} viewBox="0 0 24 24" style={{ flexShrink: 0 }}>
            <path
                strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
            />
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
