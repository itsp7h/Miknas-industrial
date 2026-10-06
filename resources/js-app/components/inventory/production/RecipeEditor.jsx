import { useState } from 'react';
import { apiPut } from '../../../api/client';
import { newKey } from './productionMath';

const errorText = { fontSize: 12, color: '#dc2626', marginTop: 4 };

const blank = () => ({ key: newKey(), raw_material_id: '', quantity_required: '' });

/**
 * What one unit of a finished good is made of. A production run starts from
 * this and scales it to the quantity made.
 *
 * The product is picked here; the lines below are that product's recipe and
 * are saved whole. Keyed by product from the parent, so switching product
 * re-seeds the lines from the one chosen rather than carrying them over.
 */
export function RecipeLines({ product, materials, onSaved, onCancel, compact = false }) {
    const [lines, setLines] = useState(() => (product.recipe.length
        ? product.recipe.map((line) => ({
            key: newKey(),
            raw_material_id: String(line.raw_material_id),
            quantity_required: String(Number(line.quantity_required)),
        }))
        : [blank()]));
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    const filled = lines.filter((line) => line.raw_material_id);
    const unitOf = (id) => materials.find((m) => String(m.id) === String(id))?.unit_of_measure ?? '';

    function setLine(key, changes) {
        setLines((prev) => prev.map((line) => (line.key === key ? { ...line, ...changes } : line)));
    }

    async function save() {
        setSaving(true);
        setErrors({});
        try {
            const response = await apiPut(`/inventory/production/recipes/${product.id}`, {
                lines: filled.map(({ raw_material_id, quantity_required }) => ({ raw_material_id, quantity_required })),
            });
            onSaved(response.data, response.message);
        } catch (err) {
            const keyAt = filled.map((line) => line.key);
            setErrors(Object.fromEntries(Object.entries(err.errors ?? {}).map(([field, messages]) => {
                const match = field.match(/^lines\.(\d+)\.(\w+)$/);

                return [match ? `${keyAt[match[1]]}.${match[2]}` : field, messages[0]];
            })));
            if (!err.errors) setErrors({ form: err.message ?? 'Could not save the recipe.' });
        } finally {
            setSaving(false);
        }
    }

    return (
        <div>
            <p style={{ fontSize: 12.5, color: '#64748b', marginBottom: 8 }}>
                Raw materials for <strong>one {product.unit_of_measure}</strong> of {product.item_name}.
            </p>

            {lines.map((line) => (
                <div key={line.key} data-testid="recipe-line" style={{
                    display: 'grid', gap: 8, alignItems: 'start', marginBottom: 8,
                    gridTemplateColumns: compact ? '1fr 7rem auto' : '1fr 9rem auto',
                }}>
                    <div>
                        <select
                            aria-label="Raw material" className="form-select" value={line.raw_material_id}
                            onChange={(e) => setLine(line.key, { raw_material_id: e.target.value })}
                        >
                            <option value="">-- Material --</option>
                            {materials.map((m) => <option key={m.id} value={m.id}>{m.item_name}</option>)}
                        </select>
                        {errors[`${line.key}.raw_material_id`] && <p style={errorText}>{errors[`${line.key}.raw_material_id`]}</p>}
                    </div>
                    <div>
                        <input
                            aria-label="Quantity per unit" className="form-input" type="number" min="0.01" step="0.01"
                            placeholder={unitOf(line.raw_material_id) || 'Qty'} value={line.quantity_required}
                            onChange={(e) => setLine(line.key, { quantity_required: e.target.value })}
                        />
                        {errors[`${line.key}.quantity_required`] && <p style={errorText}>{errors[`${line.key}.quantity_required`]}</p>}
                    </div>
                    <button
                        type="button" aria-label="Remove material" className="text-gray-400 hover:text-red-600"
                        style={{ fontSize: 18, lineHeight: 1, paddingTop: 8 }}
                        onClick={() => setLines((prev) => (prev.length > 1 ? prev.filter((l) => l.key !== line.key) : [blank()]))}
                    >
                        ×
                    </button>
                </div>
            ))}

            <button type="button" className="btn-secondary btn-sm" onClick={() => setLines((prev) => [...prev, blank()])}>
                + Add material
            </button>
            {errors.form && <p style={errorText}>{errors.form}</p>}

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 18 }}>
                <button type="button" className="btn-secondary" onClick={onCancel}>Close</button>
                <button type="button" className="btn-primary" disabled={saving} onClick={save}>
                    {saving ? 'Saving…' : 'Save Recipe'}
                </button>
            </div>
        </div>
    );
}

/** Pick a finished good, then edit its recipe. */
export default function RecipeEditor({ options, onSaved, onCancel, compact = false }) {
    const [productId, setProductId] = useState('');
    const product = options.finished_goods.find((p) => String(p.id) === String(productId));

    return (
        <div>
            <label htmlFor="recipe-product" className="form-label">Finished good</label>
            <select id="recipe-product" className="form-select" value={productId} onChange={(e) => setProductId(e.target.value)}>
                <option value="">-- Select product --</option>
                {options.finished_goods.map((p) => (
                    <option key={p.id} value={p.id}>
                        {p.item_code} — {p.item_name}{p.recipe.length ? ` (${p.recipe.length} materials)` : ' (no recipe)'}
                    </option>
                ))}
            </select>

            <div style={{ marginTop: 14 }}>
                {product
                    ? (
                        <RecipeLines
                            key={product.id} product={product} materials={options.raw_materials}
                            onSaved={onSaved} onCancel={onCancel} compact={compact}
                        />
                    )
                    : (
                        <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                            <button type="button" className="btn-secondary" onClick={onCancel}>Close</button>
                        </div>
                    )}
            </div>
        </div>
    );
}
