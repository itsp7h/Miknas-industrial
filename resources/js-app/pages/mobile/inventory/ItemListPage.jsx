import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import ItemForm from '../../../components/inventory/item/ItemForm';
import ItemImportModal from '../../../components/inventory/item/ItemImportModal';
import useItemList from '../../../components/inventory/item/useItemList';
import {
    actualPriceSource, isLow, money, num, priceGap, priceLabel, warehouseLabel,
} from '../../../components/inventory/item/itemStyles';
import {
    ActionSheet, Card, EmptyState, Hero, HeroButton, MobilePage, SearchField, Segmented,
} from '../../../components/mobile/ui';
import Icon from '../../../components/mobile/icons';
import { C, MONO } from '../../../components/mobile/theme';
import { useAccess } from '../../../layouts/AccessContext';

// The Inventory tab (SteelERP-Mobile-Designs-V2): raw materials and finished
// goods under one header, each on its own URL. A row shows what is on hand and
// what it costs; tapping it offers the rest of the item and edit / delete.
// Sort, warehouse and section sit behind the filter button.

const TABS = [
    { key: 'raw_material', label: 'Raw materials', to: '/app/inventory/items', permission: 'raw-materials.view' },
    { key: 'finished_good', label: 'Finished goods', to: '/app/inventory/finished-goods', permission: 'finished-goods.view' },
];

const deny = (what) => `You do not have permission to ${what}`;

