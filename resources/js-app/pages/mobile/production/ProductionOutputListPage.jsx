import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import Modal from '../../../components/ui/Modal';
import OutputForm from '../../../components/production/output/OutputForm';
import useOutputList from '../../../components/production/output/useOutputList';
import { formatDate, num } from '../../../components/production/formatters';

export default function ProductionOutputListPage() {
    const o = useOutputList();
    const [params] = useSearchParams();
    const [formOpen, setFormOpen] = useState(false);

    function handleSaved(output) {
        o.handleSaved(output);
        setFormOpen(false);
    }

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Production Output</h1>
                <p className="page-subtitle">Record finished goods produced</p>
            </div>

            {/* Desktop keeps the form inline under the table; on a phone that is a
                long scroll past every entry, so it opens as a sheet instead. */}
            <button
                type="button" onClick={() => setFormOpen(true)} className="btn-primary"
                style={{ width: '100%', justifyContent: 'center', marginBottom: 14 }}
            >
                + Record Output
            </button>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={o.query}
                    onChange={(e) => o.setQuery(e.target.value)}
                    placeholder="Search order, item, warehouse…"
                    aria-label="Search production output"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {o.query ? `${o.filtered.length} of ${o.outputs.length} entries` : `${o.outputs.length} entries`}
                </div>
            </div>

            {o.filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {o.query ? 'No production output matches that search.' : 'No production outputs recorded.'}
                </p>
            )}

            {o.filtered.map((output) => (
                <div key={output.id} style={{
                    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                    padding: 12, marginBottom: 8,
                }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <div style={{ fontWeight: 600, color: '#0f172a', fontSize: 14, minWidth: 0 }}>
                            {output.item_name ?? ''}
                        </div>
                        {/* Finished goods arriving in the warehouse — read as an increment. */}
                        <div style={{ color: '#16a34a', fontWeight: 700, flexShrink: 0 }}>{num(output.quantity)}</div>
                    </div>

                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5, color: '#64748b', marginTop: 6, gap: 8 }}>
                        <Link to={`/app/production/orders/${output.production_order_id}`} className="text-blue-600 font-mono">
                            {output.production_order_number}
                        </Link>
                        <span>{output.warehouse_name ?? ''}</span>
                    </div>

                    <div style={{ fontSize: 12, color: '#94a3b8', marginTop: 4 }}>
                        {formatDate(output.output_date)}{output.notes ? ` · ${output.notes}` : ''}
                    </div>
                </div>
            ))}

            <Modal open={formOpen} title="Record Production Output" onClose={() => setFormOpen(false)}>
                <OutputForm presetOrderId={params.get('production_order_id')} onSaved={handleSaved} />
            </Modal>
        </div>
    );
}
