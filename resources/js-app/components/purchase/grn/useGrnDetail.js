import { useCallback, useEffect, useState } from 'react';
import { apiGet, apiPatch, apiPostForm } from '../../../api/client';
import { echo } from '../../../echo';
import { useToast } from '../../ui/Toast';

/** Loads one GRN, keeps it live, and exposes confirm. */
export default function useGrnDetail(id) {
    const [grn, setGrn] = useState(null);
    const [loading, setLoading] = useState(true);
    const [confirming, setConfirming] = useState(false);
    const [uploading, setUploading] = useState(null);
    const { showToast } = useToast();

    const load = useCallback((quiet = false) => {
        if (!quiet) setLoading(true);

        return apiGet(`/purchase/grns/${id}`)
            .then((res) => setGrn(res.data))
            .catch(() => showToast('Failed to load that GRN.', 'error'))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    useEffect(() => {
        load();
    }, [load]);

    useEffect(() => {
        const channel = echo.private('purchase');
        const handler = (payload) => {
            if (String(payload.id) === String(id)) setGrn(payload);
        };
        channel.listen('.grn.saved', handler);

        return () => channel.stopListening('.grn.saved');
    }, [id]);

    async function confirm() {
        setConfirming(false);
        try {
            const response = await apiPatch(`/purchase/grns/${id}/confirm`);
            setGrn(response.data);
            showToast('GRN confirmed — stock updated.', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to confirm the GRN.', 'error');
        }
    }

    /**
     * Adds paperwork after saving: a missing LPO, GRN or tax invoice (field
     * e.g. `tax_invoice_document`, one file) or more Other files
     * (`other_documents`, several).
     */
    async function uploadDocuments(field, files) {
        const list = Array.from(files ?? []);
        if (!list.length) return;

        const form = new FormData();
        if (field === 'other_documents') list.forEach((file) => form.append('other_documents[]', file));
        else form.append(field, list[0]);

        setUploading(field);
        try {
            const response = await apiPostForm(`/purchase/grns/${id}/documents`, form);
            setGrn(response.data);
            showToast(response.message ?? 'Documents uploaded.', 'success');
        } catch (err) {
            const first = Object.values(err?.errors ?? {})[0];
            showToast((Array.isArray(first) ? first[0] : first) || err?.message || 'The file could not be uploaded.', 'error');
        } finally {
            setUploading(null);
        }
    }

    /**
     * Sets what one of the supplier's units holds in ours on a line received
     * in theirs. Resolves true when saved, so the converter can settle.
     */
    async function convert(itemId, unitFactor) {
        try {
            const response = await apiPatch(`/purchase/grns/${id}/items/${itemId}/conversion`, { unit_factor: unitFactor });
            setGrn(response.data);
            showToast(response.message ?? 'Conversion saved.', 'success');

            return true;
        } catch (err) {
            const first = err?.errors?.unit_factor;
            showToast((Array.isArray(first) ? first[0] : first) || err?.message || 'The conversion could not be saved.', 'error');

            return false;
        }
    }

    return { grn, loading, confirming, setConfirming, confirm, uploading, uploadDocuments, convert };
}
