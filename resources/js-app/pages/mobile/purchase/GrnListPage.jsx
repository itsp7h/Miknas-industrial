import { useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import GrnModal from '../../../components/purchase/grn/GrnModal';
import useGrnList from '../../../components/purchase/grn/useGrnList';
import { STATUS_LABELS, confirmBlockedReason, needsLabel } from '../../../components/purchase/grn/grnStyles';
import { DocRow, PurchasingHeader, Warning } from '../../../components/purchase/mobile/Purchasing';
import { Card, CountLine, EmptyState, MobilePage, SearchField } from '../../../components/mobile/ui';
import { longDate } from '../../../components/mobile/format';
import { useAccess } from '../../../layouts/AccessContext';

// Purchasing → Receipts (SteelERP-Mobile-Designs-V2). A draft says what it
// still needs, or offers Confirm once it needs nothing; deleting a draft is on
// its own page.
export default function GrnListPage() {
    const g = useGrnList();
    const navigate = useNavigate();
    const canCreate = useAccess().can('goods-receipts.create');
    const [params, setParams] = useSearchParams();
    const presetOrderId = params.get('purchase_order_id');

    // A purchase order's "Create GRN" arrives with its id; Home's "Receive
    // goods" with ?new=1.
    useEffect(() => {
        if (presetOrderId && canCreate) g.setModalOpen(true);
        if (params.get('new') === '1') {
            if (canCreate) g.setModalOpen(true);
            params.delete('new');
            setParams(params, { replace: true });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [presetOrderId, params]);

    function closeModal() {
        g.setModalOpen(false);
        if (presetOrderId) {
            params.delete('purchase_order_id');
            setParams(params, { replace: true });
        }
    }

    const drafts = g.grns.filter((grn) => grn.status !== 'confirmed').length;

    return (
        <MobilePage gap={14}>
            <PurchasingHeader
                tab="grns"
                action={{
                    label: 'New goods receipt', short: 'GRN', onClick: () => g.setModalOpen(true), allowed: canCreate,
                    denied: 'You do not have permission to receive goods',
                }}
            />

            <SearchField value={g.query} onChange={g.setQuery} placeholder="Search receipts" />
            <CountLine>
                {g.query
                    ? `${g.filtered.length} of ${g.grns.length} goods receipt notes`
                    : `${g.grns.length} goods receipt note${g.grns.length === 1 ? '' : 's'}${drafts ? ` · ${drafts} draft${drafts === 1 ? '' : 's'}` : ''}`}
            </CountLine>

            {g.filtered.length === 0 ? (
                <EmptyState icon="download" title={g.query ? 'No receipts match that search' : 'No goods received yet'} />
            ) : (
                <Card>
                    {g.filtered.map((grn, i) => {
                        const draft = grn.status !== 'confirmed';
                        const needs = needsLabel(grn);
                        const ready = draft && !confirmBlockedReason(grn);

                        return (
                            <DocRow
                                key={grn.id}
                                onClick={() => navigate(`/app/purchase/grns/${grn.id}`)}
                                number={grn.grn_number}
                                title={grn.supplier_name ?? '—'}
                                sub={[grn.po_number, grn.warehouse_name, longDate(grn.received_date)].filter(Boolean).join(' · ')}
                                status={STATUS_LABELS[grn.status] ?? grn.status}
                                statusTone={draft ? 'slate' : 'green'}
                                last={i === g.filtered.length - 1}
                                footer={(needs || ready) && (
                                    <span style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10 }}>
                                        <Warning ok={!needs}>{needs ?? 'All documents attached'}</Warning>
                                        {ready && (
                                            <button
                                                type="button"
                                                onClick={(e) => { e.stopPropagation(); g.setConfirming(grn); }}
                                                style={{
                                                    flexShrink: 0, fontSize: 14, fontWeight: 600, color: '#FFFFFF', background: '#15803D',
                                                    padding: '7px 14px', borderRadius: 10, border: 0, font: 'inherit', cursor: 'pointer',
                                                }}
                                            >
                                                Confirm
                                            </button>
                                        )}
                                    </span>
                                )}
                            />
                        );
                    })}
                </Card>
            )}

            {g.modalOpen && (
                <GrnModal presetOrderId={presetOrderId} onSaved={g.handleSaved} onCancel={closeModal} />
            )}
            <ConfirmModal
                open={!!g.confirming}
                title="Confirm this GRN?"
                body={g.confirming
                    ? `${g.confirming.grn_number} will raise stock at ${g.confirming.warehouse_name ?? 'the warehouse'} for its inventory lines, charge any consumable lines to their projects, and update the purchase order. This cannot be undone.`
                    : ''}
                onConfirm={g.handleConfirm}
                onCancel={() => g.setConfirming(null)}
            />
        </MobilePage>
    );
}
