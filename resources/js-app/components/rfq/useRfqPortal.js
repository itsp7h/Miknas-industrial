import { money as formatMoney } from '../../currency';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { apiGet, apiPost } from '../../api/client';

/** Three decimals everywhere, because BD prices are quoted in fils. */
export const round3 = (value) => Math.round((Number(value) || 0) * 1000) / 1000;

// The portal is public and has no shell to read the configured currency from,
// so it takes the default rather than guessing.
export const money = (value) => formatMoney(round3(value));

/** 10.500 → "10.5", 10.000 → "10" — quantities read better without the padding. */
export function qty(value) {
    const fixed = round3(value).toFixed(3);

    return fixed.includes('.') ? fixed.replace(/\.?0+$/, '') : fixed;
}

const blankRow = (item) => ({
    unitPrice: '',
    isVatable: false,
    notAvailable: false,
    description: item.description,
    // The unit they quote in: ours unless they change it, in which case they
    // say how many of theirs they supply. What one holds in ours is not asked
    // of them; it is set on our GRN when the goods arrive.
    unit: item.unit ?? '',
    supplierQty: '',
});

/** Whether a row is quoted in a unit other than the one we asked in. */
export const inOtherUnit = (item, row) => !!(row?.unit && item.unit && row.unit !== item.unit);

/**
 * How many units the price multiplies: theirs when they changed the unit
 * ("4 BAG"), otherwise the quantity we asked for.
 */
export const pricedQty = (item, row) => (inOtherUnit(item, row)
    ? (parseFloat(row.supplierQty) || 0)
    : item.quantity_required);

/**
 * The whole supplier portal — loading the invitation, the quote the supplier
 * is building, the running totals, and the submit — shared by the desktop and
 * mobile pages so the two differ only in how they lay it out (CLAUDE.md #12).
 *
 * Totals are computed the same way the server recomputes them on submit
 * (round each line to three decimals, then VAT per line): the supplier must
 * not see one grand total here and a different one on the comparison sheet.
 */
