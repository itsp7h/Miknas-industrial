import { useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete, apiPostForm } from '../../../api/client';
import { useToast } from '../../ui/Toast';
import {
    categorySearchText, scopeToWarehouse, sectionOptions, sortItems, SORT_OPTIONS,
    warehouseNames, warehouseOptions,
} from './itemStyles';

/** List, import and delete behaviour shared by both viewports. */
/**
 * @param onlyCategory  restricts the page to one items.category, so the Raw
 *                      Materials page shows raw materials and the Finished
 *                      Goods page shows finished goods. Omitted, it shows all.
 */
export default function useItemList(onlyCategory = null) {
    const { items: allItems, meta, upsertItem, removeItem, refetch } = useLiveList({
        endpoint: '/inventory/items',
        channel: 'inventory',
        event: '.item.saved',
        deleteEvent: '.item.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load items.',
    });
    const [query, setQuery] = useState('');
    const [warehouseId, setWarehouseId] = useState('');
    const [sectionId, setSectionId] = useState('');
    const [sort, setSort] = useState('name');
    const [modalOpen, setModalOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const { showToast } = useToast();

    function openCreate() {
        setEditing(null);
        setModalOpen(true);
    }

    function openEdit(item) {
        setEditing(item);
        setModalOpen(true);
    }

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
                // An item with stock history is deactivated rather than removed,
                // so the row stays and is refreshed instead.
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

    async function handleImport(file) {
        const formData = new FormData();
        formData.append('file', file);
        try {
            const result = await apiPostForm('/inventory/items/import', formData);
            showToast(`${result.imported} added, ${result.skipped} skipped.`, 'success');
            setImportOpen(false);
            await refetch();
        } catch (err) {
            showToast(err.message || 'Failed to import items.', 'error');
        }
    }

    // The page's own slice of the list. Everything below — the counts, the
    // filter options, the search — reads this, never the unfiltered set, so
    // nothing offers a choice that leads to an empty page.
    const items = onlyCategory ? allItems.filter((item) => item.category === onlyCategory) : allItems;

    const warehouses = warehouseOptions(items);
    const sections = sectionOptions(items);

    const activeSectionId = sections.some((s) => String(s.id) === sectionId) ? sectionId : '';

    // A warehouse that empties out while its filter is on would otherwise leave
    // the page stuck on an option no longer offered, showing nothing.
    const activeWarehouseId = warehouses.some((w) => String(w.id) === warehouseId) ? warehouseId : '';

    // Narrow to the warehouse first, so the search reads the scoped rows and
    // the count below the box counts what is actually on screen.
    const inScope = scopeToWarehouse(items, activeWarehouseId)
        .filter((item) => !activeSectionId || String(item.item_category_id) === activeSectionId);

    const matched = inScope.filter((item) => {
        const q = query.trim().toLowerCase();
        if (!q) return true;

        return [item.item_code, item.item_name, categorySearchText(item), item.unit_of_measure, warehouseNames(item)]
            .some((field) => String(field ?? '').toLowerCase().includes(q));
    });

    // Ordered last, so the sort applies to what survived the filters rather
    // than to the whole list — the rows on screen are the ones being ordered.
    const filtered = sortItems(matched, sort);

    return {
        items, filtered, inScope,
        categoryOptions: meta.category_options ?? [],
        allWarehouses: meta.warehouses ?? [],
        query, setQuery,
        warehouses, warehouseId: activeWarehouseId, setWarehouseId,
        sections, sectionId: activeSectionId, setSectionId,
        sort, setSort, sortOptions: SORT_OPTIONS,
        modalOpen, setModalOpen,
        importOpen, setImportOpen, handleImport,
        editing, openCreate, openEdit, handleSaved,
        deleting, setDeleting, handleDeleteConfirmed,
    };
}
