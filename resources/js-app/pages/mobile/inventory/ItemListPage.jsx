import { useState } from 'react';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import ItemForm from '../../../components/inventory/item/ItemForm';
import ItemToolbar from '../../../components/inventory/item/ItemToolbar';
import ItemImportModal from '../../../components/inventory/item/ItemImportModal';
import useItemList from '../../../components/inventory/item/useItemList';
import { categoryBadgeClass, categoryLabel, isLow, money, num, warehouseLabel } from '../../../components/inventory/item/itemStyles';

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
    const [actionsOpen, setActionsOpen] = useState(false);

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">{title}</h1>
                <p className="page-subtitle">{subtitle}</p>
            </div>

            {/* Import/export stay behind an Actions sheet so the header does not
                put four buttons across a phone's width. */}
            <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
                <button type="button" onClick={() => setActionsOpen(true)} className="btn btn-secondary" style={{ flex: 1, justifyContent: 'center' }}>
                    Actions
                </button>
                <button type="button" onClick={it.openCreate} className="btn-primary" style={{ flex: 1, justifyContent: 'center' }}>
                    + Add Item
                </button>
            </div>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={it.query}
                    onChange={(e) => it.setQuery(e.target.value)}
                    placeholder="Search code, name, category, unit, warehouse…"
                    aria-label="Search items"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <select
                    aria-label="Filter by warehouse"
                    value={it.warehouseId}
                    onChange={(e) => it.setWarehouseId(e.target.value)}
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                    style={{ marginTop: 8, background: '#fff' }}
                >
                    <option value="">All warehouses</option>
                    {it.warehouses.map((warehouse) => (
                        <option key={warehouse.id} value={warehouse.id}>{warehouse.name}</option>
                    ))}
                </select>
                <select
                    aria-label="Sort items by"
                    value={it.sort}
                    onChange={(e) => it.setSort(e.target.value)}
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                    style={{ marginTop: 8, background: '#fff' }}
                >
                    {it.sortOptions.map((option) => (
                        <option key={option.value} value={option.value}>Sort: {option.label}</option>
                    ))}
                </select>
                {it.sections.length > 0 && (
                    <select
                        aria-label="Filter by section"
                        value={it.sectionId}
                        onChange={(e) => it.setSectionId(e.target.value)}
                        className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                        style={{ marginTop: 8, background: '#fff' }}
                    >
                        <option value="">All sections</option>
                        {it.sections.map((section) => (
                            <option key={section.id} value={section.id}>{section.name}</option>
                        ))}
                    </select>
                )}
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {it.query ? `${it.filtered.length} of ${it.inScope.length} items` : `${it.inScope.length} items`}
                    {it.warehouseId && ` in ${it.warehouses.find((w) => String(w.id) === String(it.warehouseId))?.name}`}
                </div>
            </div>

            {it.filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {it.query ? 'No items match that search.' : 'No items found.'}
                </p>
            )}

            {/* A ten-column table does not fit a phone, so each item is a card. */}
            {it.filtered.map((item) => (
                <div key={item.id} style={{
                    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                    padding: 12, marginBottom: 8,
                }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontWeight: 600, color: '#0f172a', fontSize: 14 }}>{item.item_name}</div>
                            <div className="font-mono" style={{ fontSize: 11, color: '#94a3b8' }}>{item.item_code}</div>
                        </div>
                        <span className={item.is_active ? 'badge-green' : 'badge-gray'} style={{ flexShrink: 0 }}>
                            {item.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </div>

                    <div style={{ marginTop: 6 }}>
                        <span className={categoryBadgeClass(item.category)}>{categoryLabel(item.category)}</span>
                        {item.item_category_name && (
                            <span style={{ fontSize: 11, color: '#94a3b8', marginLeft: 8 }}>{item.item_category_name}</span>
                        )}
                    </div>

                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px 16px', marginTop: 8, fontSize: 12 }}>
                        <div>
                            <div style={{ color: '#94a3b8' }}>UOM</div>
                            <div style={{ fontWeight: 600, color: '#1f2937' }}>{item.unit_of_measure}</div>
                        </div>
                        <div>
                            <div style={{ color: '#94a3b8' }}>Quantity</div>
                            <div style={{ fontWeight: 600, color: isLow(item) ? '#dc2626' : '#1f2937' }}>
                                {num(item.quantity)}
                            </div>
                        </div>
                        <div>
                            <div style={{ color: '#94a3b8' }}>Min Stock</div>
                            <div style={{ fontWeight: 600, color: '#1f2937' }}>{num(item.minimum_stock_level)}</div>
                        </div>
                        <div>
                            <div style={{ color: '#94a3b8' }}>Cost Price</div>
                            <div style={{ fontWeight: 600, color: '#1f2937' }}>{money(item.cost_price)}</div>
                        </div>
                        <div>
                            <div style={{ color: '#94a3b8' }}>Warehouse</div>
                            <div style={{ fontWeight: 600, color: '#1f2937' }}>{warehouseLabel(item)}</div>
                        </div>
                    </div>

                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 10 }}>
                        <button type="button" onClick={() => it.openEdit(item)} className="btn-secondary btn-sm">Edit</button>
                        <button type="button" onClick={() => it.setDeleting(item)} className="btn-danger btn-sm">Delete</button>
                    </div>
                </div>
            ))}

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
            <Modal open={actionsOpen} title="Actions" onClose={() => setActionsOpen(false)}>
                <ItemToolbar
                    onImportClick={() => { setActionsOpen(false); it.setImportOpen(true); }}
                    onCreate={it.openCreate}
                    stacked
                    showAdd={false}
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
