import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import PipelineDialogs from '../../../components/purchase/pipeline/PipelineDialogs';
import PipelineHeader from '../../../components/purchase/pipeline/PipelineHeader';
import PipelineSidebar from '../../../components/purchase/pipeline/PipelineSidebar';
import StageTimeline from '../../../components/purchase/pipeline/StageTimeline';
import usePipelineRequest from '../../../components/purchase/pipeline/usePipelineRequest';
import { useRequestModal } from '../../../components/purchase/requests/RequestModalProvider';
import { useSetPageTitle } from '../../../layouts/PageTitleContext';

export default function PipelinePage() {
    const { id } = useParams();
    const { request, loading, applyUpdate, ...actions } = usePipelineRequest(id);
    const { openEdit } = useRequestModal();
    // Which dialog is open, if any — the timeline names it.
    const [dialog, setDialog] = useState(null);

    // Matches the Blade page's @section('title', 'Pipeline — ' . $pr->request_number).
    useSetPageTitle(request ? `Pipeline — ${request.request_number}` : null);

    return (
        <div>
            <div style={{ marginBottom: 20 }}>
                <Link
                    to="/app/purchase/pipeline"
                    style={{
                        fontSize: 13, color: '#2563eb', textDecoration: 'none',
                        display: 'inline-flex', alignItems: 'center', gap: 5,
                    }}
                >
                    <svg width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    Purchase Pipeline
                </Link>
            </div>

            {loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}
            {!loading && !request && (
                <p style={{ fontSize: 14, color: '#64748b' }}>That purchase request could not be found.</p>
            )}

            {request && (
                <>
                    <PipelineHeader request={request} onEdit={() => openEdit(id, applyUpdate)} />
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 320px', gap: 20, alignItems: 'start' }}>
                        <StageTimeline request={request} onAction={setDialog} />
                        <PipelineSidebar request={request} />
                    </div>
                    <PipelineDialogs
                        open={dialog} onClose={() => setDialog(null)}
                        request={request} actions={actions}
                    />
                </>
            )}
        </div>
    );
}