export default function useRfqPortal({ token, load = apiGet, send = apiPost } = {}) {
    const [payload, setPayload] = useState(null);
    const [loadError, setLoadError] = useState('');
    const [rows, setRows] = useState({});
    const [terms, setTerms] = useState(false);
    const [confirmInput, setConfirmInput] = useState('');
    const [meta, setMeta] = useState({ reference: '', lead_time_days: '', payment_terms: '', notes: '' });
    const [editing, setEditing] = useState({ id: null, draft: '' });
    const [errors, setErrors] = useState({});
    const [formError, setFormError] = useState('');
    const [submitting, setSubmitting] = useState(false);
    // Set by the first submit that finds something missing. From then on every
    // missing required field is outlined in red, and each clears as it is
    // filled. Not before: a blank form all in red on arrival reads as an error
    // the supplier has not made yet.
    const [showMissing, setShowMissing] = useState(false);

    // StrictMode mounts effects twice in development, and GET /rfq/{token} is not
    // a plain read — it flips the invitation to 'opened' and issues the session's
    // confirmation code — so the fetch is made once and once only.
    //
    // This ref is the whole guard. It used to be paired with a `live` flag
    // cleared on cleanup, which cancelled the one request the ref allowed: the
    // cleanup from the first mount set live=false, the second mount returned
    // early rather than refetching, and the response that did arrive was
    // dropped on the floor. The portal then sat on "Loading your quote
    // request…" for ever — in development only, which is why it was invisible
    // on staging and production, where StrictMode does not double-invoke.
    const loaded = useRef(false);

    useEffect(() => {
        if (loaded.current) return;
        loaded.current = true;

        load(`/rfq/${token}`)
            .then((response) => {
                setPayload(response);
                setRows(
                    Object.fromEntries(
                        (response?.data?.items ?? []).map((item) => [item.id, blankRow(item)])
                    )
                );
            })
            .catch((err) => {
                setLoadError(err?.message || 'This quote request could not be loaded.');
            });
    }, [token, load]);

    const invitation = payload?.data ?? null;
    const state = loadError ? 'error' : (payload?.state ?? 'loading');
    const vatRate = Number(payload?.vat_rate ?? 0);
    const confirmCode = payload?.confirm_code ?? '';
    const items = invitation?.items ?? [];

    const setRow = useCallback((id, patch) => {
        setRows((prev) => ({ ...prev, [id]: { ...prev[id], ...patch } }));
    }, []);

    /** Marking a line unavailable clears the price and the VAT flag with it. */
    const setNotAvailable = useCallback((id, notAvailable) => {
        setRow(id, notAvailable
            ? { notAvailable: true, unitPrice: '', isVatable: false }
            : { notAvailable: false });
    }, [setRow]);

    /**
     * Description editing is held here rather than in each page so the two
     * layouts cannot disagree about what "cancel" restores: the draft is kept
     * apart from the committed value, and only a save writes it back.
     */
    const beginEdit = useCallback((item) => {
        setEditing({ id: item.id, draft: rows[item.id]?.description ?? item.description });
    }, [rows]);

    const setDraft = useCallback((draft) => {
        setEditing((prev) => ({ ...prev, draft }));
    }, []);

    const endEdit = useCallback((item, save) => {
        if (save) {
            // Blanking the field means "leave it as it was", not "no name".
            setRow(item.id, { description: editing.draft.trim() || item.description });
        }
        setEditing({ id: null, draft: '' });
    }, [editing.draft, setRow]);

    /** Switching back to our unit forgets their quantity. */
    const setUnit = useCallback((item, unit) => {
        setRow(item.id, unit === item.unit ? { unit, supplierQty: '' } : { unit });
    }, [setRow]);

    const setSupplierQty = useCallback((item, supplierQty) => {
        setRow(item.id, { supplierQty });
    }, [setRow]);

    const setField = useCallback((name, value) => {
        setMeta((prev) => ({ ...prev, [name]: value }));
    }, []);

    const lineTotal = useCallback((item) => {
        const row = rows[item.id];
        if (!row || row.notAvailable) return 0;

        return round3((parseFloat(row.unitPrice) || 0) * pricedQty(item, row));
    }, [rows]);

    const totals = useMemo(() => {
        let subtotal = 0;
        let vat = 0;

        items.forEach((item) => {
            const row = rows[item.id];
            if (!row || row.notAvailable) return;

            const total = round3((parseFloat(row.unitPrice) || 0) * pricedQty(item, row));
            subtotal += total;

            if (row.isVatable && vatRate > 0) {
                vat += round3((total * vatRate) / 100);
            }
        });

        return { subtotal, vat, grand: round3(subtotal + vat) };
    }, [items, rows, vatRate]);

    // Every line has to be priced or explicitly marked unavailable. The Blade
    // form left this to the browser's `required`, which only surfaced as an
    // untitled tooltip on whichever input it reached first.
    const unpricedCount = items.filter((item) => {
        const row = rows[item.id];

        return row && !row.notAvailable && String(row.unitPrice).trim() === '';
    }).length;

    // A changed unit needs their quantity, or the line has no total.
    const unmappedCount = items.filter((item) => {
        const row = rows[item.id];

        return row && !row.notAvailable && inOtherUnit(item, row) && !(parseFloat(row.supplierQty) > 0);
    }).length;

    const codeMatches =
        confirmCode !== '' && confirmInput.trim().toUpperCase() === confirmCode.toUpperCase();

    // The supplier's quotation number goes on the LPO, so it cannot be blank.
    const hasReference = meta.reference.trim() !== '';

    const ready = hasReference && terms && codeMatches && unpricedCount === 0 && unmappedCount === 0;
    const canSubmit = ready && !submitting;

    /**
     * What is still missing, field by field, once the supplier has tried to
     * submit — the same rules as `ready`, so a red field and the reason under
     * the button can never disagree.
     */
    const missing = useMemo(() => {
        const none = { reference: false, terms: false, confirmCode: false, rows: {} };
        if (!showMissing) return none;

        return {
            reference: !hasReference,
            terms: !terms,
            confirmCode: !codeMatches,
            rows: Object.fromEntries(items.map((item) => {
                const row = rows[item.id];
                const open = row && !row.notAvailable;
                const other = open && inOtherUnit(item, row);

                return [item.id, {
                    unitPrice: !!open && String(row.unitPrice).trim() === '',
                    supplierQty: !!other && !(parseFloat(row.supplierQty) > 0),
                }];
            })),
        };
    }, [showMissing, hasReference, terms, codeMatches, items, rows]);

    const blockedReason = (() => {
        if (!hasReference) return 'Please enter your quotation reference number (Ref).';
        if (unpricedCount > 0) {
            return unpricedCount === 1
                ? 'One item still needs a unit price, or mark it as not available.'
                : `${unpricedCount} items still need a unit price, or mark them as not available.`;
        }
        if (unmappedCount > 0) {
            return unmappedCount === 1
                ? 'One item is in a different unit: enter your quantity in that unit.'
                : `${unmappedCount} items are in a different unit: enter your quantity in each.`;
        }
        if (!terms) return 'Please accept the terms and conditions.';
        if (!codeMatches) return 'Enter the confirmation code exactly as shown.';

        return '';
    })();

    async function submit(event) {
        event?.preventDefault();
        if (submitting) return;
        if (!ready) {
            setShowMissing(true);
            // After the red outlines render, bring the first one into view.
            setTimeout(() => {
                const first = document.querySelector('[data-missing="true"]');
                first?.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
                first?.focus?.({ preventScroll: true });
            }, 0);

            return;
        }

        setSubmitting(true);
        setErrors({});
        setFormError('');

        try {
            const response = await send(`/rfq/${token}`, {
                terms: true,
                confirm_code: confirmInput.trim().toUpperCase(),
                reference: meta.reference.trim(),
                lead_time_days: meta.lead_time_days === '' ? null : Number(meta.lead_time_days),
                payment_terms: meta.payment_terms || null,
                notes: meta.notes || null,
                items: items.map((item) => {
                    const row = rows[item.id];
                    const edited = row.description.trim() !== item.description;

                    return {
                        id: item.id,
                        unit_price: row.notAvailable || row.unitPrice === ''
                            ? null
                            : Number(row.unitPrice),
                        is_vatable: row.isVatable,
                        not_available: row.notAvailable,
                        supplier_description: edited ? row.description.trim() : null,
                        ...(inOtherUnit(item, row) && !row.notAvailable ? {
                            supplier_unit: row.unit,
                            supplier_quantity: Number(row.supplierQty),
                        } : {}),
                    };
                }),
            });

            setPayload(response);
        } catch (err) {
            const fieldErrors = err?.errors ?? {};
            setErrors(
                Object.fromEntries(
                    Object.entries(fieldErrors).map(([key, list]) => [
                        key,
                        Array.isArray(list) ? list[0] : String(list),
                    ])
                )
            );
            // A line-level refusal has no field of its own on the page.
            if (!Object.keys(fieldErrors).length || fieldErrors.items) {
                setFormError(err?.message || 'Your quote could not be submitted. Please try again.');
            }
            setSubmitting(false);
        }
    }

    return {
        state,
        loadError,
        invitation,
        items,
        vatRate,
        confirmCode,
        units: payload?.units ?? [],
        rows,
        setRow,
        setUnit,
        setSupplierQty,
        setNotAvailable,
        editing,
        beginEdit,
        setDraft,
        endEdit,
        terms,
        setTerms,
        confirmInput,
        setConfirmInput,
        codeMatches,
        meta,
        setField,
        lineTotal,
        totals,
        errors,
        formError,
        submitting,
        ready,
        canSubmit,
        missing,
        showMissing,
        blockedReason,
        submit,
    };
}
