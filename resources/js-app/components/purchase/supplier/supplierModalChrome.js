// The supplier modal's two identities, in the same shape as the MPR modal's
// (requestModalChrome.js) — a header gradient and an accent are how you tell
// at a glance which dialog you are looking at. Suppliers get their own hue so
// they do not read as a purchase request: slate→violet for a new record,
// amber→orange for editing one that already exists.
export const CREATE_CHROME = {
    title: 'New Supplier',
    subtitle: 'Add a company to the supplier directory',
    gradient: 'linear-gradient(135deg,#4338ca 0%,#7c3aed 100%)',
    accent: '#8b5cf6',
    submitLabel: 'Save Supplier',
    icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
};

export const EDIT_CHROME = {
    title: 'Edit Supplier',
    subtitle: 'Update this supplier’s details',
    gradient: 'linear-gradient(135deg,#b45309 0%,#ea580c 100%)',
    accent: '#f59e0b',
    submitLabel: 'Save Changes',
    icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
};
