// The purchase pipeline's stages as the mobile screens name and colour them:
// short names (the board's), and the colours of the Home "Requests by stage"
// bar, which the pipeline list's stage pills reuse.

export const STAGE_META = {
    draft: { label: 'Draft', color: '#64748B', tone: 'slate' },
    gm_approval: { label: 'GM approval', color: '#9F1239', tone: 'rose' },
    rfq: { label: 'RFQ', color: '#0891B2', tone: 'indigo' },
    quoting: { label: 'Quoting', color: '#D97706', tone: 'amber' },
    comparison: { label: 'Comparison', color: '#7C3AED', tone: 'violet' },
    lpo: { label: 'LPO', color: '#2563EB', tone: 'blue' },
    receiving: { label: 'Receiving', color: '#0D9488', tone: 'teal' },
    complete: { label: 'Complete', color: '#15803D', tone: 'green' },
};

export function stageMeta(stage) {
    return STAGE_META[stage] ?? { label: stage, color: '#64748B', tone: 'slate' };
}
