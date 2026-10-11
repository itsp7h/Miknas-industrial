import { useEffect, useMemo, useState, useCallback } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import useLiveList from '../../../hooks/useLiveList';
import { echo } from '../../../echo';
import { useRequestModal } from '../../../components/purchase/requests/RequestModalProvider';
import { useAccess } from '../../../layouts/AccessContext';
import {
    Chip, ChipRow, DocNo, EmptyState, Hero, HeroButton, MobilePage, Pill, SearchField, Segmented,
} from '../../../components/mobile/ui';
import { C } from '../../../components/mobile/theme';
import { STAGE_META, stageMeta } from '../../../components/mobile/stages';
import { shortDate } from '../../../components/mobile/format';

const STAGE_ORDER = Object.keys(STAGE_META);

// Mirrors App\Policies\PurchaseRequestPolicy::ACTIVE_PIPELINE_STAGES exactly — kept
// in sync by hand since the frontend can't import PHP constants.
const ACTIVE_PIPELINE_STAGES = ['rfq', 'quoting', 'comparison', 'lpo', 'receiving', 'complete'];

export default function PipelineBoardPage({
    currentUserId, canViewAllPurchaseRequests, canViewActivePipeline, canViewOwnPurchaseRequests,
} = {}) {
    const { openNew } = useRequestModal();
    // Disabled, not hidden, for anyone who may view but not create; see desktop.
    const canCreate = useAccess().can('pipeline.create');
    const [params, setParams] = useSearchParams();
    const { items, setItems } = useLiveList({
        endpoint: '/purchase/pipeline',
        channel: 'purchase',
        mergeKey: 'id',
        errorMessage: 'Failed to load the purchase pipeline.',
    });

    // .purchase-request.created goes out unfiltered on the shared `private-purchase`
    // channel to every authenticated user (the API endpoint filters by permission,
    // the broadcast doesn't). Mirror the API's own filter here so a view-own user
    // doesn't see other users' new requests, and a view-active-pipeline user doesn't
    // see newly-created draft-stage requests.
    const acceptRow = useCallback((payload) => {
        const allowed = canViewAllPurchaseRequests
            || (canViewActivePipeline && ACTIVE_PIPELINE_STAGES.includes(payload.stage))
            || (canViewOwnPurchaseRequests && payload.requested_by_id === currentUserId);
        if (!allowed) return;
        // The endpoint sorts newest-first (`latest()`), so a row the board has
        // not seen goes on top, not on the end. An upsert of a row already
        // there keeps its place.
        setItems((prev) => (
            prev.some((item) => item.id === payload.id)
                ? prev.map((item) => (item.id === payload.id ? payload : item))
                : [payload, ...prev]
        ));
    }, [currentUserId, canViewAllPurchaseRequests, canViewActivePipeline, canViewOwnPurchaseRequests, setItems]);

    // An edit rewrites these very columns, and PurchaseRequestUpdated
    // broadcasts the same payload shape, so one handler upserts both.
    useEffect(() => {
        const ch = echo.private('purchase');
        ch.listen('.purchase-request.created', acceptRow);
        ch.listen('.purchase-request.updated', acceptRow);
        return () => {
            ch.stopListening('.purchase-request.created');
            ch.stopListening('.purchase-request.updated');
        };
    }, [acceptRow]);

    // ?new=1 opens the MPR form straight away: it is where the dashboard's
    // "New Purchase Request" action and the old /purchase/requests/create URL
    // both land, and the form is a modal rather than a page of its own. The
    // param is stripped so a reload or a back-navigation does not reopen it.
    useEffect(() => {
        if (params.get('new') !== '1') return;
        if (canCreate) openNew(acceptRow);
        params.delete('new');
        setParams(params, { replace: true });
    }, [params, setParams, openNew, acceptRow, canCreate]);

    // A deleted request has to leave every board showing it.
    useEffect(() => {
        const ch = echo.private('purchase');
        const handleDeleted = (payload) => {
            setItems((prev) => prev.filter((item) => item.id !== payload.id));
        };
        ch.listen('.purchase-request.deleted', handleDeleted);
        return () => ch.stopListening('.purchase-request.deleted');
    }, [setItems]);

    // .purchase-request.stage-changed carries only {id, request_number, stage} — merge
    // it shallowly onto the matching row so company_name/department/etc. survive.
    useEffect(() => {
        const ch = echo.private('purchase');
        const handleStageChanged = (payload) => {
            setItems((prev) => prev.map((item) => (
                item.id === payload.id ? { ...item, ...payload } : item
            )));
        };
        ch.listen('.purchase-request.stage-changed', handleStageChanged);
        return () => ch.stopListening('.purchase-request.stage-changed');
    }, [setItems]);

    const [tab, setTab] = useState('active');
    const [stage, setStage] = useState('all');
    const [query, setQuery] = useState('');

    const active = items.filter((r) => r.stage !== 'complete');
    const completed = items.filter((r) => r.stage === 'complete');
    const rows = tab === 'active' ? active : completed;

    // The stage chips: every stage the open tab has a request in, in pipeline
    // order, with its count. Switching tab clears a stage filter the other
    // tab could not satisfy.
    const stageCounts = useMemo(() => STAGE_ORDER
        .map((key) => ({ key, count: rows.filter((r) => r.stage === key).length }))
        .filter((s) => s.count > 0), [rows]);
    const byStage = stage === 'all' ? rows : rows.filter((r) => r.stage === stage);

    const filteredRows = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return byStage;
        return byStage.filter((row) => (
            [row.request_number, row.company_name, row.project_name, row.department, row.requested_by_name]
                .filter(Boolean)
                .some((field) => field.toLowerCase().includes(q))
        ));
    }, [byStage, query]);

    function pickTab(next) {
        setTab(next);
        setStage('all');
    }

    return (
        <MobilePage gap={14}>
            <Hero
                zone="purchase"
                variant="root"
                eyebrow="Material purchase requests"
                title="Pipeline"
                action={(
                    // Disabled, not hidden, for anyone who may view but not create.
                    <HeroButton
                        zone="purchase"
                        label="New purchase request"
                        title={canCreate ? 'New purchase request' : 'You do not have permission to create purchase requests'}
                        onClick={() => openNew(acceptRow)}
                        disabled={!canCreate}
                    />
                )}
            />

            <Segmented
                ariaLabel="Requests"
                value={tab}
                onChange={pickTab}
                options={[
                    { key: 'active', label: `Active · ${active.length}` },
                    { key: 'completed', label: `Completed · ${completed.length}` },
                ]}
            />

            <SearchField value={query} onChange={setQuery} placeholder="Search requests" />

            {stageCounts.length > 1 && (
                <ChipRow>
                    <Chip active={stage === 'all'} onClick={() => setStage('all')}>All {rows.length}</Chip>
                    {stageCounts.map((s) => (
                        <Chip key={s.key} active={stage === s.key} onClick={() => setStage(s.key)}>
                            {stageMeta(s.key).label} {s.count}
                        </Chip>
                    ))}
                </ChipRow>
            )}

            {query.trim() !== '' && (
                <span style={{ fontSize: 13, color: C.muted, padding: '0 4px' }}>
                    {filteredRows.length} of {byStage.length}
                </span>
            )}

            {filteredRows.length === 0 ? (
                <EmptyState icon="pipeline" title={query.trim() !== '' ? 'No matching requests' : 'No requests'} />
            ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                    {filteredRows.map((row) => <RequestCard key={row.id} row={row} />)}
                </div>
            )}
        </MobilePage>
    );
}

