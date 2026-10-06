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
            <div className="page-header">
                <div>
                    <h1 className="page-title">Goods Receipt Note</h1>
                    <p className="page-subtitle">
                        <Link to="/app/purchase/grns" className="text-blue-600 hover:underline">GRNs</Link>
                        {' / '}{g.grn?.grn_number ?? '…'}
                    </p>
                </div>
                {/* Confirming is what receives the stock; the Blade show page had no
                    way to do it. */}
                {g.grn && g.grn.status !== 'confirmed' && (
                    <ConfirmGrnButton grn={g.grn} onClick={() => g.setConfirming(true)}>
                        Confirm &amp; Receive Stock
                    </ConfirmGrnButton>
                )}
            </div>

            {g.loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}
            {!g.loading && !g.grn && <p style={{ fontSize: 14, color: '#64748b' }}>That GRN could not be found.</p>}

            <GrnDetail grn={g.grn} canUpload={canUpload} uploading={g.uploading} onUpload={g.uploadDocuments} />

            <ConfirmModal
                open={g.confirming}
                title="Confirm this GRN?"
                body={g.grn
                    ? `${g.grn.grn_number} will raise stock at ${g.grn.warehouse_name ?? 'the warehouse'} for its inventory lines, charge any consumable lines to their projects, and update the purchase order. This cannot be undone.`
                    : ''}
                onConfirm={g.confirm}
                onCancel={() => g.setConfirming(false)}
            />
        </div>
    );
}
