/**
 * The arithmetic of a production run, kept out of the form so both viewports
 * and the tests share one copy.
 */

const round = (value, places) => {
    const factor = 10 ** places;

    return Math.round((Number(value) || 0) * factor) / factor;
};

let nextKey = 0;
export const newKey = () => `line-${++nextKey}`;

/** How much of an item a warehouse holds, from the options' `stock` rows. */
export function onHand(stock, itemId, warehouseId) {
    const row = (stock ?? []).find((s) =>
        String(s.item_id) === String(itemId) && String(s.warehouse_id) === String(warehouseId));

    return row ? Number(row.quantity) : 0;
}

/** The warehouse holding most of a material, or `fallback` when none does. */
export function bestWarehouse(stock, itemId, fallback = '') {
    const rows = (stock ?? []).filter((s) => String(s.item_id) === String(itemId) && Number(s.quantity) > 0);
    if (rows.length === 0) return fallback;

    return String(rows.reduce((a, b) => (Number(b.quantity) > Number(a.quantity) ? b : a)).warehouse_id);
}

/**
 * The lines a product's recipe asks for to make `quantity` of it. Each line
 * remembers its per-unit amount and that it came from the recipe (`auto`), so
 * a change of quantity rescales it — until someone edits it by hand.
 */
export function linesFromRecipe(recipe, quantity, stock, fallbackWarehouse = '') {
    return (recipe ?? []).map((line) => ({
        key: newKey(),
        item_id: String(line.raw_material_id),
        warehouse_id: bestWarehouse(stock, line.raw_material_id, fallbackWarehouse),
        per_unit: Number(line.quantity_required),
        quantity: scaled(line.quantity_required, quantity),
        auto: true,
    }));
}

export const scaled = (perUnit, quantity) => {
    const q = Number(quantity);

    return q > 0 ? String(round(Number(perUnit) * q, 2)) : '';
};

/** Rescales the lines still following the recipe; leaves hand-edited ones alone. */
export const rescale = (lines, quantity) =>
    lines.map((line) => (line.auto ? { ...line, quantity: scaled(line.per_unit, quantity) } : line));

export const emptyLine = (warehouseId = '') => ({
    key: newKey(), item_id: '', warehouse_id: warehouseId, per_unit: null, quantity: '', auto: false,
});

/** Each line's cost at its material's cost price, plus the run's total and per-unit cost. */
export function costOf(lines, materials, quantity) {
    const byId = Object.fromEntries((materials ?? []).map((m) => [String(m.id), m]));
    const costed = lines.map((line) => {
        const unitCost = Number(byId[String(line.item_id)]?.cost_price ?? 0);

        return { ...line, unit_cost: unitCost, line_cost: round((Number(line.quantity) || 0) * unitCost, 3) };
    });
    const total = round(costed.reduce((sum, line) => sum + line.line_cost, 0), 3);
    const q = Number(quantity);

    return { lines: costed, total, perUnit: q > 0 ? total / q : 0 };
}
