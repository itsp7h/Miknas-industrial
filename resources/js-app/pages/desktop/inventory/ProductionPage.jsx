import ProductionDialogs from '../../../components/inventory/production/ProductionDialogs';
import ProductionRunTable from '../../../components/inventory/production/ProductionRunTable';
import useProductionRunList from '../../../components/inventory/production/useProductionRunList';
import { useAccess } from '../../../layouts/AccessContext';

const DISABLED = { opacity: 0.5, cursor: 'not-allowed' };

export default function ProductionPage() {
    const p = useProductionRunList();
    const { can } = useAccess();
    const canCreate = can('production.create');
    const canRecipes = can('production.manage-recipes');

    return (
        <div>
            <div className="page-header">
                <div>
                    <h1 className="page-title">Production</h1>
                    <p className="page-subtitle">Make finished goods from raw materials, at cost</p>
                </div>
                <div className="flex gap-2">
                    <button
                        type="button" onClick={() => p.setRecipesOpen(true)} className="btn-secondary"
                        disabled={!canRecipes} style={canRecipes ? undefined : DISABLED}
                        title={canRecipes ? undefined : 'You do not have permission to edit recipes'}
                    >
                        Recipes
                    </button>
                    <button
                        type="button" onClick={() => p.setFormOpen(true)} className="btn-primary"
                        disabled={!canCreate} style={canCreate ? undefined : DISABLED}
                        title={canCreate ? undefined : 'You do not have permission to record production'}
                    >
                        + New Production Run
                    </button>
                </div>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12, gap: 12 }}>
                <div style={{ position: 'relative' }}>
                    <svg
                        style={{ position: 'absolute', left: 11, top: '50%', transform: 'translateY(-50%)', color: '#94a3b8', pointerEvents: 'none' }}
                        width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                    </svg>
                    <input
                        type="text"
                        value={p.query}
                        onChange={(e) => p.setQuery(e.target.value)}
                        placeholder="Search run #, product, material…"
                        aria-label="Search production runs"
                        autoComplete="off"
                        style={{ padding: '8px 14px 8px 34px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13.5, width: 340, outline: 'none' }}
                    />
                </div>
                <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                    {p.query ? `${p.filtered.length} of ${p.runs.length} runs` : `${p.runs.length} runs`}
                </div>
            </div>

            {p.query && p.filtered.length === 0 && p.runs.length > 0
                ? <div className="card card-body text-center text-gray-400">No production runs match that search.</div>
                : <ProductionRunTable runs={p.filtered} onView={p.setViewing} />}

            <ProductionDialogs p={p} />
        </div>
    );
}
