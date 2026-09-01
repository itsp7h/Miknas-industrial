import { useSearchParams } from 'react-router-dom';
import OutputForm from '../../../components/production/output/OutputForm';
import OutputTable from '../../../components/production/output/OutputTable';
import useOutputList from '../../../components/production/output/useOutputList';

export default function ProductionOutputListPage() {
    const o = useOutputList();
    // The production order detail page links here with the order preselected,
    // the way Blade's create link carried ?production_order_id=.
    const [params] = useSearchParams();

    function focusForm() {
        document.getElementById('record-production-output')?.scrollIntoView({ behavior: 'smooth' });
        document.getElementById('production_order_id')?.focus();
    }

    return (
        <div>
            <div className="page-header">
                <div>
                    <h1 className="page-title">Production Output</h1>
                    <p className="page-subtitle">Record finished goods produced</p>
                </div>
                {/* The form is on the page, so the header button jumps to it
                    rather than opening a modal. */}
                <button type="button" onClick={focusForm} className="btn-primary">+ Record Output</button>
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
                        value={o.query}
                        onChange={(e) => o.setQuery(e.target.value)}
                        placeholder="Search order, item, warehouse…"
                        aria-label="Search production output"
                        autoComplete="off"
                        style={{ padding: '8px 14px 8px 34px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13.5, width: 340, outline: 'none' }}
                    />
                </div>
                <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                    {o.query ? `${o.filtered.length} of ${o.outputs.length} entries` : `${o.outputs.length} entries`}
                </div>
            </div>

            <OutputTable outputs={o.filtered} />

            <div className="mt-8" id="record-production-output">
                <h2 className="text-lg font-bold text-gray-800 mb-4">Record Production Output</h2>
                <OutputForm presetOrderId={params.get('production_order_id')} onSaved={o.handleSaved} />
            </div>
        </div>
    );
}
