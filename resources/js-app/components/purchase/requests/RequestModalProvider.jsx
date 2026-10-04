import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { apiGet, apiPost, apiPut } from '../../../api/client';
import { useToast } from '../../ui/Toast';
import RequestModal from './RequestModal';
import { blankRow } from './ItemRows';
import { CREATE_CHROME, EDIT_CHROME } from './requestModalChrome';
import useRequestFormOptions from './useRequestFormOptions';

const RequestModalContext = createContext(null);

/**
 * Shown for the moment before the form can be seeded — the options fetch on the
 * very first open, or the record fetch when editing. Both modals read their
 * initial values once at mount, so neither may be mounted early.
 */
function LoadingOverlay() {
    return (
        <div style={{
            display: 'flex', position: 'fixed', inset: 0, zIndex: 9999, alignItems: 'center',
            justifyContent: 'center', background: 'rgba(15,23,42,0.55)', backdropFilter: 'blur(3px)',
        }}>
            <div style={{
                background: '#fff', borderRadius: '1rem', padding: '1.25rem 1.75rem',
                fontSize: 13, color: '#64748b',
            }}>
                Loading…
            </div>
        </div>
    );
}

/**
 * Where minimized forms dock: bottom-right, the first minimized furthest right
 * and each later one to its left, wrapping upwards on a narrow screen. Its
 * height is published as --request-dock-height so the toasts, which live in
 * the same corner, sit above it instead of behind it.
 */
function Dock({ onElement }) {
    const ref = useRef(null);

    useEffect(() => {
        const node = ref.current;
        onElement(node);
        const root = document.documentElement;
        const publish = () => root.style.setProperty('--request-dock-height', `${node.offsetHeight ? node.offsetHeight + 8 : 0}px`);
        publish();
        if (typeof ResizeObserver === 'undefined') return () => root.style.removeProperty('--request-dock-height');
        const observer = new ResizeObserver(publish);
        observer.observe(node);

        return () => {
            observer.disconnect();
            root.style.removeProperty('--request-dock-height');
        };
    }, [onElement]);

    return (
        <div
            ref={ref}
            style={{
                position: 'fixed', right: '1rem', bottom: '1rem', zIndex: 9990, display: 'flex',
                flexDirection: 'row-reverse', flexWrap: 'wrap-reverse', gap: '0.5rem',
                maxWidth: 'calc(100vw - 2rem)', pointerEvents: 'none',
            }}
        />
    );
}

/**
 * Hosts the MPR create and edit forms above the router, so any page can open
 * either one without owning the form itself. This replaces the Blade component
 * that app-shell.blade.php had to include purely so React could call its Alpine
 * `window.mprModalOpen()` — the SPA host page is plain HTML again.
 *
 * Several forms can be open at once: one on screen, the rest minimized to the
 * dock. Each stays mounted, so its own state — everything typed — survives
 * being minimized, and navigating between pages (this sits above the router).
 */
