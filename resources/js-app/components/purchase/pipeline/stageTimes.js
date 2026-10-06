import { datetime } from '../requests/RequestSheet';

/**
 * Which recorded stage marks each timeline step as done.
 *
 * The timeline's cursor passes a step once its work is done (see
 * StageTimeline). The request is created at 'draft' and signing moves it to
 * 'gm_approval', so those two steps are done the moment their own stage is
 * reached. From Select Suppliers on, a step stays current while the request
 * sits at it and is done when the request moves to the next stage: suppliers
 * are selected at 'rfq' and the step finishes when the RFQs go out ('quoting').
 */
const DONE_WHEN = {
    draft: 'draft',
    gm_approval: 'gm_approval',
    rfq: 'quoting',
    quoting: 'comparison',
    comparison: 'lpo',
    lpo: 'receiving',
    receiving: 'complete',
    complete: 'complete',
};

/** "under a minute", "45m", "3h 20m", "2d 4h". */
export function duration(ms) {
    if (ms == null || Number.isNaN(ms) || ms < 0) return '';
    const minutes = Math.floor(ms / 60000);
    if (minutes < 1) return 'under a minute';
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return minutes % 60 ? `${hours}h ${minutes % 60}m` : `${hours}h`;
    const days = Math.floor(hours / 24);

    return hours % 24 ? `${days}d ${hours % 24}h` : `${days}d`;
}

const at = (history, stage) => {
    const value = history?.[stage]?.reached_at;

    return value ? new Date(value).getTime() : null;
};

/** "30 Sep 2026, 14:35", with the en-GB "Sept" some browsers write as "Sep". */
const when = (ms) => datetime(new Date(ms).toISOString()).replace('Sept', 'Sep');

/**
 * The time line under a step: when it was done, by whom and how long it took
 * — or, for the step in progress, how long it has been waiting. Empty when
 * nothing was recorded for it.
 */
export function stageTime(stages, stage, history, { done, current, now = Date.now() }) {
    const i = stages.indexOf(stage);
    const previous = i > 0 ? at(history, DONE_WHEN[stages[i - 1]]) : null;

    if (done || (stage === 'complete' && current && at(history, 'complete'))) {
        const finished = at(history, DONE_WHEN[stage]);
        if (finished == null) return '';

        if (stage === 'complete') {
            const started = at(history, 'draft');
            const total = started != null ? duration(finished - started) : '';

            return [when(finished), total && `total ${total}`].filter(Boolean).join(' · ');
        }

        const by = history[DONE_WHEN[stage]]?.by;
        const took = previous != null ? duration(finished - previous) : '';
        // The LPO step is done when the LPOs are approved; the caption above
        // already says who issued them, so this one says who approved.
        const verb = stage === 'lpo' ? 'approved by' : 'by';

        return [when(finished), by && `${verb} ${by}`, took && `took ${took}`].filter(Boolean).join(' · ');
    }

    if (current && previous != null) {
        const waiting = duration(now - previous);

        return waiting ? `Waiting ${waiting}` : '';
    }

    return '';
}
