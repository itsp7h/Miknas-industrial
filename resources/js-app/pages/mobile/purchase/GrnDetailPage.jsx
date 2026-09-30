import { Link, useParams } from 'react-router-dom';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import ConfirmGrnButton from '../../../components/purchase/grn/ConfirmGrnButton';
import GrnDetail from '../../../components/purchase/grn/GrnDetail';
import useGrnDetail from '../../../components/purchase/grn/useGrnDetail';
import { useAccess } from '../../../layouts/AccessContext';
import { useSetPageTitle } from '../../../layouts/PageTitleContext';

export default function GrnDetailPage() {
    const { id } = useParams();
    const g = useGrnDetail(id);
    const canUpload = useAccess().can('goods-receipts.edit');

    useSetPageTitle(g.grn ? `Goods Receipt Note — ${g.grn.grn_number}` : null);

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <Link to="/app/purchase/grns" style={{ fontSize: 13, color: '#2563eb', textDecoration: 'none' }}>
                    ← GRNs
                </Link>
            </div>

            {g.loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}
            {!g.loading && !g.grn && <p style={{ fontSize: 14, color: '#64748b' }}>That GRN could not be found.</p>}

            <GrnDetail grn={g.grn} compact canUpload={canUpload} uploading={g.uploading} onUpload={g.uploadDocuments} />

            {/* Full-width, thumb-reachable rather than a top-right button. */}
            {g.grn && g.grn.status !== 'confirmed' && (
                <ConfirmGrnButton
                    grn={g.grn} onClick={() => g.setConfirming(true)} className="btn-success"
                    style={{ width: '100%', justifyContent: 'center', marginTop: 16 }}
                >
                    Confirm &amp; Receive Stock
                </ConfirmGrnButton>
            )}

            <ConfirmModal
                open={g.confirming}
                title="Confirm this GRN?"
                body={g.grn
                    ? `${g.grn.grn_number} will raise stock at ${g.grn.warehouse_name ?? 'the warehouse'} and update the purchase order. This cannot be undone.`
                    : ''}
                onConfirm={g.confirm}
                onCancel={() => g.setConfirming(false)}
            />
        </div>
    );
}
