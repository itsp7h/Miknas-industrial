import { Link } from 'react-router-dom';
import useWarehouseDetail from '../../../components/inventory/warehouse/useWarehouseDetail';
import { TONES } from '../../../components/inventory/warehouse/WarehouseStockTable';
import { money, qty } from '../../../currency';

const isLow = (line) => line.minimum_stock_level > 0 && line.quantity < line.minimum_stock_level;

function Section({ title, lines, emptyMessage, tone }) {
    const total = lines.reduce((sum, line) => sum + Number(line.total_value ?? 0), 0);

    return (
        <div style={{ marginBottom: 20 }}>
            {/* A tinted strip rather than a bare heading, so the two groups are
                told apart on a screen that is otherwise a column of white cards. */}
            <div style={{
                display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 8,
                padding: '9px 12px', marginBottom: 8,
                background: tone.band, border: `1px solid ${tone.border}`, borderRadius: 10,
            }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, minWidth: 0 }}>
                    <span style={{ width: 8, height: 8, borderRadius: 8, background: tone.dot, flexShrink: 0 }} />
                    <span style={{ fontSize: 13, fontWeight: 700, color: tone.text }}>{title}</span>
                    <span style={{ fontSize: 11.5, color: tone.muted }}>{lines.length}</span>
                </div>
                <span style={{ fontSize: 12, fontWeight: 700, color: tone.text, whiteSpace: 'nowrap' }}>
                    {money(total)}
                </span>
            </div>

            {lines.length === 0 && <p style={{ fontSize: 13, color: '#64748b' }}>{emptyMessage}</p>}

            {/* A seven-column table does not fit a phone, so each line is a card. */}
            {lines.map((line) => (
                <div key={line.id} style={{
                    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                    padding: 12, marginBottom: 8,
                }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontWeight: 600, color: '#0f172a', fontSize: 14 }}>{line.item_name}</div>
                            <div className="font-mono" style={{ fontSize: 11, color: '#94a3b8' }}>{line.item_code}</div>
                        </div>
                        <div style={{
                            fontWeight: 700, flexShrink: 0,
                            color: isLow(line) ? '#dc2626' : '#1f2937',
                        }}>
                            {qty(line.quantity)} {line.unit_of_measure}
                        </div>
                    </div>
                    <div style={{ fontSize: 12, color: '#64748b', marginTop: 6 }}>
                        {line.item_category_name ?? '—'} · {money(line.total_value)}
                    </div>
                </div>
            ))}
        </div>
    );
}

export default function WarehouseDetailPage() {
    const w = useWarehouseDetail();

    if (w.loading) return <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>;
    if (!w.warehouse) return <p style={{ fontSize: 14, color: '#64748b' }}>Warehouse not found.</p>;

    return (
        <div>
            <Link to="/app/inventory/warehouses" className="text-blue-600" style={{ fontSize: 12.5 }}>← Warehouses</Link>

            <div style={{
                background: '#fff', border: '1px solid #e2e8f0', borderRadius: 14,
                overflow: 'hidden', margin: '8px 0 16px',
            }}>
                <div style={{ display: 'flex', alignItems: 'stretch' }}>
                    <div style={{ width: 5, background: 'linear-gradient(180deg,#10b981,#34d399)', flexShrink: 0 }} />
                    <div style={{ padding: '12px 14px', minWidth: 0 }}>
                        <div style={{ fontSize: 17, fontWeight: 700, color: '#0f172a' }}>{w.warehouse.name}</div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 5, flexWrap: 'wrap' }}>
                            <span className="font-mono" style={{
                                fontSize: 11, color: '#475569', background: '#f1f5f9',
                                border: '1px solid #e2e8f0', borderRadius: 6, padding: '2px 6px',
                            }}>
                                {w.warehouse.code}
                            </span>
                            <span className={w.warehouse.is_active ? 'badge-green' : 'badge-gray'}>
                                {w.warehouse.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                        <div style={{ fontSize: 12.5, color: '#64748b', marginTop: 5 }}>
                            {w.warehouse.location || 'No location recorded'}
                        </div>
                    </div>
                </div>

                <div style={{
                    display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 10,
                    padding: '12px 14px', background: '#f8fafc', borderTop: '1px solid #e2e8f0', fontSize: 12,
                }}>
                    <div><div style={{ color: '#94a3b8' }}>Items</div><div style={{ fontWeight: 700, fontSize: 15 }}>{w.meta.total_items ?? 0}</div></div>
                    <div><div style={{ color: '#94a3b8' }}>Stock value</div><div style={{ fontWeight: 700, fontSize: 15 }}>{money(w.meta.total_value)}</div></div>
                    <div><div style={{ color: '#94a3b8' }}>Raw materials</div><div style={{ fontWeight: 700, fontSize: 15, color: '#1d4ed8' }}>{w.meta.raw_material_count ?? 0}</div></div>
                    <div>
                        <div style={{ color: '#94a3b8' }}>Finished goods</div>
                        <div style={{ fontWeight: 700, fontSize: 15, color: '#047857' }}>{w.meta.finished_good_count ?? 0}</div>
                    </div>
                </div>
            </div>

            <input
                type="search"
                value={w.query}
                onChange={(e) => w.setQuery(e.target.value)}
                placeholder="Search code, name, category, unit…"
                aria-label="Search stock"
                className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                style={{ marginBottom: 12 }}
            />

            <Section
                title="Raw Materials"
                tone={TONES.raw_material}
                lines={w.rawMaterials}
                emptyMessage={w.query ? 'No raw materials match that search.' : 'No raw materials here.'}
            />
            <Section
                title="Finished Goods"
                tone={TONES.finished_good}
                lines={w.finishedGoods}
                emptyMessage={w.query ? 'No finished goods match that search.' : 'No finished goods here.'}
            />
            {w.other.length > 0 && <Section title="Work In Progress" tone={TONES.wip} lines={w.other} emptyMessage="" />}
        </div>
    );
}
