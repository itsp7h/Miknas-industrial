import { useMemo, useRef, useState } from 'react';
import Card from '../../../components/ui/Card';
import Table from '../../../components/ui/Table';
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
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const fileInputRef = useRef(null);
    const { showToast } = useToast();

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
                // Items with stock history are deactivated, not removed — keep
                // the row and let the broadcast refresh it.
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
        }
    }

    const columns = useMemo(
        () => [
            { key: 'item_code', label: 'Code' },
            { key: 'item_name', label: 'Name' },
            { key: 'category', label: 'Category', render: (row) => CATEGORY_LABELS[row.category] ?? row.category },
            { key: 'unit_of_measure', label: 'Unit' },
            { key: 'minimum_stock_level', label: 'Min. Stock' },
            { key: 'cost_price', label: 'Cost' },
            { key: 'is_active', label: 'Active', render: (row) => (row.is_active ? 'Yes' : 'No') },
            {
                key: 'actions',
                label: '',
                render: (row) => (
                    <div style={{ display: 'flex', gap: 8 }}>
                        <Button variant="link" onClick={() => { setEditing(row); setModalOpen(true); }}>Edit</Button>
                        <Button variant="link-danger" onClick={() => setDeleting(row)}>Delete</Button>
                    </div>
                ),
            },
        ],
        []
    );

    return (
        <Card title="Items">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8, marginBottom: 12 }}>
                <div style={{ display: 'flex', gap: 12, alignItems: 'center' }}>
                    <label style={{ cursor: 'pointer' }} className="text-sm text-blue-600 hover:text-blue-800">
                        Import
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".xlsx,.xls"
                            style={{ display: 'none' }}
                            onChange={handleImport}
                            aria-label="Import"
                        />
                    </label>
                    <a className="text-sm text-blue-600 hover:text-blue-800" href="/api/v1/inventory/items/template">Download Template</a>
                    <a className="text-sm text-blue-600 hover:text-blue-800" href="/api/v1/inventory/items/export-pdf">Export PDF</a>
                </div>
                <Button onClick={() => { setEditing(null); setModalOpen(true); }}>New Item</Button>
            </div>

            <Table columns={columns} rows={items} rowKey={(row) => row.id} searchPlaceholder="Search items…" />

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
        </Card>
    );
}
