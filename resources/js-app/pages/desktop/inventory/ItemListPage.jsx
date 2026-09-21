import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import ItemForm from '../../../components/inventory/item/ItemForm';
import ItemTable from '../../../components/inventory/item/ItemTable';
import ItemToolbar from '../../../components/inventory/item/ItemToolbar';
import ItemImportModal from '../../../components/inventory/item/ItemImportModal';
import useItemList from '../../../components/inventory/item/useItemList';

/**
 * One page, two entries: Raw Materials and Finished Goods show the same
 * table over different slices of items.category. A page named after a type
 * that listed every type was the complaint that started this.
 */
export default function ItemListPage({
    category = 'raw_material',
    title = 'Raw Materials',
    subtitle = 'Materials bought and consumed in production',
}) {
    const it = useItemList(category);

    return (
        <div>
            <div className="page-header">
                <div>
                    <h1 className="page-title">{title}</h1>
                    <p className="page-subtitle">{subtitle}</p>
                </div>
                <ItemToolbar onImportClick={() => it.setImportOpen(true)} onCreate={it.openCreate} />
            </div>

            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12, gap: 12, flexWrap: 'wrap' }}>
                {/* Search and the warehouse filter read as one control group. */}
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
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
                            placeholder="Search code, name, category, unit, warehouse…"
                            aria-label="Search items"
                            autoComplete="off"
                            style={{ padding: '8px 14px 8px 34px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13.5, width: 340, outline: 'none' }}
                        />
                    </div>

                    {/* Picking a warehouse re-scopes every row's quantity to it, which
                        is what makes a row here the same thing as a stock-summary line. */}
                    <select
                        aria-label="Filter by warehouse"
                        value={it.warehouseId}
                        onChange={(e) => it.setWarehouseId(e.target.value)}
                        style={{ padding: '8px 12px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13.5, outline: 'none', background: '#fff' }}
                    >
                        <option value="">All warehouses</option>
                        {it.warehouses.map((warehouse) => (
                            <option key={warehouse.id} value={warehouse.id}>{warehouse.name}</option>
                        ))}
                    </select>

                    {/* With six sections averaging eight items each, this is how
                        the page is navigated — group headings would be overkill. */}
                    {it.sections.length > 0 && (
                        <select
                            aria-label="Filter by section"
                            value={it.sectionId}
                            onChange={(e) => it.setSectionId(e.target.value)}
                            style={{ padding: '8px 12px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13.5, outline: 'none', background: '#fff' }}
                        >
                            <option value="">All sections</option>
                            {it.sections.map((section) => (
                                <option key={section.id} value={section.id}>{section.name}</option>
                            ))}
                        </select>
                    )}
                </div>
                <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                    {it.query ? `${it.filtered.length} of ${it.inScope.length} items` : `${it.inScope.length} items`}
                    {it.warehouseId && ` in ${it.warehouses.find((w) => String(w.id) === String(it.warehouseId))?.name}`}
                </div>
            </div>

            <ItemTable items={it.filtered} onEdit={it.openEdit} onDelete={it.setDeleting} />

            <Modal
                open={it.modalOpen}
                title={it.editing ? `Edit ${it.editing.item_name}` : 'New Item'}
                onClose={() => it.setModalOpen(false)}
            >
                <ItemForm
                    item={it.editing}
                    categoryOptions={it.categoryOptions}
                    warehouses={it.allWarehouses}
                    defaultCategory={category}
                    onSaved={it.handleSaved}
                    onCancel={() => it.setModalOpen(false)}
                />
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
