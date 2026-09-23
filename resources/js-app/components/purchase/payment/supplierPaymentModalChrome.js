// The supplier payment modal's identities. Violet→fuchsia for recording
// money going out, and the shared amber→orange for editing one.
export const CREATE_CHROME = {
    title: 'Record Supplier Payment',
    subtitle: 'Settle part or all of a supplier invoice',
    gradient: 'linear-gradient(135deg,#6d28d9 0%,#c026d3 100%)',
    accent: '#a855f7',
    submitLabel: 'Record Payment',
    icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
};

export const EDIT_CHROME = {
    title: 'Edit Payment',
    gradient: 'linear-gradient(135deg,#b45309 0%,#ea580c 100%)',
    accent: '#f59e0b',
    submitLabel: 'Save Changes',
    icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
};
