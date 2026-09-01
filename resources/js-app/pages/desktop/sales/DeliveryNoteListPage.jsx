import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import DeliveryNoteForm from '../../../components/sales/delivery/DeliveryNoteForm';
import DeliveryNoteEditForm from '../../../components/sales/delivery/DeliveryNoteEditForm';
import DeliveryNoteTable from '../../../components/sales/delivery/DeliveryNoteTable';
import useDeliveryNoteList from '../../../components/sales/delivery/useDeliveryNoteList';
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
            showToast(`${note.delivery_number} dispatched and stock decremented.`, 'success');
        } catch (err) {
            showToast(err.message || 'Failed to dispatch that note.', 'error');
        }
    }

    return (
        <div>
            <div className="page-header">
                <div>
                    <h1 className="page-title">Delivery Notes</h1>
                    <p className="page-subtitle">Manage goods dispatch to customers</p>
                </div>
                <button type="button" onClick={d.openCreate} className="btn-primary">+ New Delivery Note</button>
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
                        value={d.query}
                        onChange={(e) => d.setQuery(e.target.value)}
                        placeholder="Search DN #, order, customer…"
                        aria-label="Search delivery notes"
                        autoComplete="off"
                        style={{ padding: '8px 14px 8px 34px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13.5, width: 340, outline: 'none' }}
                    />
                </div>
                <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                    {d.query ? `${d.filtered.length} of ${d.notes.length} notes` : `${d.notes.length} notes`}
                </div>
            </div>

            <DeliveryNoteTable
                notes={d.filtered}
                onDispatch={d.setDispatching}
                onEdit={d.openEdit}
                onDelete={d.setDeleting}
            />

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
                    ? `${d.dispatching.delivery_number} will be dispatched, stock will be decremented at ${d.dispatching.warehouse_name}, and the customer is notified if they have a WhatsApp number. This cannot be undone.`
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
