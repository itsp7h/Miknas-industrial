import { money, qty } from '../../../currency';
import { CATEGORIES } from './ItemForm';

export const CATEGORY_LABELS = Object.fromEntries(CATEGORIES.map((c) => [c.value, c.label]));

// The Blade items table badged category with these classes. Note it labelled
// 'wip' as "WIP" in the table while the form's dropdown says "Work In Progress";
// the table's short form is kept here.
export const CATEGORY_BADGE_CLASS = {
    raw_material: 'badge-blue',
    wip: 'badge-yellow',
    finished_good: 'badge-green',
};

export const CATEGORY_TABLE_LABELS = {
    raw_material: 'Raw Material',
    wip: 'WIP',
    finished_good: 'Finished Good',
};

export const categoryBadgeClass = (category) => CATEGORY_BADGE_CLASS[category] ?? 'badge-gray';

export const categoryLabel = (category) => CATEGORY_TABLE_LABELS[category]
    ?? (category ? category.charAt(0).toUpperCase() + category.slice(1) : '');

// Quantities are bags and kilos; cost price is an amount. They format
// differently and only one of them carries a symbol. `num` is a local binding,
// not a bare re-export, because `warehouseBreakdown` below calls it.
const num = qty;

export { num, money };

// On-hand below the item's own minimum. A minimum of 0 means "no threshold
// set", so such an item is never low — otherwise every unstocked item would
// shout.
export const isLow = (item) =>
    Number(item?.minimum_stock_level ?? 0) > 0
    && Number(item?.quantity ?? 0) < Number(item.minimum_stock_level);

// One row per item, but stock sits in many warehouses. The cell names the
// biggest holding and counts the rest; the full split is the cell's title, so
// nothing the stock summary shows is lost by collapsing it here.
export const warehouseLabel = (item) => {
    const list = item?.warehouses ?? [];
    if (list.length === 0) return '—';
    if (list.length === 1) return list[0].name;

    return `${list[0].name} +${list.length - 1}`;
};

export const warehouseBreakdown = (item) => (item?.warehouses ?? [])
    .map((warehouse) => `${warehouse.name}: ${num(warehouse.quantity)}`)
    .join('\n');

// What the search box matches a warehouse on.
export const warehouseNames = (item) => (item?.warehouses ?? [])
    .map((warehouse) => warehouse.name)
    .join(' ');

// Every warehouse that actually holds one of these items, by name. Derived
// from the loaded list rather than fetched: the filter exists to narrow what
// is on screen, and a warehouse holding none of it would narrow to nothing.
export const warehouseOptions = (items) => {
    const byId = new Map();
    items.forEach((item) => (item.warehouses ?? []).forEach((warehouse) => {
        byId.set(warehouse.id, warehouse.name);
    }));

    return [...byId].map(([id, name]) => ({ id, name }))
        .sort((a, b) => a.name.localeCompare(b.name));
};

/**
 * The list as seen from one warehouse: only the items stocked there, each
 * carrying that warehouse's quantity rather than its total.
 *
 * Re-scoping the figure is the point. A row is then exactly a stock-summary
 * line — quantity in this warehouse against the item's minimum — so `isLow`
 * flags the same lines that report does, instead of hiding a local shortfall
 * behind a healthy total held somewhere else.
 */
export const scopeToWarehouse = (items, warehouseId) => {
    if (!warehouseId) return items;

    return items.reduce((kept, item) => {
        const here = (item.warehouses ?? []).find((w) => String(w.id) === String(warehouseId));
        if (here) kept.push({ ...item, quantity: here.quantity, warehouses: [here] });

        return kept;
    }, []);
};

// Every section present in the listed items, for the filter. Derived from what
// is on screen for the same reason the warehouse list is: a section holding
// none of these items would narrow to nothing.
export const sectionOptions = (items) => {
    const byId = new Map();
    items.forEach((item) => {
        if (item.item_category_id) byId.set(item.item_category_id, item.item_category_name);
    });

    return [...byId].map(([id, name]) => ({ id, name }))
        .sort((a, b) => String(a.name).localeCompare(String(b.name)));
};

// What the search box matches a classification on: both halves, so "raw" and
// "pigments" each find the same row.
export const categorySearchText = (item) => item?.category_path ?? categoryLabel(item?.category);


// The orders the list can be put in. `name` is the default because it is the
// order the server already returns and the one the page has always had.
export const SORT_OPTIONS = [
    { value: 'name', label: 'Name (A–Z)' },
    { value: 'code', label: 'Item code' },
    { value: 'recent', label: 'Recently purchased' },
];

/**
 * A copy of `items` in the chosen order.
 *
 * Client-side, over every loaded row, for the same reason the search is
 * (CLAUDE.md gotcha #6) — changing the order must not cost a round trip.
 *
 * `numeric` matters for the codes: ITEM-00009 sorts before ITEM-00010 either
 * way, but a hand-typed code like RM-9 would otherwise land after RM-10.
 * Items nobody has bought yet sort last under `recent` rather than first,
 * because a missing date is not the oldest date; they keep their name order
 * among themselves so the tail is still navigable.
 */
export const sortItems = (items, sort) => {
    const byName = (a, b) => String(a.item_name ?? '').localeCompare(String(b.item_name ?? ''), undefined, { numeric: true });

    if (sort === 'code') {
        return [...items].sort((a, b) =>
            String(a.item_code ?? '').localeCompare(String(b.item_code ?? ''), undefined, { numeric: true }));
    }

    if (sort === 'recent') {
        return [...items].sort((a, b) => {
            const left = a.last_purchased_at ?? '';
            const right = b.last_purchased_at ?? '';
            if (left === right) return byName(a, b);
            if (!left) return 1;
            if (!right) return -1;

            return right.localeCompare(left);
        });
    }

    return [...items].sort(byName);
};
