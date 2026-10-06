import { Link } from 'react-router-dom';
import useProjectCosts from '../../../components/settings/project/useProjectCosts';
import { formatDate, qty } from '../../../components/purchase/grn/grnStyles';
import { amount, money } from '../../../currency';

export default function ProjectCostsPage() {
    const c = useProjectCosts();

    if (c.loading) return <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>;
    if (!c.project) return <p style={{ fontSize: 14, color: '#64748b' }}>Project not found.</p>;

    return (
        <div>
            <Link to="/app/settings/projects" className="text-blue-600" style={{ fontSize: 13, display: 'inline-block', marginBottom: 8 }}>
                ← Projects
            </Link>

            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">{c.project.name}</h1>
                <p className="page-subtitle">
                    Project costs{c.project.company_name ? ` · ${c.project.company_name}` : ''}
                    {!c.project.is_active ? ' · Inactive' : ''}
                </p>
            </div>

            {/* The total is what the page is for, so it leads, full width. */}
            <div style={{
                background: 'linear-gradient(135deg,#eff6ff,#dbeafe)', border: '1px solid #bfdbfe',
                borderRadius: 12, padding: '12px 14px', marginBottom: 12,
            }}>
                <div style={{ fontSize: 11, fontWeight: 600, textTransform: 'uppercase', letterSpacing: '.06em', color: '#3b82f6' }}>
                    Total charged
                </div>
                <div style={{ fontSize: 22, fontWeight: 700, color: '#1e3a8a' }}>{money(c.meta.total)}</div>
                <div style={{ fontSize: 12, color: '#475569', marginTop: 2 }}>
                    {c.meta.line_count ?? 0} lines · {c.meta.grn_count ?? 0} GRNs
                </div>
            </div>

            <input
                type="search" value={c.query} onChange={(e) => c.setQuery(e.target.value)}
                placeholder="Search GRN, LPO, supplier, item…" aria-label="Search costs"
                className="form-input" style={{ width: '100%', marginBottom: 6 }}
            />
            <div style={{ fontSize: 12, color: '#94a3b8', marginBottom: 10 }}>
                {c.query ? `${c.matchCount} of ${c.totalCount} lines` : `${c.totalCount} lines`}
            </div>

            {c.totalCount === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    Nothing charged to this project yet. Record a GRN line as Consumable for this project and confirm it.
                </p>
            )}
            {c.totalCount > 0 && c.matchCount === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>No results for &quot;{c.query}&quot;.</p>
            )}

            {c.lines.map((line) => (
                <div key={line.id} style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12, padding: '10px 12px', marginBottom: 8 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontSize: 14, fontWeight: 600, color: '#1e293b' }}>{line.item_name}</div>
                            <div className="font-mono" style={{ fontSize: 11, color: '#94a3b8' }}>{line.item_code}</div>
                        </div>
                        <div style={{ fontSize: 14, fontWeight: 700, color: '#1d4ed8', whiteSpace: 'nowrap' }}>{money(line.amount)}</div>
                    </div>
                    <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                        {qty(line.quantity)} {line.unit_of_measure} × {amount(line.rate)}
                    </div>
                    <div style={{ fontSize: 12, color: '#64748b', marginTop: 4, display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                        <span>{formatDate(line.received_date)}</span>
                        <Link to={`/app/purchase/grns/${line.grn_id}`} className="text-blue-600 font-mono">{line.grn_number}</Link>
                        {line.purchase_order_id && (
                            <Link to={`/app/purchase/orders/${line.purchase_order_id}`} className="text-blue-600 font-mono">{line.po_number}</Link>
                        )}
                        {line.supplier_name && <span>{line.supplier_name}</span>}
                    </div>
                </div>
            ))}
        </div>
    );
}
