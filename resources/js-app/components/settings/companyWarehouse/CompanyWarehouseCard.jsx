import useCompanyWarehouse from './useCompanyWarehouse';

const CARD = {
    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 14,
    padding: 20, maxWidth: 720,
};

const ROW = {
    display: 'grid', gridTemplateColumns: 'minmax(0,1fr) minmax(0,15rem)',
    gap: 12, alignItems: 'center', padding: '10px 0', borderTop: '1px solid #f1f5f9',
};

const HEAD = {
    fontSize: 10.5, fontWeight: 700, color: '#94a3b8',
    textTransform: 'uppercase', letterSpacing: '.05em',
};

/**
 * Where each company's purchases are received.
 *
 * A request names its company, so by the time its goods arrive the yard is
 * already decided — this is where that decision is recorded, and the goods
 * receipt form then stops asking. A company left unlinked keeps the old
 * behaviour: the receipt offers every warehouse.
 */
export default function CompanyWarehouseCard({ maxWidth }) {
    const w = useCompanyWarehouse();

    if (w.loading) {
        return <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>;
    }

    return (
        <div style={{ ...CARD, ...(maxWidth ? { maxWidth } : {}) }}>
            <h2 style={{ fontSize: 14, fontWeight: 700, color: '#0f172a', margin: '0 0 4px' }}>
                Company Warehouses
            </h2>
            <p style={{ fontSize: 12.5, color: '#64748b', margin: '0 0 14px' }}>
                Goods bought against a company&rsquo;s request are received into that
                company&rsquo;s warehouse. Leave a company unset to choose the warehouse on
                each receipt instead.
            </p>

            <div style={{ ...ROW, borderTop: 'none', paddingTop: 0 }}>
                <span style={HEAD}>Company</span>
                <span style={HEAD}>Receiving Warehouse</span>
            </div>

            {w.companies.map((company) => (
                <div key={company.id} style={ROW}>
                    <span style={{ fontSize: 13, color: '#0f172a', fontWeight: 500 }}>
                        {company.name}
                        {!company.is_active && (
                            <span style={{ fontSize: 11, color: '#94a3b8' }}> · inactive</span>
                        )}
                    </span>
                    <select
                        className="form-select"
                        aria-label={`${company.name} receiving warehouse`}
                        value={w.links[company.id] ?? ''}
                        onChange={(e) => w.setLink(company.id, e.target.value)}
                        style={{ width: '100%' }}
                    >
                        <option value="">— No warehouse —</option>
                        {w.warehouses.map((warehouse) => (
                            <option key={warehouse.id} value={warehouse.id}>
                                {warehouse.name}
                                {!warehouse.is_active ? ' (inactive)' : ''}
                            </option>
                        ))}
                    </select>
                </div>
            ))}

            {w.companies.length === 0 && (
                <p style={{ fontSize: 13, color: '#94a3b8', padding: '12px 0 0' }}>
                    No companies yet. Add one under Companies &amp; Departments first.
                </p>
            )}

            {w.warehouses.length === 0 && w.companies.length > 0 && (
                <p style={{ fontSize: 13, color: '#94a3b8', padding: '12px 0 0' }}>
                    No warehouses yet. Add one under Inventory &rarr; Warehouses first.
                </p>
            )}

            {w.error && (
                <p role="alert" style={{ color: '#dc2626', fontSize: 12, marginTop: 12 }}>{w.error}</p>
            )}

            {w.companies.length > 0 && (
                <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 16 }}>
                    <button
                        type="button" className="btn-primary"
                        onClick={w.save} disabled={w.saving || !w.dirty}
                    >
                        {w.saving ? 'Saving…' : 'Save'}
                    </button>
                </div>
            )}
        </div>
    );
}
