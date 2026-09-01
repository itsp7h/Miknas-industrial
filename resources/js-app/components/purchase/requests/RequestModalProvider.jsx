import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { apiGet, apiPost, apiPut } from '../../../api/client';
import { useToast } from '../../ui/Toast';
import RequestModal from './RequestModal';
import { blankRow } from './ItemRows';
import { CREATE_CHROME, EDIT_CHROME } from './requestModalChrome';
import useRequestFormOptions from './useRequestFormOptions';

const RequestModalContext = createContext(null);

/**
 * Hosts the MPR create and edit forms above the router, so any page can open
 * either one without owning the form itself. This replaces the Blade component
 * that app-shell.blade.php had to include purely so React could call its Alpine
 * `window.mprModalOpen()` — the SPA host page is plain HTML again.
 */
export function RequestModalProvider({ children }) {
    // { kind: 'create' } | { kind: 'edit', id, onSaved }
    const [target, setTarget] = useState(null);
    const [editing, setEditing] = useState(null);
    const { showToast } = useToast();
    const { options, error: optionsError } = useRequestFormOptions(!!target);
    // Held in a ref so a caller's callback identity cannot retrigger the effects
    // that load the record.
    const onSaved = useRef(null);

    const openNew = useCallback(() => { onSaved.current = null; setTarget({ kind: 'create' }); }, []);
    const openEdit = useCallback((id, callback) => {
        onSaved.current = callback ?? null;
        setEditing(null);
        setTarget({ kind: 'edit', id });
    }, []);
    const close = useCallback(() => { setTarget(null); setEditing(null); }, []);

    // The edit form needs the request's own values; the pipeline detail payload
    // shapes its items for the timeline, so they come from their own endpoint.
    useEffect(() => {
        if (target?.kind !== 'edit') return;

        apiGet(`/purchase/requests/${target.id}/edit`)
            .then((response) => setEditing(response.data))
            .catch(() => {
                showToast('Could not open that request for editing.', 'error');
                close();
            });
    }, [target, showToast, close]);

    const createInitial = useMemo(() => ({
        date: options?.today ?? '',
        project_name: '',
        requested_by_name: '',
        required_date_text: '',
        location: '',
        department: '',
        remarks: '',
        items: [blankRow(options?.today ?? '')],
    }), [options]);

    const editInitial = useMemo(() => (editing ? {
        date: editing.date ?? '',
        project_name: editing.project_name ?? '',
        requested_by_name: editing.requested_by_name ?? '',
        required_date_text: editing.required_date_text ?? '',
        location: editing.location ?? '',
        department: editing.department ?? '',
        remarks: editing.remarks ?? '',
        items: editing.items?.length ? editing.items : [blankRow('')],
    } : null), [editing]);

    async function submitCreate(payload) {
        const response = await apiPost('/purchase/requests', payload);
        showToast(response.message, 'success');
        if (onSaved.current) onSaved.current(response.data);
    }

    async function submitEdit(payload) {
        const response = await apiPut(`/purchase/requests/${target.id}`, payload);
        showToast(response.message, 'success');
        if (onSaved.current) onSaved.current(response.data);
    }

    const value = useMemo(() => ({ openNew, openEdit }), [openNew, openEdit]);

    return (
        <RequestModalContext.Provider value={value}>
            {children}

            <RequestModal
                {...CREATE_CHROME}
                open={target?.kind === 'create'}
                initial={createInitial}
                options={options}
                optionsError={optionsError}
                onClose={close}
                onSubmit={submitCreate}
            />

            {/* Mounted only once the record is in hand, so the fields are never
                shown blank and then filled in under the cursor. */}
            {target?.kind === 'edit' && editInitial && (
                <RequestModal
                    {...EDIT_CHROME}
                    open
                    subtitle={editing.request_number}
                    initial={editInitial}
                    options={options}
                    optionsError={optionsError}
                    onClose={close}
                    onSubmit={submitEdit}
                />
            )}
        </RequestModalContext.Provider>
    );
}

export function useRequestModal() {
    const context = useContext(RequestModalContext);
    if (!context) throw new Error('useRequestModal must be used within a RequestModalProvider');

    return context;
}
