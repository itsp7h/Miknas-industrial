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

export const num = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
