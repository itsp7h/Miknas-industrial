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
});

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
    const [meta, setMeta] = useState({ lead_time_days: '', payment_terms: '', notes: '' });
    const [editing, setEditing] = useState({ id: null, draft: '' });
    const [errors, setErrors] = useState({});
    const [formError, setFormError] = useState('');
    const [submitting, setSubmitting] = useState(false);

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

    const setField = useCallback((name, value) => {
        setMeta((prev) => ({ ...prev, [name]: value }));
    }, []);

    const lineTotal = useCallback((item) => {
        const row = rows[item.id];
        if (!row || row.notAvailable) return 0;

        return round3((parseFloat(row.unitPrice) || 0) * item.quantity_required);
    }, [rows]);

    const totals = useMemo(() => {
        let subtotal = 0;
        let vat = 0;

        items.forEach((item) => {
            const row = rows[item.id];
            if (!row || row.notAvailable) return;

            const total = round3((parseFloat(row.unitPrice) || 0) * item.quantity_required);
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

    const codeMatches =
        confirmCode !== '' && confirmInput.trim().toUpperCase() === confirmCode.toUpperCase();

    const canSubmit = terms && codeMatches && unpricedCount === 0 && !submitting;

    const blockedReason = (() => {
        if (unpricedCount > 0) {
            return unpricedCount === 1
                ? 'One item still needs a unit price, or mark it as not available.'
                : `${unpricedCount} items still need a unit price, or mark them as not available.`;
        }
        if (!terms) return 'Please accept the terms and conditions.';
        if (!codeMatches) return 'Enter the confirmation code exactly as shown.';

        return '';
    })();

    async function submit(event) {
        event?.preventDefault();
        if (!canSubmit) return;

        setSubmitting(true);
        setErrors({});
        setFormError('');

        try {
            const response = await send(`/rfq/${token}`, {
                terms: true,
                confirm_code: confirmInput.trim().toUpperCase(),
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
            if (!Object.keys(fieldErrors).length) {
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
        rows,
        setRow,
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
        canSubmit,
        blockedReason,
        submit,
    };
}
