import { createContext, useContext, useEffect, useMemo, useState } from 'react';

const PageTitleContext = createContext({ title: null, setTitle: () => {} });

/**
 * React has no `@section('title')`, so a page that wants a more specific topbar
 * title than its route name (the Blade detail pages did — "Pipeline —
 * MPR-0003") publishes one here. TopBar prefers it and falls back to the
 * route-derived title.
 */
export function PageTitleProvider({ children }) {
    const [title, setTitle] = useState(null);
    const value = useMemo(() => ({ title, setTitle }), [title]);

    return <PageTitleContext.Provider value={value}>{children}</PageTitleContext.Provider>;
}

/** Read the override (TopBar). */
export function usePageTitleOverride() {
    return useContext(PageTitleContext).title;
}

/** Publish an override for as long as the calling page is mounted. */
export function useSetPageTitle(title) {
    const { setTitle } = useContext(PageTitleContext);

    useEffect(() => {
        setTitle(title ?? null);

        return () => setTitle(null);
    }, [title, setTitle]);
}
