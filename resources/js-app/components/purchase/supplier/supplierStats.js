/**
 * The four stat cards the Blade suppliers page showed, derived from the loaded
 * list rather than a separate endpoint: the index returns every supplier, and
 * `useLiveList` keeps it current, so the figures stay live for free.
 * Mirrors the old controller's $stats.
 */
export function deriveStats(suppliers) {
    const categories = new Set(
        suppliers.map((s) => s.category).filter((c) => c != null && String(c).trim() !== '')
    );

    return {
        total: suppliers.length,
        active: suppliers.filter((s) => s.is_active).length,
        inactive: suppliers.filter((s) => !s.is_active).length,
        categories: categories.size,
    };
}

/** Matches the Blade search box's fields: name, contact, email, phone, address. */
export function matchesQuery(supplier, query) {
    const q = query.trim().toLowerCase();
    if (!q) return true;

    return [
        supplier.supplier_code, supplier.name, supplier.category,
        supplier.contact_person, supplier.email, supplier.secondary_email,
        supplier.phone, supplier.phone2, supplier.whatsapp, supplier.address,
    ].some((field) => String(field ?? '').toLowerCase().includes(q));
}

export const hostOf = (url) => {
    if (!url) return null;
    try {
        return new URL(url.startsWith('http') ? url : `https://${url}`).host;
    } catch {
        return url;
    }
};

/** The Blade page treated only "y"/"yes" as credit terms. */
export const hasCredit = (supplier) =>
    ['y', 'yes'].includes(String(supplier.credit_terms ?? '').toLowerCase());
