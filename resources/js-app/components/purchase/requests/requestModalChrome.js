// The two modals' identities, kept where both can see them. Blade gave the
// create form a blue→indigo header with an indigo accent and the edit form a
// teal→sky header with a sky accent; that difference is the only way you can
// tell at a glance which one you are looking at.
export const CREATE_CHROME = {
    title: 'New Purchase Request',
    subtitle: 'Material Purchase Request (MPR)',
    gradient: 'linear-gradient(135deg,#2563eb 0%,#4f46e5 100%)',
    accent: '#6366f1',
    submitLabel: 'Submit Request',
    icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
};

export const EDIT_CHROME = {
    title: 'Edit Purchase Request',
    gradient: 'linear-gradient(135deg,#0f766e 0%,#0284c7 100%)',
    accent: '#0ea5e9',
    submitLabel: 'Save Changes',
    icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
};
