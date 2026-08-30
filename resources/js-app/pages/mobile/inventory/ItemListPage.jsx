import { useMemo, useRef, useState } from 'react';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import Button from '../../../components/ui/Button';
import ItemForm, { CATEGORIES } from '../../../components/inventory/item/ItemForm';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete, apiPostForm } from '../../../api/client';
import { useToast } from '../../../components/ui/Toast';

const CATEGORY_LABELS = Object.fromEntries(CATEGORIES.map((c) => [c.value, c.label]));

export default function ItemListPage() {
    const { items, upsertItem, removeItem, refetch } = useLiveList({
        endpoint: '/inventory/items',
        channel: 'inventory',
        event: '.item.saved',
        deleteEvent: '.item.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load items.',
    });
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const [actionsOpen, setActionsOpen] = useState(false);
    const fileInputRef = useRef(null);
    const { showToast } = useToast();

    // Client-side over the whole list (CLAUDE.md gotcha #6) — the endpoint is
    // deliberately unpaginated so search covers every item.
    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return items;
        return items.filter((item) =>
            [item.item_code, item.item_name, CATEGORY_LABELS[item.category] ?? item.category]
                .some((field) => String(field ?? '').toLowerCase().includes(q))
        );
    }, [items, query]);

    function handleSaved(item) {
        upsertItem(item);
        setModalOpen(false);
        showToast('Item saved.', 'success');
    }

    async function handleDeleteConfirmed() {
        const item = deleting;
        setDeleting(null);
        try {
            const result = await apiDelete(`/inventory/items/${item.id}`);
            if (result.deactivated) {
                showToast(result.message, 'info');
                await refetch();
            } else {
                removeItem(item.id);
                showToast('Item deleted.', 'success');
            }
        } catch (err) {
            showToast(err.message || 'Failed to delete item.', 'error');
        }
    }

    async function handleImport(e) {
        const file = e.target.files?.[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('file', file);
        try {
            const result = await apiPostForm('/inventory/items/import', formData);
            showToast(`${result.imported} added, ${result.skipped} skipped.`, 'success');
            await refetch();
        } catch (err) {
            showToast(err.message || 'Failed to import items.', 'error');
        } finally {
            if (fileInputRef.current) fileInputRef.current.value = '';
            setActionsOpen(false);
        }
    }

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <h1 style={{ fontSize: 18, fontWeight: 700 }}>Items</h1>
                <div style={{ display: 'flex', gap: 8 }}>
                    <Button variant="secondary" onClick={() => setActionsOpen(true)}>Actions</Button>
                    <Button onClick={() => { setEditing(null); setModalOpen(true); }}>New</Button>
                </div>
            </div>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search items…"
                    aria-label="Search items"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {query ? `${filtered.length} of ${items.length} items` : `${items.length} items`}
                </div>
            </div>

            {filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {query ? 'No items match that search.' : 'No items yet.'}
                </p>
            )}

            {filtered.map((item) => (
                <div key={item.id} style={{ border: '1px solid #e2e8f0', borderRadius: 8, padding: 12, marginBottom: 8 }}>
                    <div onClick={() => { setEditing(item); setModalOpen(true); }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                            <span style={{ fontWeight: 600 }}>{item.item_name}</span>
                            <span style={{ fontSize: 12, color: '#64748b' }}>{item.item_code}</span>
                        </div>
                        <div style={{ fontSize: 13, color: '#64748b' }}>
                            {CATEGORY_LABELS[item.category] ?? item.category} · {item.unit_of_measure}
                        </div>
                        <div style={{ fontSize: 12, color: item.is_active ? '#16a34a' : '#dc2626' }}>
                            {item.is_active ? 'Active' : 'Inactive'}
                        </div>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 8 }}>
                        <Button variant="link-danger" onClick={() => setDeleting(item)}>Delete</Button>
                    </div>
                </div>
            ))}

            <Modal open={actionsOpen} title="Actions" onClose={() => setActionsOpen(false)}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                    <label style={{ cursor: 'pointer' }} className="text-sm text-blue-600">
                        Import from Excel
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".xlsx,.xls"
                            style={{ display: 'none' }}
                            onChange={handleImport}
                            aria-label="Import"
                        />
                    </label>
                    <a className="text-sm text-blue-600" href="/api/v1/inventory/items/template">Download Template</a>
                    <a className="text-sm text-blue-600" href="/api/v1/inventory/items/export-pdf">Export PDF</a>
                </div>
            </Modal>

            <Modal open={modalOpen} title={editing ? 'Edit Item' : 'New Item'} onClose={() => setModalOpen(false)}>
                <ItemForm item={editing} onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!deleting}
                title="Delete item?"
                body={deleting ? `This will permanently remove "${deleting.item_name}". Items with stock history are deactivated instead.` : ''}
                onConfirm={handleDeleteConfirmed}
                onCancel={() => setDeleting(null)}
            />
        </div>
    );
}
