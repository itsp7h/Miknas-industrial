import { useMemo, useState } from 'react';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import Button from '../../../components/ui/Button';
import DeliveryNoteForm from '../../../components/sales/delivery/DeliveryNoteForm';
import useLiveList from '../../../hooks/useLiveList';
import { apiPatch } from '../../../api/client';
import { useToast } from '../../../components/ui/Toast';

const STATUS_COLOURS = { draft: '#64748b', dispatched: '#7c3aed' };
const STATUS_LABELS = { draft: 'Draft', dispatched: 'Dispatched' };

export default function DeliveryNoteListPage() {
    const { items: notes, upsertItem } = useLiveList({
        endpoint: '/sales/delivery-notes',
        channel: 'sales',
        event: '.delivery-note.saved',
        mergeKey: 'id',
        errorMessage: 'Failed to load delivery notes.',
    });
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [dispatching, setDispatching] = useState(null);
    const { showToast } = useToast();

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return notes;
        return notes.filter((n) =>
            [n.delivery_number, n.order_number, n.customer_name, STATUS_LABELS[n.status] ?? n.status]
                .some((field) => String(field ?? '').toLowerCase().includes(q))
        );
    }, [notes, query]);

    function handleSaved(note) {
        upsertItem(note);
        setModalOpen(false);
        showToast('Delivery note created.', 'success');
    }

    async function handleDispatch() {
        const note = dispatching;
        setDispatching(null);
        try {
            const response = await apiPatch(`/sales/delivery-notes/${note.id}/dispatch`);
            upsertItem(response.data);
            showToast(`${note.delivery_number} dispatched.`, 'success');
        } catch (err) {
            showToast(err.message || 'Failed to dispatch that note.', 'error');
        }
    }

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <h1 style={{ fontSize: 18, fontWeight: 700 }}>Delivery Notes</h1>
                <Button onClick={() => setModalOpen(true)}>New</Button>
            </div>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search" value={query} onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search delivery notes…" aria-label="Search delivery notes"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {query ? `${filtered.length} of ${notes.length} notes` : `${notes.length} notes`}
                </div>
            </div>

            {filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {query ? 'No delivery notes match that search.' : 'No delivery notes yet.'}
                </p>
            )}

            {filtered.map((note) => (
                <div key={note.id} style={{ border: '1px solid #e2e8f0', borderRadius: 8, padding: 12, marginBottom: 8 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <span style={{ fontWeight: 600 }}>{note.delivery_number}</span>
                        <span style={{ fontSize: 12, fontWeight: 600, color: STATUS_COLOURS[note.status] }}>
                            {STATUS_LABELS[note.status] ?? note.status}
                        </span>
                    </div>
                    <div style={{ fontSize: 13, color: '#64748b' }}>{note.customer_name ?? '—'}</div>
                    <div style={{ fontSize: 12, color: '#94a3b8' }}>
                        {note.order_number ?? '—'} · {note.delivery_date}
                    </div>
                    {note.status === 'draft' && (
                        <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 8 }}>
                            <Button variant="link" onClick={() => setDispatching(note)}>Dispatch</Button>
                        </div>
                    )}
                </div>
            ))}

            <Modal open={modalOpen} title="New Delivery Note" onClose={() => setModalOpen(false)}>
                <DeliveryNoteForm onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!dispatching}
                title="Dispatch this delivery note?"
                body={dispatching ? `${dispatching.delivery_number} will be dispatched and stock decremented. This cannot be undone.` : ''}
                onConfirm={handleDispatch}
                onCancel={() => setDispatching(null)}
            />
        </div>
    );
}
