import { useState } from 'react';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import SupplierInvoiceModal from '../../../components/purchase/invoice/SupplierInvoiceModal';
import useSupplierInvoiceList from '../../../components/purchase/invoice/useSupplierInvoiceList';
import { STATUS_LABELS, money } from '../../../components/purchase/invoice/invoiceStyles';
import { DocRow, PurchasingHeader } from '../../../components/purchase/mobile/Purchasing';
import { ActionSheet, Card, CountLine, EmptyState, MobilePage, SearchField } from '../../../components/mobile/ui';
import { longDate } from '../../../components/mobile/format';
import { useAccess } from '../../../layouts/AccessContext';

const TONE = { unpaid: 'red', partial: 'amber', paid: 'green' };

// Purchasing → Invoices (SteelERP-Mobile-Designs-V2). An invoice has no page
// of its own, so tapping one offers what can be done with it.
export default function SupplierInvoiceListPage() {
    const v = useSupplierInvoiceList();
    const { can } = useAccess();
    const [picked, setPicked] = useState(null);
    const canCreate = can('supplier-invoices.create');

    const unpaid = v.invoices.filter((inv) => inv.status !== 'paid').length;

    return (
        <MobilePage gap={14}>
            <PurchasingHeader
                tab="invoices"
                action={{
                    label: 'New supplier invoice', short: 'Invoice', onClick: v.openCreate, allowed: canCreate,
                    denied: 'You do not have permission to create supplier invoices',
                }}
            />

            <SearchField value={v.query} onChange={v.setQuery} placeholder="Search invoices" />
            <CountLine>
                {v.query
                    ? `${v.filtered.length} of ${v.invoices.length} supplier invoices`
                    : `${v.invoices.length} supplier invoice${v.invoices.length === 1 ? '' : 's'}${unpaid ? ` · ${unpaid} unpaid` : ''}`}
            </CountLine>

            {v.filtered.length === 0 ? (
                <EmptyState icon="fileText" title={v.query ? 'No invoices match that search' : 'No supplier invoices yet'} />
            ) : (
                <Card>
                    {v.filtered.map((invoice, i) => {
                        const outstanding = Number(invoice.outstanding ?? 0);

                        return (
                            <DocRow
                                key={invoice.id}
                                onClick={() => setPicked(invoice)}
                                number={invoice.invoice_number}
                                title={invoice.supplier_name ?? '—'}
                                sub={[
                                    longDate(invoice.invoice_date),
                                    invoice.po_number ?? 'no PO linked',
                                    invoice.status === 'partial' && outstanding > 0 ? `${money(outstanding)} due` : null,
                                ].filter(Boolean).join(' · ')}
                                amount={money(invoice.total_amount)}
                                status={STATUS_LABELS[invoice.status] ?? invoice.status}
                                statusTone={TONE[invoice.status] ?? 'slate'}
                                last={i === v.filtered.length - 1}
                            />
                        );
                    })}
                </Card>
            )}

            <ActionSheet
                open={!!picked}
                onClose={() => setPicked(null)}
                title={picked ? `${picked.invoice_number} · ${picked.supplier_name ?? ''}` : ''}
                options={picked ? [
                    picked.status !== 'paid' && (can('supplier-payments.create')
                        ? { label: 'Record a payment', to: `/app/purchase/payments?invoice_id=${picked.id}` }
                        : { label: 'Record a payment', disabled: true, disabledReason: 'You do not have permission to record payments', onClick: () => {} }),
                    {
                        label: 'Edit invoice', onClick: () => v.openEdit(picked),
                        disabled: !can('supplier-invoices.edit'), disabledReason: 'You do not have permission to edit supplier invoices',
                    },
                    {
                        label: 'Delete invoice', danger: true, onClick: () => v.setDeleting(picked),
                        disabled: !can('supplier-invoices.delete'), disabledReason: 'You do not have permission to delete supplier invoices',
                    },
                ].filter(Boolean) : []}
            />

            {v.modalOpen && (
                <SupplierInvoiceModal
                    invoice={v.editing}
                    onSaved={v.handleSaved}
                    onCancel={() => v.setModalOpen(false)}
                />
            )}
            <ConfirmModal
                open={!!v.deleting}
                title="Delete this invoice?"
                body={v.deleting ? `${v.deleting.invoice_number} will be permanently removed.` : ''}
                onConfirm={v.handleDeleteConfirmed}
                onCancel={() => v.setDeleting(null)}
            />
        </MobilePage>
    );
}
