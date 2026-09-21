import ConfirmModal from '../../ui/ConfirmModal';
import Button from '../../ui/Button';
import useItemCategories from './useItemCategories';

/**
 * The sections an item can be filed under — the second half of
 * "Raw Materials / Chemical Materials".
 *
 * One tree with a `compact` flag rather than a desktop/mobile pair: the form is
 * four fields, and two copies of it would drift the way the MPR forms did.
 */
export default function ItemCategoryCard({ compact = false }) {
    const c = useItemCategories();

    return (
        <div>
            <div style={{
                background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                padding: compact ? 12 : 16, marginBottom: 16,
            }}>
                <h2 style={{ fontSize: 15, fontWeight: 600, color: '#0f172a', marginBottom: 12 }}>
                    {c.editing ? `Rename ${c.editing.name}` : 'Add a category'}
                </h2>

                <div style={{
                    display: 'flex', gap: 10, flexWrap: 'wrap',
                    flexDirection: compact ? 'column' : 'row', alignItems: compact ? 'stretch' : 'flex-end',
                }}>
                    <div style={{ flex: compact ? undefined : 2, minWidth: 0 }}>
                        <label htmlFor="ic-name" className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input
                            id="ic-name"
                            value={c.values.name}
                            onChange={(e) => c.setField('name', e.target.value)}
                            placeholder="Chemical Materials"
                            className={`border rounded-md px-3 py-2 text-sm w-full ${c.errors.name ? 'border-red-400' : 'border-gray-300'}`}
                        />
                        {c.errors.name && <p className="text-sm text-red-600 mt-1">{c.errors.name}</p>}
                    </div>

                    <div style={{ flex: compact ? undefined : 1, minWidth: 0 }}>
                        <label htmlFor="ic-parent" className="block text-sm font-medium text-gray-700 mb-1">Belongs to</label>
                        <select
                            id="ic-parent"
                            value={c.values.parent_type}
                            onChange={(e) => c.setField('parent_type', e.target.value)}
                            className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                        >
                            {c.parentTypes.map((type) => (
                                <option key={type.value} value={type.value}>{type.label}</option>
                            ))}
                        </select>
                    </div>

                    <div style={{ width: compact ? undefined : 90 }}>
                        <label htmlFor="ic-order" className="block text-sm font-medium text-gray-700 mb-1">Order</label>
                        <input
                            id="ic-order"
                            type="number"
                            min="0"
                            value={c.values.sort_order}
                            onChange={(e) => c.setField('sort_order', e.target.value)}
                            className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                        />
                    </div>

                    <div style={{ display: 'flex', gap: 8 }}>
                        <Button onClick={c.save} loading={c.saving}>{c.editing ? 'Save' : 'Add'}</Button>
                        {c.editing && <Button variant="secondary" onClick={c.openNew}>Cancel</Button>}
                    </div>
                </div>
            </div>

            {c.categories.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>No categories yet.</p>
            )}

            {c.categories.map((category) => (
                <div
                    key={category.id}
                    style={{
                        background: '#fff', border: '1px solid #e2e8f0', borderRadius: 10,
                        padding: 12, marginBottom: 8,
                        display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12,
                    }}
                >
                    <div style={{ minWidth: 0 }}>
                        <div style={{ fontWeight: 600, color: '#0f172a', fontSize: 14 }}>{category.path}</div>
                        <div style={{ fontSize: 12, color: '#94a3b8', marginTop: 2 }}>
                            {category.items_count === 1 ? '1 item' : `${category.items_count ?? 0} items`}
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 8, flexShrink: 0 }}>
                        <button type="button" onClick={() => c.openEdit(category)} className="btn-secondary btn-sm">Edit</button>
                        <button type="button" onClick={() => c.setDeleting(category)} className="btn-danger btn-sm">Delete</button>
                    </div>
                </div>
            ))}

            <ConfirmModal
                open={!!c.deleting}
                title="Delete this category?"
                body={c.deleting
                    ? `"${c.deleting.path}" will be removed. A category still holding items cannot be deleted — move them first.`
                    : ''}
                onConfirm={c.confirmDelete}
                onCancel={() => c.setDeleting(null)}
            />
        </div>
    );
}
