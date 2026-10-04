import { createContext, useContext, useMemo } from 'react';

/**
 * What the signed-in person may do, reachable from anywhere below the shell.
 *
 * The permission list already came down for the sidebar and RequirePermission,
 * but it stopped at the shell: a page had no way to ask, so every list page
 * offered its "New …" button to everyone and the API refused the first fetch.
 * From a view-only account that read as a broken screen rather than a closed
 * door — the very thing RequirePermission exists to prevent one level up.
 *
 * `can` answers true for an Admin whatever the list holds, mirroring the
 * Gate::before in AppServiceProvider. The API checks again regardless: this
 * decides what to *offer*, never what to allow.
 */
const AccessContext = createContext({ isAdmin: false, permissions: [], userId: null, can: () => false });

// `userId` is for rules about *whose* record it is — an LPO's approver must not
// be the person who prepared it.
export function AccessProvider({ isAdmin = false, permissions = [], userId = null, children }) {
    const value = useMemo(() => ({
        isAdmin,
        permissions,
        userId: userId == null ? null : Number(userId),
        can: (permission) => isAdmin || (!!permission && permissions.includes(permission)),
    }), [isAdmin, permissions, userId]);

    return <AccessContext.Provider value={value}>{children}</AccessContext.Provider>;
}

export function useAccess() {
    return useContext(AccessContext);
}
