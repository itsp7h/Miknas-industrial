import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import Modal from '../../../components/ui/Modal';
import MaterialIssueForm from '../../../components/production/issue/MaterialIssueForm';
import useMaterialIssueList, { formatDate, num } from '../../../components/production/issue/useMaterialIssueList';

export default function MaterialIssueListPage() {
    const m = useMaterialIssueList();
    const [params] = useSearchParams();
    const [formOpen, setFormOpen] = useState(false);

    function handleSaved(issue) {
        m.handleSaved(issue);
        setFormOpen(false);
    }

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Material Issues</h1>
                <p className="page-subtitle">Record materials issued for production</p>
            </div>

            {/* Desktop keeps the form inline under the table; on a phone that is a
                long scroll past every issue, so it opens as a sheet instead. */}
            <button
                type="button" onClick={() => setFormOpen(true)} className="btn-primary"
                style={{ width: '100%', justifyContent: 'center', marginBottom: 14 }}
            >
                + Issue Material
            </button>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={m.query}
                    onChange={(e) => m.setQuery(e.target.value)}
                    placeholder="Search order, item, warehouse…"
                    aria-label="Search material issues"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {m.query ? `${m.filtered.length} of ${m.issues.length} issues` : `${m.issues.length} issues`}
                </div>
            </div>

            {m.filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {m.query ? 'No material issues match that search.' : 'No material issues found.'}
                </p>
            )}

            {m.filtered.map((issue) => (
                <div key={issue.id} style={{
                    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                    padding: 12, marginBottom: 8,
                }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontWeight: 600, color: '#0f172a', fontSize: 14 }}>{issue.item_name ?? ''}</div>
                            <div className="font-mono" style={{ fontSize: 11, color: '#94a3b8' }}>{issue.issue_number}</div>
                        </div>
                        {/* Material leaving the warehouse — read as a decrement. */}
                        <div style={{ color: '#dc2626', fontWeight: 700, flexShrink: 0 }}>{num(issue.quantity)}</div>
                    </div>

                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5, color: '#64748b', marginTop: 6, gap: 8 }}>
                        <Link to={`/app/production/orders/${issue.production_order_id}`} className="text-blue-600 font-mono">
                            {issue.production_order_number}
                        </Link>
                        <span>{issue.warehouse_name ?? ''}</span>
                    </div>

                    <div style={{ fontSize: 12, color: '#94a3b8', marginTop: 4 }}>
                        {formatDate(issue.issue_date)}{issue.notes ? ` · ${issue.notes}` : ''}
                    </div>
                </div>
            ))}

            <Modal open={formOpen} title="Issue New Material" onClose={() => setFormOpen(false)}>
                <MaterialIssueForm presetOrderId={params.get('production_order_id')} onSaved={handleSaved} />
            </Modal>
        </div>
    );
}
