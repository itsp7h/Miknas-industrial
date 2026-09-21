import { Link } from 'react-router-dom';
import useWarehouseDetail from '../../../components/inventory/warehouse/useWarehouseDetail';
import WarehouseStockTable, { TONES } from '../../../components/inventory/warehouse/WarehouseStockTable';
import mapLink from '../../../components/map/mapLink';
import { money } from '../../../currency';

/** A figure in the header band. Tiles are divided, so they do not run together. */
function Stat({ label, value, tone, last }) {
    return (
        <div style={{
            padding: '0 22px',
            borderRight: last ? 'none' : '1px solid #e2e8f0',
        }}>
            <div style={{
                fontSize: 10.5, fontWeight: 600, textTransform: 'uppercase',
                letterSpacing: '.06em', color: '#94a3b8', whiteSpace: 'nowrap',
            }}>
                {label}
            </div>
            <div style={{ fontSize: 19, fontWeight: 700, color: tone ?? '#0f172a', marginTop: 2 }}>
                {value}
            </div>
        </div>
    );
}

export default function WarehouseDetailPage() {
    const w = useWarehouseDetail();

    if (w.loading) return <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>;
    if (!w.warehouse) return <p style={{ fontSize: 14, color: '#64748b' }}>Warehouse not found.</p>;

    const pin = mapLink(w.warehouse.latitude, w.warehouse.longitude);
    const belowMinimum = w.meta.below_minimum ?? 0;

    return (
        <div>
            <Link
                to="/app/inventory/warehouses"
                className="text-blue-600 hover:underline"
                style={{ fontSize: 12.5, display: 'inline-block', marginBottom: 10 }}
            >
                ← Warehouses
            </Link>

            {/* The page was a stack of white on white. The warehouse itself gets a
                card with the module's colour down its edge, so the header reads as
                the subject of the page rather than as the first row of a table. */}
            <div style={{
                background: '#fff', border: '1px solid #e2e8f0', borderRadius: 14,
                overflow: 'hidden', marginBottom: 20, boxShadow: '0 1px 2px rgba(15,23,42,.04)',
            }}>
                <div style={{ display: 'flex', alignItems: 'stretch' }}>
                    <div style={{ width: 5, background: 'linear-gradient(180deg,#10b981,#34d399)', flexShrink: 0 }} />
                    <div style={{ padding: '16px 20px', flex: 1, minWidth: 0 }}>
                        <div style={{ display: 'flex', alignItems: 'baseline', gap: 10, flexWrap: 'wrap' }}>
                            <h1 style={{ fontSize: 20, fontWeight: 700, color: '#0f172a' }}>{w.warehouse.name}</h1>
                            <span
                                className="font-mono"
                                style={{
                                    fontSize: 11.5, color: '#475569', background: '#f1f5f9',
                                    border: '1px solid #e2e8f0', borderRadius: 6, padding: '2px 7px',
                                }}
                            >
                                {w.warehouse.code}
                            </span>
                            <span className={w.warehouse.is_active ? 'badge-green' : 'badge-gray'}>
                                {w.warehouse.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                        <div style={{ fontSize: 13, color: '#64748b', marginTop: 4 }}>
                            {w.warehouse.location || 'No location recorded'}
                            {pin && (
                                <>
                                    {' · '}
                                    <a href={pin} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:underline">
                                        📍 View on map
                                    </a>
                                </>
                            )}
                        </div>
                    </div>
                </div>

                <div style={{
                    display: 'flex', flexWrap: 'wrap', rowGap: 12,
                    padding: '14px 20px 14px 0', marginLeft: 5,
                    background: '#f8fafc', borderTop: '1px solid #e2e8f0',
                }}>
                    <Stat label="Items" value={w.meta.total_items ?? 0} />
                    <Stat label="Raw materials" value={w.meta.raw_material_count ?? 0} tone="#1d4ed8" />
                    <Stat label="Finished goods" value={w.meta.finished_good_count ?? 0} tone="#047857" />
                    <Stat label="Stock value" value={money(w.meta.total_value)} />
                    <Stat
                        label="Below minimum"
                        value={belowMinimum}
                        tone={belowMinimum > 0 ? '#dc2626' : undefined}
                        last
                    />
                </div>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 14, gap: 12 }}>
                <input
                    type="search"
                    value={w.query}
                    onChange={(e) => w.setQuery(e.target.value)}
                    placeholder="Search code, name, category, unit…"
                    aria-label="Search stock"
                    style={{
                        width: 340, padding: '9px 13px', border: '1px solid #e2e8f0',
                        borderRadius: 9, fontSize: 13.5, outline: 'none', background: '#fff',
                    }}
                />
                <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                    {w.query ? `${w.matchCount} of ${w.totalCount} items` : `${w.totalCount} items`}
                </div>
            </div>

            {/* The two item pages split raw materials from finished goods; a
                warehouse holds both, so it shows both, split the same way. */}
            <WarehouseStockTable
                title="Raw Materials"
                tone={TONES.raw_material}
                lines={w.rawMaterials}
                emptyMessage={w.query ? 'No raw materials match that search.' : 'No raw materials in this warehouse.'}
            />
            <WarehouseStockTable
                title="Finished Goods"
                tone={TONES.finished_good}
                lines={w.finishedGoods}
                emptyMessage={w.query ? 'No finished goods match that search.' : 'No finished goods in this warehouse.'}
            />
            {w.other.length > 0 && (
                <WarehouseStockTable title="Work In Progress" tone={TONES.wip} lines={w.other} emptyMessage="" />
            )}
        </div>
    );
}