function RequestCard({ row }) {
    const meta = stageMeta(row.stage);
    const by = row.requested_by_name || '—';

    return (
        <Link
            to={`/app/purchase/pipeline/${row.id}`}
            style={{
                background: C.card, borderRadius: 20, padding: 16, display: 'flex', flexDirection: 'column',
                gap: 10, textDecoration: 'none', color: C.text,
            }}
        >
            <span style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8 }}>
                <DocNo color={C.text} size={14}>{row.request_number}</DocNo>
                <Pill tone={meta.tone}>{meta.label}</Pill>
            </span>
            <span style={{ display: 'flex', flexDirection: 'column', gap: 3, minWidth: 0 }}>
                <span style={{ fontSize: 16, fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {row.department || row.company_name || '—'}
                </span>
                <span style={{ fontSize: 14, color: C.muted, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {[row.company_name, row.project_name].filter(Boolean).join(' · ') || '—'}
                </span>
            </span>
            <span style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 2 }}>
                <span style={{
                    width: 22, height: 22, borderRadius: 11, background: C.hairline, color: C.text2,
                    fontSize: 11, fontWeight: 600, display: 'flex', alignItems: 'center', justifyContent: 'center',
                }}>
                    {by.charAt(0).toUpperCase()}
                </span>
                <span style={{ flex: 1, minWidth: 0, fontSize: 14, color: C.muted, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {by}
                </span>
                <span style={{ fontSize: 14, color: C.muted, flexShrink: 0 }}>{shortDate(row.date)}</span>
            </span>
        </Link>
    );
}
