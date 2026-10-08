import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import ConfirmGrnButton from '../../../components/purchase/grn/ConfirmGrnButton';
import GrnDetail from '../../../components/purchase/grn/GrnDetail';
import useGrnDetail from '../../../components/purchase/grn/useGrnDetail';
import { STATUS_LABELS, needsLabel } from '../../../components/purchase/grn/grnStyles';
import { Warning } from '../../../components/purchase/mobile/Purchasing';
import { apiDelete } from '../../../api/client';
import { useToast } from '../../../components/ui/Toast';
import { useAccess } from '../../../layouts/AccessContext';
import { useSetPageTitle } from '../../../layouts/PageTitleContext';
import {
    ActionSheet, BarButton, BottomBar, EmptyState, Hero, Loading, MobilePage, Pill,
} from '../../../components/mobile/ui';
import { MONO } from '../../../components/mobile/theme';
import { longDate } from '../../../components/mobile/format';

// One goods receipt. While it is a draft, what it still needs is said up top,
// and Confirm waits in the bottom bar — disabled, with the reason, until the
// paperwork and any unit conversion are in.
export default function GrnDetailPage() {
    const { id } = useParams();
    const navigate = useNavigate();
    const g = useGrnDetail(id);
    const access = useAccess();
    const { showToast } = useToast();
    const canUpload = access.can('goods-receipts.edit');
    const canConvert = access.can('goods-receipts.convert-units');
    const [menu, setMenu] = useState(false);
    const [deleting, setDeleting] = useState(false);

    useSetPageTitle(g.grn ? `Goods Receipt Note — ${g.grn.grn_number}` : null);

    async function remove() {
        setDeleting(false);
        try {
            await apiDelete(`/purchase/grns/${g.grn.id}`);
            showToast('GRN deleted.', 'success');
            navigate('/app/purchase/grns', { replace: true });
        } catch (err) {
            showToast(err.message || 'Failed to delete the GRN.', 'error');
        }
    }

    const back = { to: '/app/purchase/grns', label: 'Receipts' };

    if (!g.grn) {
        return (
            <MobilePage>
                <Hero zone="purchase" back={back} title={g.loading ? 'Loading…' : 'Not found'} />
                {g.loading ? <Loading /> : <EmptyState icon="download" title="That GRN could not be found." />}
            </MobilePage>
        );
    }

    const grn = g.grn;
    const draft = grn.status !== 'confirmed';
    const needs = draft ? needsLabel(grn) : null;

    return (
        <MobilePage gap={16}>
            <Hero
                zone="purchase"
                back={back}
                title={grn.grn_number}
                titleStyle={{ fontFamily: MONO, fontSize: 22, fontWeight: 500 }}
                actions={draft && <BarButton icon="more" label="More options" onClick={() => setMenu(true)} />}
            >
                <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginTop: -8 }}>
                    <div><Pill tone={draft ? 'slate' : 'green'}>{STATUS_LABELS[grn.status] ?? grn.status}</Pill></div>
                    <span style={{ fontSize: 14, color: 'rgba(255,255,255,0.85)' }}>
                        {[grn.supplier_name, grn.po_number, grn.warehouse_name, longDate(grn.received_date)].filter(Boolean).join(' · ')}
                    </span>
                </div>
            </Hero>

            {needs && <Warning>{needs}</Warning>}

            <GrnDetail grn={grn} compact canUpload={canUpload} uploading={g.uploading} onUpload={g.uploadDocuments}
                canConvert={canConvert} onConvert={g.convert} />

            <BottomBar>
                {draft && (
                    <ConfirmGrnButton
                        grn={grn} onClick={() => g.setConfirming(true)} className="btn-success"
                        style={{ width: '100%', justifyContent: 'center', minHeight: 52, borderRadius: 16, fontSize: 16, fontWeight: 600 }}
                    >
                        Confirm &amp; receive stock
                    </ConfirmGrnButton>
                )}
            </BottomBar>

            <ActionSheet
                open={menu}
                onClose={() => setMenu(false)}
                title={grn.grn_number}
                options={[{
                    label: 'Delete draft GRN', danger: true, onClick: () => setDeleting(true),
                    disabled: !access.can('goods-receipts.delete'), disabledReason: 'You do not have permission to delete GRNs',
                }]}
            />

            <ConfirmModal
                open={g.confirming}
                title="Confirm this GRN?"
                body={`${grn.grn_number} will raise stock at ${grn.warehouse_name ?? 'the warehouse'} for its inventory lines, charge any consumable lines to their projects, and update the purchase order. This cannot be undone.`}
                onConfirm={g.confirm}
                onCancel={() => g.setConfirming(false)}
            />
            <ConfirmModal
                open={deleting}
                title="Delete this GRN?"
                body={`${grn.grn_number} will be permanently removed.`}
                onConfirm={remove}
                onCancel={() => setDeleting(false)}
            />
        </MobilePage>
    );
}
