import ProductionDialogs from '../../../components/inventory/production/ProductionDialogs';
import useProductionRunList from '../../../components/inventory/production/useProductionRunList';
import { formatDate } from '../../../components/inventory/movement/movementStyles';
import { useAccess } from '../../../layouts/AccessContext';
import { money, qty } from '../../../currency';
import { Hero, MobilePage } from '../../../components/mobile/ui';

const DISABLED = { opacity: 0.5, cursor: 'not-allowed' };
const FULL = { width: '100%', justifyContent: 'center' };

export default function ProductionPage() {
    const p = useProductionRunList();
    const { can } = useAccess();
    const canCreate = can('production.create');
    const canRecipes = can('production.manage-recipes');

    return (
        <MobilePage>
            <Hero zone="inventory" back={{ to: '/app/more', label: 'More' }} title="Production" subtitle="Make finished goods from raw materials, at cost" />
            <div>

            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: 8, marginBottom: 14 }}>
                <button
                    type="button" onClick={() => p.setFormOpen(true)} className="btn-primary"
                    disabled={!canCreate} style={canCreate ? FULL : { ...FULL, ...DISABLED }}
                    title={canCreate ? undefined : 'You do not have permission to record production'}
                >
                    + New Production Run
                </button>
                <button
                    type="button" onClick={() => p.setRecipesOpen(true)} className="btn-secondary"
                    disabled={!canRecipes} style={canRecipes ? FULL : { ...FULL, ...DISABLED }}
                    title={canRecipes ? undefined : 'You do not have permission to edit recipes'}
                >
                    Recipes
                </button>
            </div>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={p.query}
                    onChange={(e) => p.setQuery(e.target.value)}
                    placeholder="Search run #, product, material…"
                    aria-label="Search production runs"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {p.query ? `${p.filtered.length} of ${p.runs.length} runs` : `${p.runs.length} runs`}
                </div>
            </div>

            {p.filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {p.query ? 'No production runs match that search.' : 'No production runs recorded.'}
                </p>
            )}

            {p.filtered.map((run) => (
                <button
                    key={run.id} type="button" onClick={() => p.setViewing(run)}
                    style={{
                        display: 'block', width: '100%', textAlign: 'left', background: '#fff',
                        border: '1px solid #e2e8f0', borderRadius: 12, padding: 12, marginBottom: 8,
                    }}
                >
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontWeight: 600, color: '#0f172a', fontSize: 14 }}>{run.item_name}</div>
                            <div className="font-mono" style={{ fontSize: 11, color: '#94a3b8' }}>{run.run_number}</div>
                        </div>
                        <div style={{ textAlign: 'right', flexShrink: 0 }}>
                            <div style={{ fontWeight: 700, color: '#1f2937' }}>{qty(run.quantity)} {run.unit_of_measure}</div>
                            <div style={{ fontSize: 12, color: '#64748b' }}>{money(run.total_cost)}</div>
                        </div>
                    </div>
                    <div style={{ fontSize: 12, color: '#64748b', marginTop: 6 }}>
                        {run.warehouse_name} · {formatDate(run.production_date)} · {money(run.unit_cost)} / {run.unit_of_measure ?? 'unit'}
                    </div>
                </button>
            ))}

            <ProductionDialogs p={p} compact />
            </div>
        </MobilePage>
    );
}
