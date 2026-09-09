import { Link, useParams } from 'react-router-dom';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import GrnDetail from '../../../components/purchase/grn/GrnDetail';
import useGrnDetail from '../../../components/purchase/grn/useGrnDetail';
import { useSetPageTitle } from '../../../layouts/PageTitleContext';

export default function GrnDetailPage() {
    const { id } = useParams();
    const g = useGrnDetail(id);

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
                    <button type="button" onClick={() => g.setConfirming(true)} className="btn-success">
                        Confirm &amp; Receive Stock
                    </button>
                )}
            </div>

            {g.loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}
            {!g.loading && !g.grn && <p style={{ fontSize: 14, color: '#64748b' }}>That GRN could not be found.</p>}

            <GrnDetail grn={g.grn} />

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