export default function ItemListPage({ category = 'raw_material' }) {
    const it = useItemList(category);
    const navigate = useNavigate();
    const { can } = useAccess();
    const [picked, setPicked] = useState(null);
    const [filters, setFilters] = useState(false);
    const [tools, setTools] = useState(false);

    // The other tab's count comes from the same loaded list: the hook fetches
    // every item and slices it.
    const counts = Object.fromEntries(TABS.map((t) => [t.key, it.allItems.filter((x) => x.category === t.key).length]));
    const tabs = TABS.filter((t) => can(t.permission));
    const low = it.inScope.filter(isLow).length;
    const warehouseName = it.warehouseId
        ? it.warehouses.find((w) => String(w.id) === String(it.warehouseId))?.name
        : null;
    const sortLabel = it.sortOptions.find((o) => o.value === it.sort)?.label;
    const filtering = !!it.warehouseId || !!it.sectionId || it.sort !== 'name';
    const editPerm = category === 'raw_material' ? 'raw-materials' : 'finished-goods';

    useEffect(() => { setPicked(null); }, [category]);

    return (
        <MobilePage gap={14}>
            <Hero
                zone="inventory"
                variant="root"
                eyebrow={warehouseName ?? 'All warehouses'}
                title="Inventory"
                action={(
                    <div style={{ display: 'flex', gap: 10 }}>
                        <button
                            type="button" aria-label="Adjust stock" title="Adjust stock"
                            onClick={() => navigate('/app/inventory/movements?new=1')}
                            disabled={!can('stock-movements.create')}
                            style={{
                                width: 44, height: 44, borderRadius: 22, border: '1px solid rgba(255,255,255,0.35)',
                                background: 'rgba(255,255,255,0.16)', color: '#FFFFFF', display: 'flex',
                                alignItems: 'center', justifyContent: 'center', cursor: 'pointer',
                                opacity: can('stock-movements.create') ? 1 : 0.5,
                            }}
                        >
                            <Icon name="swap" size={22} />
                        </button>
                        <HeroButton
                            zone="inventory" label="Add item" onClick={it.openCreate}
                            disabled={!can(`${editPerm}.create`)}
                            title={can(`${editPerm}.create`) ? 'Add item' : deny('add items')}
                        />
                    </div>
                )}
            />

            {tabs.length > 1 && (
                <Segmented
                    ariaLabel="Item type"
                    value={category}
                    onChange={(key) => navigate(TABS.find((t) => t.key === key).to)}
                    options={tabs.map((t) => ({ key: t.key, label: `${t.label} · ${counts[t.key]}` }))}
                />
            )}

            <SearchField
                value={it.query} onChange={it.setQuery} placeholder="Search items"
                onFilter={() => setFilters(true)} filterActive={filtering}
            />

            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12, fontSize: 14, color: C.muted, padding: '0 4px' }}>
                <span>
                    {it.query ? `${it.filtered.length} of ${it.inScope.length}` : it.inScope.length} item{it.inScope.length === 1 ? '' : 's'} · {sortLabel}
                </span>
                <span style={{ color: low ? '#B91C1C' : C.muted, fontWeight: low ? 600 : 400 }}>{low} below minimum</span>
            </div>

            {it.filtered.length === 0 ? (
                <EmptyState icon="box" title={it.query ? 'No items match that search' : 'No items here yet'} />
            ) : (
                <Card>
                    {it.filtered.map((item, i) => {
                        const short = isLow(item);

                        return (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => setPicked(item)}
                                style={{
                                    display: 'flex', alignItems: 'center', gap: 12, padding: '14px 16px', width: '100%',
                                    border: 0, borderBottom: i === it.filtered.length - 1 ? 0 : `1px solid ${C.hairline}`,
                                    background: 'none', textAlign: 'left', font: 'inherit', color: C.text, cursor: 'pointer',
                                    opacity: item.is_active ? 1 : 0.55,
                                }}
                            >
                                <span style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', gap: 3 }}>
                                    <span style={{ fontFamily: MONO, fontSize: 13, color: C.faint }}>{item.item_code}</span>
                                    <span style={{ fontSize: 16, fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                        {item.item_name}
                                    </span>
                                    <span style={{ fontSize: 14, color: C.muted }}>
                                        {warehouseLabel(item) === '—' ? 'No warehouse' : warehouseLabel(item)}
                                        {!item.is_active && ' · Inactive'}
                                    </span>
                                </span>
                                <span style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 4, flexShrink: 0 }}>
                                    <span style={{ fontSize: 17, fontWeight: 700, color: short ? '#B91C1C' : C.text }}>
                                        {num(item.quantity)}{' '}
                                        <span style={{ fontSize: 13, fontWeight: 500, color: short ? '#B91C1C' : C.muted }}>{item.unit_of_measure}</span>
                                    </span>
                                    <span style={{ fontSize: 14, color: C.muted }}>
                                        {Number(item.cost_price) > 0 ? money(item.cost_price) : 'No price yet'}
                                    </span>
                                </span>
                            </button>
                        );
                    })}
                </Card>
            )}

            <button
                type="button" onClick={() => setTools(true)}
                style={{
                    alignSelf: 'center', background: 'none', border: 0, color: C.accent, font: 'inherit',
                    fontSize: 15, fontWeight: 500, padding: 8, cursor: 'pointer',
                }}
            >
                Import, template &amp; PDF
            </button>

            <Modal open={filters} title="Sort & filter" onClose={() => setFilters(false)}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <div>
                        <label className="form-label" htmlFor="items-sort">Sort by</label>
                        <select id="items-sort" aria-label="Sort items by" className="form-select" value={it.sort} onChange={(e) => it.setSort(e.target.value)}>
                            {it.sortOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="form-label" htmlFor="items-warehouse">Warehouse</label>
                        <select id="items-warehouse" aria-label="Filter by warehouse" className="form-select" value={it.warehouseId} onChange={(e) => it.setWarehouseId(e.target.value)}>
                            <option value="">All warehouses</option>
                            {it.warehouses.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
                        </select>
                    </div>
                    {it.sections.length > 0 && (
                        <div>
                            <label className="form-label" htmlFor="items-section">Section</label>
                            <select id="items-section" aria-label="Filter by section" className="form-select" value={it.sectionId} onChange={(e) => it.setSectionId(e.target.value)}>
                                <option value="">All sections</option>
                                {it.sections.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                        </div>
                    )}
                    <button type="button" className="btn btn-primary" style={{ justifyContent: 'center' }} onClick={() => setFilters(false)}>
                        Done
                    </button>
                </div>
            </Modal>

            <ActionSheet
                open={!!picked}
                onClose={() => setPicked(null)}
                title={picked ? [
                    picked.item_name,
                    `Min ${num(picked.minimum_stock_level)} ${picked.unit_of_measure ?? ''}`.trim(),
                    `${priceLabel(category)} ${money(picked.cost_price)}`,
                    category === 'raw_material' && picked.actual_price
                        ? `Actual ${money(picked.actual_price.price)}${priceGap(picked) ? ` (${priceGap(picked).text})` : ''} · ${actualPriceSource(picked)}`
                        : null,
                ].filter(Boolean).join(' · ') : ''}
                options={picked ? [
                    {
                        label: 'Edit item', onClick: () => it.openEdit(picked),
                        disabled: !can(`${editPerm}.edit`), disabledReason: deny('edit items'),
                    },
                    {
                        label: 'Delete item', danger: true, onClick: () => it.setDeleting(picked),
                        disabled: !can(`${editPerm}.delete`), disabledReason: deny('delete items'),
                    },
                ] : []}
            />

            <ActionSheet
                open={tools}
                onClose={() => setTools(false)}
                title="Items"
                options={[
                    {
                        label: 'Import from Excel', onClick: () => it.setImportOpen(true),
                        disabled: !can(`${editPerm}.import`), disabledReason: deny('import items'),
                    },
                    can(`${editPerm}.import`)
                        ? { label: 'Download import template', href: '/api/v1/inventory/items/template' }
                        : { label: 'Download import template', disabled: true, disabledReason: deny('import items'), onClick: () => {} },
                    can(`${editPerm}.export`)
                        ? { label: 'Export as PDF', href: '/api/v1/inventory/items/export-pdf' }
                        : { label: 'Export as PDF', disabled: true, disabledReason: deny('export items'), onClick: () => {} },
                ]}
            />

            <Modal
                open={it.modalOpen}
                title={it.editing ? `Edit ${it.editing.item_name}` : 'New item'}
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
        </MobilePage>
    );
}
