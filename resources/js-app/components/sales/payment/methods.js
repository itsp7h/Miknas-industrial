/** Mirrors the enum check constraint on payment_receipts.payment_method. */
export const METHOD_LABELS = {
    cash: 'Cash',
    bank_transfer: 'Bank Transfer',
    cheque: 'Cheque',
    other: 'Other',
};

export const methodLabel = (method) =>
    METHOD_LABELS[method] ?? String(method ?? '').replace(/_/g, ' ');
