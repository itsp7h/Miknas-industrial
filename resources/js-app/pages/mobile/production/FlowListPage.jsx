import { useMemo, useState } from 'react';
import Modal from '../../../components/ui/Modal';
import Button from '../../../components/ui/Button';
import FlowForm from '../../../components/production/FlowForm';
import { qty } from '../../../components/production/statuses';
import useLiveList from '../../../hooks/useLiveList';
import { useToast } from '../../../components/ui/Toast';

const CONFIG = {
    'material-issue': {
        title: 'Material Issues',
        endpoint: '/production/material-issues',
        event: '.material-issue.recorded',
        dateKey: 'issue_date',
        numberKey: 'issue_number',
        newLabel: 'Issue Material',
        tone: '#dc2626',
        noun: 'issues',
    },
    'production-output': {
        title: 'Production Output',
        endpoint: '/production/outputs',
        event: '.production-output.recorded',
        dateKey: 'output_date',
        numberKey: null,
        newLabel: 'Record Output',
        tone: '#16a34a',
        noun: 'entries',
    },
};

export default function FlowListPage({ kind }) {
    const config = CONFIG[kind];
    const { items: rows, upsertItem } = useLiveList({
        endpoint: config.endpoint,
        channel: 'production',
        event: config.event,
        mergeKey: 'id',
        errorMessage: `Failed to load ${config.title.toLowerCase()}.`,
    });
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const { showToast } = useToast();

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return rows;
        return rows.filter((r) =>
            [r.item_name, r.warehouse_name, r.production_order_number, r[config.numberKey ?? 'id']]
                .some((field) => String(field ?? '').toLowerCase().includes(q))
        );
    }, [rows, query, config.numberKey]);

    function handleSaved(row) {
        upsertItem(row);
        setModalOpen(false);
        showToast(`${config.title.replace(/s$/, '')} recorded.`, 'success');
    }

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <h1 style={{ fontSize: 18, fontWeight: 700 }}>{config.title}</h1>
                <Button onClick={() => setModalOpen(true)}>New</Button>
            </div>

            <div style={{ marginBottom: 12 }}>
                <input type="search" value={query} onChange={(e) => setQuery(e.target.value)}
                    placeholder={`Search ${config.title.toLowerCase()}…`} aria-label={`Search ${config.title.toLowerCase()}`}
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full" />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {query ? `${filtered.length} of ${rows.length} ${config.noun}` : `${rows.length} ${config.noun}`}
                </div>
            </div>

            {filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {query ? `No ${config.noun} match that search.` : `No ${config.title.toLowerCase()} yet.`}
                </p>
            )}

            {filtered.map((row) => (
                <div key={row.id} style={{ border: '1px solid #e2e8f0', borderRadius: 8, padding: 12, marginBottom: 8 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <span style={{ fontWeight: 600 }}>{row.item_name ?? '—'}</span>
                        <span style={{ color: config.tone, fontWeight: 700 }}>{qty(row.quantity)}</span>
                    </div>
                    <div style={{ fontSize: 13, color: '#64748b' }}>
                        {row.production_order_number ?? '—'} · {row.warehouse_name ?? '—'}
                    </div>
                    <div style={{ fontSize: 12, color: '#94a3b8' }}>
                        {row[config.dateKey]}{config.numberKey && row[config.numberKey] ? ` · ${row[config.numberKey]}` : ''}
                    </div>
                </div>
            ))}

            <Modal open={modalOpen} title={config.newLabel} onClose={() => setModalOpen(false)}>
                <FlowForm kind={kind} onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
        </div>
    );
}
