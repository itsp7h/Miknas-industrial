// The purchase order modal's two identities, in the same shape as
// requestModalChrome.js and supplierModalChrome.js. Its own hue again, so a
// glance at the header says which document you are editing: emerald→teal for
// a new order, amber→orange for changing one that exists (matching the
// supplier modal's edit colour — editing is editing).
export const CREATE_CHROME = {
    title: 'New Purchase Order',
    subtitle: 'Local Purchase Order (LPO)',
    gradient: 'linear-gradient(135deg,#047857 0%,#0d9488 100%)',
    accent: '#10b981',
    submitLabel: 'Create Purchase Order',
    icon: 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
};

export const EDIT_CHROME = {
    title: 'Edit Purchase Order',
    gradient: 'linear-gradient(135deg,#b45309 0%,#ea580c 100%)',
    accent: '#f59e0b',
    submitLabel: 'Save Changes',
    icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
};
