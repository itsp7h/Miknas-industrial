import { useState } from 'react';
import { useParams } from 'react-router-dom';
import PipelineDialogs from '../../../components/purchase/pipeline/PipelineDialogs';
import { stepCursor } from '../../../components/purchase/pipeline/StageTimeline';
import MobileStageTimeline, { BarActions } from '../../../components/purchase/pipeline/mobile/MobileStageTimeline';
import MobilePipelineDetails from '../../../components/purchase/pipeline/mobile/MobilePipelineDetails';
import usePipelineRequest from '../../../components/purchase/pipeline/usePipelineRequest';
import { formatDate } from '../../../components/purchase/pipeline/pipelineStyles';
import { useRequestModal } from '../../../components/purchase/requests/RequestModalProvider';
import { useSetPageTitle } from '../../../layouts/PageTitleContext';
import {
    ActionSheet, BarButton, BottomBar, EmptyState, Hero, Loading, MobilePage, Pill,
} from '../../../components/mobile/ui';
import { MONO } from '../../../components/mobile/theme';

// A purchase request's pipeline (SteelERP-Mobile-Designs-V2): the number,
// where it is and how far along in the header; the stages; the request's
// facts; and what the step in progress asks for in the bar along the bottom.

function Progress({ request }) {
    const total = request.stages.length;
    const cursor = Math.min(stepCursor(request), total);
    const finished = request.is_done;

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
            <div style={{ display: 'flex', gap: 3 }} aria-hidden="true">
                {request.stages.map((stage, i) => {
                    let background = 'rgba(255,255,255,0.28)';
                    if (finished || i < cursor) background = '#FFFFFF';
                    else if (i === cursor) background = '#FBBF24';

                    return <div key={stage} style={{ flex: 1, height: 5, borderRadius: 3, background }} />;
                })}
            </div>
            <span style={{ fontSize: 12, color: 'rgba(255,255,255,0.85)' }}>
                {finished ? 'Complete' : `Stage ${Math.min(cursor + 1, total)} of ${total}`}
            </span>
        </div>
    );
}

export default function PipelinePage() {
    const { id } = useParams();
    const { request, loading, applyUpdate, ...actions } = usePipelineRequest(id);
    const { openEdit } = useRequestModal();
    // Which dialog is open, if any — the timeline and the bottom bar name it.
    const [dialog, setDialog] = useState(null);
    const [menu, setMenu] = useState(false);

    useSetPageTitle(request ? `Pipeline — ${request.request_number}` : null);

    const back = { to: '/app/purchase/pipeline', label: 'Pipeline' };

    if (!request) {
        return (
            <MobilePage>
                <Hero zone="purchase" back={back} title={loading ? 'Loading…' : 'Not found'} />
                {loading
                    ? <Loading />
                    : <EmptyState icon="pipeline" title="That purchase request could not be found." />}
            </MobilePage>
        );
    }

    const current = request.stages[stepCursor(request)] ?? request.stage;

    return (
        <MobilePage gap={16}>
            <Hero
                zone="purchase"
                back={back}
                title={request.request_number}
                titleStyle={{ fontFamily: MONO, fontSize: 22, fontWeight: 500, letterSpacing: '-0.01em' }}
                actions={(
                    <>
                        {request.permissions.update && (
                            <BarButton label="Edit request" onClick={() => openEdit(id, applyUpdate)}>Edit</BarButton>
                        )}
                        <BarButton icon="more" label="More options" onClick={() => setMenu(true)} />
                    </>
                )}
            >
                <div style={{ display: 'flex', flexDirection: 'column', gap: 10, marginTop: -8 }}>
                    <div>
                        <Pill tone={request.is_done ? 'green' : 'amber'}>{request.stage_labels[current] ?? current}</Pill>
                    </div>
                    <span style={{ fontSize: 14, color: 'rgba(255,255,255,0.85)', lineHeight: 1.45 }}>
                        {[request.company_name, request.department, request.requested_by_name, request.date && formatDate(request.date)]
                            .filter(Boolean).join(' · ')}
                    </span>
                    <Progress request={request} />
                </div>
            </Hero>

            <MobileStageTimeline request={request} onAction={setDialog} />
            <MobilePipelineDetails request={request} />

            <BottomBar>
                <BarActions request={request} onAction={setDialog} onChanged={actions.reload} />
            </BottomBar>

            <ActionSheet
                open={menu}
                onClose={() => setMenu(false)}
                title={request.request_number}
                options={[
                    { label: 'View full request', to: `/app/purchase/requests/${request.id}` },
                    { label: 'Print MPR', href: `/purchase/requests/${request.id}/print`, newTab: true },
                ]}
            />

            <PipelineDialogs
                open={dialog} onClose={() => setDialog(null)}
                request={request} actions={actions}
            />
        </MobilePage>
    );
}
