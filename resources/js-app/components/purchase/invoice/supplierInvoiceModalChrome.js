// The supplier invoice modal's identities. Rose→pink for a new invoice — a
// bill arriving is its own event — and the shared amber→orange for editing.
export const CREATE_CHROME = {
    title: 'New Supplier Invoice',
    subtitle: 'Record a bill received from a supplier',
    gradient: 'linear-gradient(135deg,#be123c 0%,#db2777 100%)',
    accent: '#f43f5e',
    submitLabel: 'Save Invoice',
    icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
};

export const EDIT_CHROME = {
    title: 'Edit Supplier Invoice',
    gradient: 'linear-gradient(135deg,#b45309 0%,#ea580c 100%)',
    accent: '#f59e0b',
    submitLabel: 'Save Changes',
    icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
};