export function RequestModalProvider({ children }) {
    // Each open form: { key, kind: 'create' } | { key, kind: 'edit', id, record }
    const [drafts, setDrafts] = useState([]);
    // The one on screen; every other draft is minimized.
    const [active, setActive] = useState(null);
    const [dock, setDock] = useState(null);
    const { showToast } = useToast();
    const { options, error: optionsError } = useRequestFormOptions(drafts.length > 0);
    // Held in a ref so a caller's callback identity cannot retrigger the effects
    // that load a record. Keyed by draft.
    const onSaved = useRef({});
    const next = useRef(0);
    const latest = useRef(drafts);
    latest.current = drafts;

    const close = useCallback((key) => {
        setDrafts((current) => current.filter((d) => d.key !== key));
        setActive((current) => (current === key ? null : current));
        delete onSaved.current[key];
    }, []);

    // Takes the same callback openEdit does: submitCreate already hands the
    // saved row back through it, and the board needs that row to appear without
    // waiting for a broadcast it may never hear.
    const openNew = useCallback((callback) => {
        const key = `create-${next.current += 1}`;
        // openNew takes no required argument, so a caller can wire it straight to
        // onClick and hand us a click event. Only a function is a callback.
        onSaved.current[key] = typeof callback === 'function' ? callback : null;
        setDrafts((current) => [...current, { key, kind: 'create' }]);
        setActive(key);
    }, []);

    const openEdit = useCallback((id, callback) => {
        // Already open — minimized, most likely: bring that one back rather than
        // load a second copy over its unsaved changes.
        const existing = latest.current.find((d) => d.kind === 'edit' && d.id === id);
        if (existing) {
            setActive(existing.key);

            return;
        }
        const key = `edit-${id}`;
        onSaved.current[key] = callback ?? null;
        setDrafts((current) => [...current, { key, kind: 'edit', id, record: null }]);
        setActive(key);

        // The edit form needs the request's own values; the pipeline detail
        // payload shapes its items for the timeline, so they come from their
        // own endpoint.
        apiGet(`/purchase/requests/${id}/edit`)
            .then((response) => setDrafts((current) => current.map((d) => (
                d.key === key ? { ...d, record: response.data } : d
            ))))
            .catch(() => {
                showToast('Could not open that request for editing.', 'error');
                close(key);
            });
    }, [showToast, close]);

    const minimize = useCallback((key) => setActive((current) => (current === key ? null : current)), []);

    const createInitial = useMemo(() => ({
        date: options?.today ?? '',
        company_name: '',
        project_name: '',
        requested_by_name: '',
        required_date_text: '',
        location: '',
        department: '',
        remarks: '',
        items: [blankRow(options?.today ?? '')],
    }), [options]);

    const editInitials = useMemo(() => Object.fromEntries(drafts
        .filter((d) => d.kind === 'edit' && d.record)
        .map((d) => [d.key, {
            date: d.record.date ?? '',
            company_name: d.record.company_name ?? '',
            project_name: d.record.project_name ?? '',
            requested_by_name: d.record.requested_by_name ?? '',
            required_date_text: d.record.required_date_text ?? '',
            location: d.record.location ?? '',
            department: d.record.department ?? '',
            remarks: d.record.remarks ?? '',
            items: d.record.items?.length ? d.record.items : [blankRow('')],
        }])), [drafts]);

    async function submitFor(draft, payload) {
        const response = draft.kind === 'create'
            ? await apiPost('/purchase/requests', payload)
            : await apiPut(`/purchase/requests/${draft.id}`, payload);
        showToast(response.message, 'success');
        const callback = onSaved.current[draft.key];
        if (callback) callback(response.data);
    }

    const value = useMemo(() => ({ openNew, openEdit }), [openNew, openEdit]);
    const onScreen = drafts.find((d) => d.key === active);
    const loading = onScreen && (onScreen.kind === 'create'
        ? !options && !optionsError
        : !onScreen.record);

    return (
        <RequestModalContext.Provider value={value}>
            {children}

            {loading && <LoadingOverlay />}

            {/* Each form is mounted only once it can be seeded, and keyed by its
                draft: the form seeds itself from `initial` at mount, so it
                never needs an effect that could reset it mid-typing. The edit
                form waits for its record, so its fields are never shown blank
                and then filled in under the cursor. */}
            {drafts.map((draft) => {
                const ready = draft.kind === 'create' ? (options || optionsError) : draft.record;
                if (!ready) return null;
                const chrome = draft.kind === 'create' ? CREATE_CHROME : EDIT_CHROME;

                return (
                    <RequestModal
                        {...chrome}
                        key={draft.key}
                        subtitle={draft.kind === 'edit' ? draft.record.request_number : chrome.subtitle}
                        initial={draft.kind === 'create' ? createInitial : editInitials[draft.key]}
                        options={options}
                        optionsError={optionsError}
                        onClose={() => close(draft.key)}
                        onSubmit={(payload) => submitFor(draft, payload)}
                        minimized={draft.key !== active}
                        onMinimize={() => minimize(draft.key)}
                        onRestore={() => setActive(draft.key)}
                        dock={dock}
                    />
                );
            })}

            <Dock onElement={setDock} />
        </RequestModalContext.Provider>
    );
}

export function useRequestModal() {
    const context = useContext(RequestModalContext);
    if (!context) throw new Error('useRequestModal must be used within a RequestModalProvider');

    return context;
}
