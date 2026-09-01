import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import PipelineDialogs from '../../../components/purchase/pipeline/PipelineDialogs';
import PipelineHeader from '../../../components/purchase/pipeline/PipelineHeader';
import PipelineSidebar from '../../../components/purchase/pipeline/PipelineSidebar';
import StageTimeline from '../../../components/purchase/pipeline/StageTimeline';
import usePipelineRequest from '../../../components/purchase/pipeline/usePipelineRequest';
import { useSetPageTitle } from '../../../layouts/PageTitleContext';

export default function PipelinePage() {
    const { id } = useParams();
    const { request, loading, ...actions } = usePipelineRequest(id);
    // Which dialog is open, if any — the timeline names it.
    const [dialog, setDialog] = useState(null);

    // Matches the Blade page's @section('title', 'Pipeline — ' . $pr->request_number).
    useSetPageTitle(request ? `Pipeline — ${request.request_number}` : null);

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <Link to="/app/purchase/pipeline" style={{ fontSize: 13, color: '#2563eb', textDecoration: 'none' }}>
                    ← Purchase Pipeline
                </Link>
            </div>

            {loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}
            {!loading && !request && (
                <p style={{ fontSize: 14, color: '#64748b' }}>That purchase request could not be found.</p>
            )}

            {request && (
                <>
                    <PipelineHeader request={request} compact />
                    {/* Single column: the timeline is already a vertical stepper, and
                        the sidebar cards linearise beneath it in the same order. */}
                    <StageTimeline request={request} compact onAction={setDialog} />
                    <div style={{ marginTop: 16 }}>
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
