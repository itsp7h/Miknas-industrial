import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import ItemForm from '../../../components/inventory/item/ItemForm';
import ItemTable from '../../../components/inventory/item/ItemTable';
import ItemToolbar from '../../../components/inventory/item/ItemToolbar';
import ItemImportModal from '../../../components/inventory/item/ItemImportModal';
import useItemList from '../../../components/inventory/item/useItemList';

export default function ItemListPage() {
    const it = useItemList();

    return (
        <div>
            <div className="page-header">
                <div>
                    <h1 className="page-title">Inventory Items</h1>
                    <p className="page-subtitle">Manage all stock items</p>
                </div>
                <ItemToolbar onImportClick={() => it.setImportOpen(true)} onCreate={it.openCreate} />
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
                        value={it.query}
                        onChange={(e) => it.setQuery(e.target.value)}
                        placeholder="Search code, name, category, unit…"
                        aria-label="Search items"
                        autoComplete="off"
                        style={{ padding: '8px 14px 8px 34px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13.5, width: 340, outline: 'none' }}
                    />
                </div>
                <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                    {it.query ? `${it.filtered.length} of ${it.items.length} items` : `${it.items.length} items`}
                </div>
            </div>

            <ItemTable items={it.filtered} onEdit={it.openEdit} onDelete={it.setDeleting} />

            <Modal
                open={it.modalOpen}
                title={it.editing ? `Edit ${it.editing.item_name}` : 'New Item'}
                onClose={() => it.setModalOpen(false)}
            >
                <ItemForm item={it.editing} onSaved={it.handleSaved} onCancel={() => it.setModalOpen(false)} />
            </Modal>
            <ItemImportModal
                open={it.importOpen}
                onClose={() => it.setImportOpen(false)}
                onImport={it.handleImport}
            />
            <ConfirmModal
                open={!!it.deleting}
                title="Delete this item?"
                body={it.deleting
                    ? `"${it.deleting.item_name}" will be permanently removed. An item with stock history is deactivated instead.`
                    : ''}
                onConfirm={it.handleDeleteConfirmed}
                onCancel={() => it.setDeleting(null)}
            />
        </div>
    );
}
