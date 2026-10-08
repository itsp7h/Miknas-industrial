import { CurrentActions, DoneActions, TimelineStyleContext, caption, stepCursor } from '../StageTimeline';
import { stageTime } from '../stageTimes';
import Icon from '../../../mobile/icons';
import { C } from '../../../mobile/theme';

// The phone's "Pipeline stages" card (SteelERP-Mobile-Designs-V2): the same
// steps, captions, times and actions as the desktop timeline — they come from
// StageTimeline's own helpers — drawn as the design draws them.
//
// What a finished step offers ("View", "View suppliers") sits at its right as
// a text link. What the step in progress asks for goes in the page's bottom
// bar instead (see BarActions below), where a thumb can reach it.

const ACCENT = '#2563EB';

// Done steps: their actions read as quiet text links.
export const INLINE_STYLES = {
    icons: false,
    VIEW: {
        display: 'inline-flex', alignItems: 'center', gap: 4, fontSize: 14, fontWeight: 500,
        color: ACCENT, background: 'none', border: 0, padding: '2px 0', textDecoration: 'none',
        cursor: 'pointer', whiteSpace: 'nowrap', font: 'inherit',
    },
    ACTION: {
        display: 'inline-flex', alignItems: 'center', gap: 4, fontSize: 13, fontWeight: 600,
        padding: '6px 12px', borderRadius: 10, border: 0, textDecoration: 'none',
        cursor: 'pointer', whiteSpace: 'nowrap',
    },
};

// The bottom bar: full-size buttons that share the row.
export const BAR_STYLES = {
    icons: false,
    ACTION: {
        flex: '1 1 40%', minHeight: 52, borderRadius: 16, border: 0, fontSize: 16, fontWeight: 600,
        display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6, padding: '0 14px',
        textDecoration: 'none', cursor: 'pointer', boxSizing: 'border-box', textAlign: 'center',
    },
    VIEW: {
        flex: '1 1 40%', minHeight: 52, borderRadius: 16, border: '1px solid #CBD5E1', background: '#FFFFFF',
        color: C.text, fontSize: 16, fontWeight: 600, display: 'flex', alignItems: 'center',
        justifyContent: 'center', gap: 6, padding: '0 14px', textDecoration: 'none', cursor: 'pointer',
        boxSizing: 'border-box', textAlign: 'center',
    },
};

// The caption already says who ("Signed by …"), so the time line's own
// "by …" would repeat it; keep when and how long.
function withoutActor(time) {
    return time.split(' · ').filter((part) => !/^by /.test(part)).join(' · ');
}

function Marker({ done, current }) {
    if (done) {
        return (
            <span style={{
                width: 24, height: 24, borderRadius: 12, background: ACCENT, color: '#FFFFFF', flexShrink: 0,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
            }}>
                <Icon name="check" size={14} strokeWidth={3} />
            </span>
        );
    }
    if (current) {
        return (
            <span style={{
                width: 24, height: 24, borderRadius: 12, background: '#FEF3C7', flexShrink: 0,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
            }}>
                <span style={{ width: 12, height: 12, borderRadius: 6, background: '#D97706' }} />
            </span>
        );
    }

    return (
        <span style={{
            width: 14, height: 14, margin: '5px 0', borderRadius: 7, border: '2px solid #CBD5E1',
            boxSizing: 'border-box', flexShrink: 0,
        }} />
    );
}

export default function MobileStageTimeline({ request, onAction }) {
    const stages = request.stages;
    const cursor = stepCursor(request);

    return (
        <section style={{ background: C.card, borderRadius: 20, padding: 16, display: 'flex', flexDirection: 'column' }}>
            <h2 style={{ margin: '0 0 12px', fontSize: 16, fontWeight: 600 }}>Pipeline stages</h2>
            {stages.map((stage, i) => {
                const done = i < cursor;
                const current = i === cursor;
                const last = i === stages.length - 1;
                const text = done || current ? caption(stage, request, current) : '';
                const time = done || current
                    ? stageTime(stages, stage, request.stage_history, { done, current })
                    : '';
                // The line below a marker: blue between finished steps, amber
                // into the step in progress, grey after it.
                let line = '#E2E8F0';
                if (i < cursor - 1) line = ACCENT;
                else if (i === cursor - 1) line = '#FCD34D';

                return (
                    <div key={stage} style={{ display: 'flex', gap: 12 }}>
                        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', width: 24, flexShrink: 0 }}>
                            <Marker done={done} current={current} />
                            {!last && <span style={{ width: 2, flex: 1, minHeight: 10, background: line }} />}
                        </div>
                        <div style={{
                            flex: 1, minWidth: 0, paddingBottom: last ? 0 : 16, paddingTop: done || current ? 0 : 3,
                            display: 'flex', flexDirection: 'column', gap: 10,
                        }}>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 2, minWidth: 0 }}>
                                <span style={{
                                    fontSize: 15,
                                    fontWeight: done || current ? 600 : 400,
                                    color: current ? '#B45309' : (done ? C.text : C.faint),
                                }}>
                                    {request.stage_labels[stage]}
                                </span>
                                {(text || (done && time)) && (
                                    <span
                                        data-testid={done ? `stage-time-${stage}` : undefined}
                                        style={{ fontSize: 13, color: C.muted, lineHeight: 1.4 }}
                                    >
                                        {[text, done ? withoutActor(time) : ''].filter(Boolean).join(' · ')}
                                    </span>
                                )}
                            </div>
                            {done && (
                                <div className="m-timeline-links" style={{ display: 'flex', flexWrap: 'wrap', gap: '6px 16px', marginTop: -4 }}>
                                    <TimelineStyleContext.Provider value={INLINE_STYLES}>
                                        <DoneActions stage={stage} r={request} on={onAction} />
                                    </TimelineStyleContext.Provider>
                                </div>
                            )}
                            {current && time && (
                                <span
                                    data-testid={`stage-time-${stage}`}
                                    style={{
                                        alignSelf: 'flex-start', display: 'flex', alignItems: 'center', gap: 6,
                                        fontSize: 13, fontWeight: 500, color: '#92400E', background: '#FEF3C7',
                                        padding: '6px 10px', borderRadius: 10,
                                    }}
                                >
                                    <Icon name="clock" size={16} />{time}
                                </span>
                            )}
                        </div>
                    </div>
                );
            })}
        </section>
    );
}

/**
 * What the step in progress asks for, as the bottom bar's buttons. Null when
 * there is nothing this person can do here, so the tab bar comes back.
 */
export function BarActions({ request, onAction, onChanged }) {
    const stage = request.stages[stepCursor(request)];
    if (!stage) return null;

    return (
        <TimelineStyleContext.Provider value={BAR_STYLES}>
            <CurrentActions stage={stage} r={request} on={onAction} onChanged={onChanged} />
        </TimelineStyleContext.Provider>
    );
}
