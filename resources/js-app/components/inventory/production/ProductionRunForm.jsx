import { useMemo, useState } from 'react';
import { apiPost } from '../../../api/client';
import { amount, money, qty } from '../../../currency';
import {
    bestWarehouse, costOf, emptyLine, linesFromRecipe, onHand, rescale,
} from './productionMath';

const today = () => new Date().toISOString().slice(0, 10);

const errorText = { fontSize: 12, color: '#dc2626', marginTop: 4 };
const hint = { fontSize: 12, color: '#64748b', marginTop: 4 };

/**
 * One production run: what was made, how many, into which warehouse — and
 * the raw materials it used, each from the warehouse it was taken from.
 *
 * Choosing the product fills the materials in from its recipe, scaled to the
 * quantity; a line keeps following the quantity until someone edits it by
 * hand. Every line is costed at its material's cost price, and the total
 * becomes the product's cost price per unit when the run is saved.
 *
 * One tree for both viewports, `compact` stacking each line on a phone: two
 * copies of a form this long would drift (CLAUDE.md #10's reasoning).
 */
export default function ProductionRunForm({ options, onSaved, onCancel, compact = false }) {
    const [values, setValues] = useState({
        item_id: '', warehouse_id: '', quantity: '', production_date: today(), notes: '',
    });
    const [lines, setLines] = useState([emptyLine()]);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    const product = options.finished_goods.find((p) => String(p.id) === String(values.item_id));
    const materials = options.raw_materials;
    const cost = useMemo(() => costOf(lines, materials, values.quantity), [lines, materials, values.quantity]);
    const materialById = useMemo(() => Object.fromEntries(materials.map((m) => [String(m.id), m])), [materials]);

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    function chooseProduct(id) {
        setField('item_id', id);
        const recipe = options.finished_goods.find((p) => String(p.id) === String(id))?.recipe ?? [];
        setLines(recipe.length
            ? linesFromRecipe(recipe, values.quantity, options.stock, values.warehouse_id)
            : [emptyLine(values.warehouse_id)]);
    }

    function chooseQuantity(value) {
        setField('quantity', value);
        setLines((prev) => rescale(prev, value));
    }

    function setLine(key, changes) {
        setLines((prev) => prev.map((line) => (line.key === key ? { ...line, ...changes } : line)));
    }

    function chooseMaterial(key, itemId) {
        setLine(key, {
            item_id: itemId,
            warehouse_id: bestWarehouse(options.stock, itemId, values.warehouse_id),
            auto: false,
        });
    }

    const filled = lines.filter((line) => line.item_id);

    async function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const response = await apiPost('/inventory/production', {
                ...values,
                lines: filled.map(({ item_id, warehouse_id, quantity }) => ({ item_id, warehouse_id, quantity })),
            });
            onSaved(response.data, response.message);
        } catch (err) {
            // The API numbers lines as sent, which skips any left blank.
            const keyAt = filled.map((line) => line.key);
            setErrors(Object.fromEntries(Object.entries(err.errors ?? {}).map(([field, messages]) => {
                const match = field.match(/^lines\.(\d+)\.(\w+)$/);

                return [match ? `${keyAt[match[1]]}.${match[2]}` : field, messages[0]];
            })));
            if (!err.errors) setErrors({ form: err.message ?? 'Could not record the run.' });
        } finally {
            setSaving(false);
        }
    }

    const two = compact ? '1fr' : '1fr 1fr';

    return (
        <form onSubmit={handleSubmit}>
            <div style={{ display: 'grid', gridTemplateColumns: two, gap: 14 }}>
                <div>
                    <label htmlFor="run-product" className="form-label">Finished good <span className="text-red-500">*</span></label>
                    <select id="run-product" className="form-select" required value={values.item_id} onChange={(e) => chooseProduct(e.target.value)}>
                        <option value="">-- Select product --</option>
                        {options.finished_goods.map((p) => (
                            <option key={p.id} value={p.id}>{p.item_code} — {p.item_name}</option>
                        ))}
                    </select>
                    {errors.item_id && <p style={errorText}>{errors.item_id}</p>}
                    {product && product.recipe.length === 0 && (
                        <p style={hint}>No recipe for this product yet — add the materials below, or set one up under Recipes.</p>
                    )}
                </div>
                <div>
                    <label htmlFor="run-quantity" className="form-label">
                        Quantity made{product ? ` (${product.unit_of_measure})` : ''} <span className="text-red-500">*</span>
                    </label>
                    <input
                        id="run-quantity" className="form-input" type="number" min="0.01" step="0.01" required
                        value={values.quantity} onChange={(e) => chooseQuantity(e.target.value)}
                    />
                    {errors.quantity && <p style={errorText}>{errors.quantity}</p>}
                </div>
                <div>
                    <label htmlFor="run-warehouse" className="form-label">Into warehouse <span className="text-red-500">*</span></label>
                    <select id="run-warehouse" className="form-select" required value={values.warehouse_id} onChange={(e) => setField('warehouse_id', e.target.value)}>
                        <option value="">-- Select warehouse --</option>
                        {options.warehouses.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
                    </select>
                    {errors.warehouse_id && <p style={errorText}>{errors.warehouse_id}</p>}
                </div>
                <div>
                    <label htmlFor="run-date" className="form-label">Date <span className="text-red-500">*</span></label>
                    <input
                        id="run-date" className="form-input" type="date" required
                        value={values.production_date} onChange={(e) => setField('production_date', e.target.value)}
                    />
                </div>
            </div>

            <div style={{ marginTop: 20, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <h3 style={{ fontSize: 14, fontWeight: 700, color: '#1e293b' }}>Raw materials used</h3>
                <button type="button" className="btn-secondary btn-sm" onClick={() => setLines((prev) => [...prev, emptyLine(values.warehouse_id)])}>
                    + Add material
                </button>
            </div>
            {errors.lines && <p style={errorText}>{errors.lines}</p>}

            <div style={{ marginTop: 8 }}>
                {cost.lines.map((line, index) => {
                    const material = materialById[String(line.item_id)];
                    const held = line.item_id && line.warehouse_id ? onHand(options.stock, line.item_id, line.warehouse_id) : null;
                    const short = held !== null && Number(line.quantity) > held;

                    return (
                        <div key={line.key} data-testid="run-line" style={{
                            display: 'grid', gap: 8, alignItems: 'start', padding: '10px 0',
                            gridTemplateColumns: compact ? '1fr 1fr' : '2.2fr 1.4fr 1fr 1fr auto',
                            borderTop: index === 0 ? 'none' : '1px solid #f1f5f9',
                        }}>
                            <div style={compact ? { gridColumn: '1 / -1' } : undefined}>
                                <select
                                    aria-label="Raw material" className="form-select" value={line.item_id}
                                    onChange={(e) => chooseMaterial(line.key, e.target.value)}
                                >
                                    <option value="">-- Material --</option>
                                    {materials.map((m) => <option key={m.id} value={m.id}>{m.item_name}</option>)}
                                </select>
                                {errors[`${line.key}.item_id`] && <p style={errorText}>{errors[`${line.key}.item_id`]}</p>}
                            </div>
                            <div>
                                <select
                                    aria-label="From warehouse" className="form-select" value={line.warehouse_id}
                                    onChange={(e) => setLine(line.key, { warehouse_id: e.target.value })}
                                >
                                    <option value="">-- From --</option>
                                    {options.warehouses.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
                                </select>
                                {held !== null && (
                                    <p style={{ ...hint, color: short ? '#dc2626' : '#64748b' }}>
                                        {qty(held)} {material?.unit_of_measure} on hand
                                    </p>
                                )}
                            </div>
                            <div>
                                <input
                                    aria-label="Quantity used" className="form-input" type="number" min="0.01" step="0.01"
                                    placeholder="Qty" value={line.quantity}
                                    onChange={(e) => setLine(line.key, { quantity: e.target.value, auto: false })}
                                />
                                {line.auto && line.per_unit !== null && (
                                    <p style={hint}>{qty(line.per_unit)} per unit</p>
                                )}
                                {errors[`${line.key}.quantity`] && <p style={errorText}>{errors[`${line.key}.quantity`]}</p>}
                            </div>
                            <div style={{ textAlign: 'right', fontSize: 13, paddingTop: 8 }}>
                                <div style={{ fontWeight: 600, color: '#0f172a' }}>{amount(line.line_cost)}</div>
                                {line.item_id && (line.unit_cost > 0
                                    ? <div style={hint}>@ {amount(line.unit_cost)}</div>
                                    : <div style={{ ...hint, color: '#b45309' }}>No cost price</div>)}
                            </div>
                            <div style={{ paddingTop: 4, textAlign: 'right' }}>
                                <button
                                    type="button" aria-label="Remove material" className="text-gray-400 hover:text-red-600"
                                    style={{ fontSize: 18, lineHeight: 1 }}
                                    onClick={() => setLines((prev) => (prev.length > 1 ? prev.filter((l) => l.key !== line.key) : [emptyLine(values.warehouse_id)]))}
                                >
                                    ×
                                </button>
                            </div>
                        </div>
                    );
                })}
            </div>

            <div style={{
                marginTop: 12, padding: 12, background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 10,
                display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap', gap: 8,
            }}>
                <div>
                    <div style={{ fontSize: 12, color: '#64748b' }}>Total cost</div>
                    <div data-testid="run-total" style={{ fontSize: 16, fontWeight: 700, color: '#0f172a' }}>{money(cost.total)}</div>
                </div>
                <div style={{ textAlign: 'right' }}>
                    <div style={{ fontSize: 12, color: '#64748b' }}>Cost per {product?.unit_of_measure ?? 'unit'}</div>
                    <div data-testid="run-per-unit" style={{ fontSize: 16, fontWeight: 700, color: '#0f172a' }}>{money(cost.perUnit)}</div>
                </div>
                {product && cost.total > 0 && (
                    <p style={{ ...hint, flexBasis: '100%' }}>
                        Saving sets {product.item_name}&rsquo;s cost price to {money(cost.perUnit)}.
                    </p>
                )}
            </div>

            <div style={{ marginTop: 14 }}>
                <label htmlFor="run-notes" className="form-label">Notes</label>
                <input
                    id="run-notes" className="form-input" type="text"
                    value={values.notes} onChange={(e) => setField('notes', e.target.value)}
                />
            </div>

            {errors.form && <p style={errorText}>{errors.form}</p>}

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 18 }}>
                <button type="button" className="btn-secondary" onClick={onCancel}>Cancel</button>
                <button type="submit" className="btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Record Production'}</button>
            </div>
        </form>
    );
}
