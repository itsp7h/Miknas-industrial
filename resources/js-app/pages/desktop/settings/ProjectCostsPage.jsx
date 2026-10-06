import { Link } from 'react-router-dom';
import useProjectCosts from '../../../components/settings/project/useProjectCosts';
import { amount, money } from '../../../currency';
import { formatDate, qty } from '../../../components/purchase/grn/grnStyles';

function Stat({ label, value, tone, last }) {
    return (
        <div style={{ padding: '0 22px', borderRight: last ? 'none' : '1px solid #e2e8f0' }}>
            <div style={{
                fontSize: 10.5, fontWeight: 600, textTransform: 'uppercase',
                letterSpacing: '.06em', color: '#94a3b8', whiteSpace: 'nowrap',
            }}>
                {label}
            </div>
            <div style={{ fontSize: 19, fontWeight: 700, color: tone ?? '#0f172a', marginTop: 2 }}>{value}</div>
        </div>
    );
}

const TH = {
    padding: '9px 12px', fontSize: 11, fontWeight: 600, textTransform: 'uppercase',
    letterSpacing: '.05em', color: '#64748b', textAlign: 'left', whiteSpace: 'nowrap',
};
const TD = { padding: '9px 12px', fontSize: 13, color: '#1e293b', borderTop: '1px solid #f1f5f9' };
const NUM = { textAlign: 'right', fontVariantNumeric: 'tabular-nums', whiteSpace: 'nowrap' };

export default function ProjectCostsPage() {
    const c = useProjectCosts();

    if (c.loading) return <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>;
    if (!c.project) return <p style={{ fontSize: 14, color: '#64748b' }}>Project not found.</p>;

    return (
        <div>
            <Link
                to="/app/settings/projects" className="text-blue-600 hover:underline"
                style={{ fontSize: 12.5, display: 'inline-block', marginBottom: 10 }}
            >
                ← Projects
            </Link>

            <div style={{
                background: '#fff', border: '1px solid #e2e8f0', borderRadius: 14,
                overflow: 'hidden', marginBottom: 20, boxShadow: '0 1px 2px rgba(15,23,42,.04)',
            }}>
                <div style={{ display: 'flex', alignItems: 'stretch' }}>
                    <div style={{ width: 5, background: 'linear-gradient(180deg,#2563eb,#60a5fa)', flexShrink: 0 }} />
                    <div style={{ padding: '16px 20px', flex: 1, minWidth: 0 }}>
                        <div style={{ display: 'flex', alignItems: 'baseline', gap: 10, flexWrap: 'wrap' }}>
                            <h1 style={{ fontSize: 20, fontWeight: 700, color: '#0f172a' }}>{c.project.name}</h1>
                            {c.project.company_name && (
                                <span style={{ fontSize: 12, color: '#1d4ed8', background: '#dbeafe', padding: '1px 8px', borderRadius: 9 }}>
                                    {c.project.company_name}
                                </span>
                            )}
                            {!c.project.is_active && <span className="badge-gray">Inactive</span>}
                        </div>
                        <div style={{ fontSize: 13, color: '#64748b', marginTop: 4 }}>
                            Project costs — consumables received for this project on confirmed GRNs. They are charged here and never enter stock.
                        </div>
                    </div>
                </div>
                <div style={{
                    display: 'flex', flexWrap: 'wrap', rowGap: 12, padding: '14px 20px 14px 0',
                    marginLeft: 5, background: '#f8fafc', borderTop: '1px solid #e2e8f0',
                }}>
                    <Stat label="Total charged" value={money(c.meta.total)} tone="#1d4ed8" />
                    <Stat label="Lines" value={c.meta.line_count ?? 0} />
                    <Stat label="GRNs" value={c.meta.grn_count ?? 0} last />
                </div>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 14, gap: 12 }}>
                <input
                    type="search" value={c.query} onChange={(e) => c.setQuery(e.target.value)}
                    placeholder="Search GRN, LPO, supplier, item…" aria-label="Search costs"
                    style={{
                        width: 340, padding: '9px 13px', border: '1px solid #e2e8f0',
                        borderRadius: 9, fontSize: 13.5, outline: 'none', background: '#fff',
                    }}
                />
                <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                    {c.query ? `${c.matchCount} of ${c.totalCount} lines` : `${c.totalCount} lines`}
                </div>
            </div>

            {c.totalCount === 0 ? (
                <div className="card card-body" style={{ textAlign: 'center', padding: '3rem', color: '#9ca3af' }}>
                    Nothing charged to this project yet. Record a GRN line as Consumable for this project and confirm it.
                </div>
            ) : c.matchCount === 0 ? (
                <div className="card card-body" style={{ textAlign: 'center', padding: '2rem', color: '#9ca3af' }}>
                    No results for &quot;{c.query}&quot;.
                </div>
            ) : (
                <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12, overflow: 'hidden' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead style={{ background: '#f8fafc' }}>
                            <tr>
                                <th style={TH}>Date</th>
                                <th style={TH}>GRN</th>
                                <th style={TH}>LPO</th>
                                <th style={TH}>Supplier</th>
                                <th style={TH}>Item</th>
                                <th style={{ ...TH, textAlign: 'right' }}>Qty</th>
                                <th style={{ ...TH, textAlign: 'right' }}>Rate</th>
                                <th style={{ ...TH, textAlign: 'right' }}>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {c.lines.map((line) => (
                                <tr key={line.id}>
                                    <td style={{ ...TD, whiteSpace: 'nowrap', color: '#64748b' }}>{formatDate(line.received_date)}</td>
                                    <td style={TD}>
                                        <Link to={`/app/purchase/grns/${line.grn_id}`} className="text-blue-600 hover:underline font-mono" style={{ fontSize: 12.5 }}>
                                            {line.grn_number}
                                        </Link>
                                    </td>
                                    <td style={TD}>
                                        {line.purchase_order_id ? (
                                            <Link to={`/app/purchase/orders/${line.purchase_order_id}`} className="text-blue-600 hover:underline font-mono" style={{ fontSize: 12.5 }}>
                                                {line.po_number}
                                            </Link>
                                        ) : '—'}
                                    </td>
                                    <td style={TD}>{line.supplier_name ?? '—'}</td>
                                    <td style={TD}>
                                        <div style={{ fontWeight: 600 }}>{line.item_name}</div>
                                        <div className="font-mono" style={{ fontSize: 11, color: '#94a3b8' }}>{line.item_code}</div>
                                    </td>
                                    <td style={{ ...TD, ...NUM }}>{qty(line.quantity)} {line.unit_of_measure}</td>
                                    <td style={{ ...TD, ...NUM }}>{amount(line.rate)}</td>
                                    <td style={{ ...TD, ...NUM, fontWeight: 600 }}>{amount(line.amount)}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr style={{ background: '#f8fafc' }}>
                                <td colSpan={7} style={{ ...TD, fontWeight: 700, textAlign: 'right', borderTop: '2px solid #e2e8f0' }}>
                                    {c.query ? 'Total (shown lines)' : 'Total'}
                                </td>
                                <td style={{ ...TD, ...NUM, fontWeight: 700, color: '#1d4ed8', borderTop: '2px solid #e2e8f0' }}>
                                    {money(c.query ? c.filteredTotal : c.meta.total)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            )}
        </div>
    );
}
