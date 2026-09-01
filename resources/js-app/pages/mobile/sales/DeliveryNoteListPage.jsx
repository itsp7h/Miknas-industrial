import { Link } from 'react-router-dom';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import DeliveryNoteForm from '../../../components/sales/delivery/DeliveryNoteForm';
import DeliveryNoteEditForm from '../../../components/sales/delivery/DeliveryNoteEditForm';
import useDeliveryNoteList from '../../../components/sales/delivery/useDeliveryNoteList';
import { badgeClassFor, statusLabel } from '../../../components/sales/delivery/statuses';
import { formatDate } from '../../../components/sales/order/statuses';
import { apiPatch } from '../../../api/client';
import { useToast } from '../../../components/ui/Toast';

export default function DeliveryNoteListPage() {
    const d = useDeliveryNoteList();
    const { showToast } = useToast();

    async function handleDispatch() {
        const note = d.dispatching;
        d.setDispatching(null);
        try {
            const response = await apiPatch(`/sales/delivery-notes/${note.id}/dispatch`);
            d.upsertItem(response.data);
            showToast(`${note.delivery_number} dispatched.`, 'success');
        } catch (err) {
            showToast(err.message || 'Failed to dispatch that note.', 'error');
        }
    }

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Delivery Notes</h1>
                <p className="page-subtitle">Manage goods dispatch to customers</p>
            </div>

            <button
                type="button" onClick={d.openCreate} className="btn-primary"
                style={{ width: '100%', justifyContent: 'center', marginBottom: 14 }}
            >
                + New Delivery Note
            </button>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={d.query}
                    onChange={(e) => d.setQuery(e.target.value)}
                    placeholder="Search DN #, order, customer…"
                    aria-label="Search delivery notes"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {d.query ? `${d.filtered.length} of ${d.notes.length} notes` : `${d.notes.length} notes`}
                </div>
            </div>

            {d.filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {d.query ? 'No delivery notes match that search.' : 'No delivery notes found.'}
                </p>
            )}

            {d.filtered.map((note) => (
                <div key={note.id} style={{
                    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                    padding: 12, marginBottom: 8,
                }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontWeight: 600, color: '#0f172a', fontSize: 14 }}>{note.customer_name ?? ''}</div>
                            <div className="font-mono" style={{ fontSize: 11, color: '#94a3b8' }}>{note.delivery_number}</div>
                        </div>
                        <span className={badgeClassFor(note.status)} style={{ flexShrink: 0 }}>{statusLabel(note.status)}</span>
                    </div>

                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5, color: '#64748b', marginTop: 6, gap: 8 }}>
                        <Link to={`/app/sales/orders/${note.sales_order_id}`} className="text-blue-600 font-mono">
                            {note.order_number}
                        </Link>
                        <span>{note.warehouse_name ?? ''}</span>
                    </div>

                    <div style={{ fontSize: 12, color: '#94a3b8', marginTop: 4 }}>{formatDate(note.delivery_date)}</div>

                    {note.status === 'draft' && (
                        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 10, flexWrap: 'wrap' }}>
                            <button type="button" onClick={() => d.setDispatching(note)} className="btn-success btn-sm">Dispatch</button>
                            <button type="button" onClick={() => d.openEdit(note)} className="btn-secondary btn-sm">Edit</button>
                            <button type="button" onClick={() => d.setDeleting(note)} className="btn-danger btn-sm">Delete</button>
                        </div>
                    )}
                </div>
            ))}

            <Modal
                open={d.modalOpen}
                title={d.editing ? `Edit ${d.editing.delivery_number}` : 'New Delivery Note'}
                onClose={() => d.setModalOpen(false)}
            >
                {d.editing
                    ? <DeliveryNoteEditForm note={d.editing} onSaved={d.handleSaved} onCancel={() => d.setModalOpen(false)} />
                    : <DeliveryNoteForm presetOrderId={d.presetOrderId} onSaved={d.handleSaved} onCancel={() => d.setModalOpen(false)} />}
            </Modal>
            <ConfirmModal
                open={!!d.dispatching}
                title="Dispatch this delivery note?"
                body={d.dispatching
                    ? `${d.dispatching.delivery_number} will be dispatched and stock decremented at ${d.dispatching.warehouse_name}. This cannot be undone.`
                    : ''}
                onConfirm={handleDispatch}
                onCancel={() => d.setDispatching(null)}
            />
            <ConfirmModal
                open={!!d.deleting}
                title="Delete this delivery note?"
                body={d.deleting ? `${d.deleting.delivery_number} will be permanently removed.` : ''}
                onConfirm={d.handleDelete}
                onCancel={() => d.setDeleting(null)}
            />
        </div>
    );
}
