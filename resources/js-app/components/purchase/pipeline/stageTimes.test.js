import { describe, it, expect } from 'vitest';
import { duration, stageTime } from './stageTimes';

const STAGES = ['draft', 'gm_approval', 'rfq', 'quoting', 'comparison', 'lpo', 'receiving', 'complete'];
const HISTORY = {
    draft: { reached_at: '2026-10-05T09:00:00Z', by: 'Ali' },
    gm_approval: { reached_at: '2026-10-05T09:30:00Z', by: 'Gm' },
    rfq: { reached_at: '2026-10-05T09:40:00Z', by: 'Admin User' },
    quoting: { reached_at: '2026-10-05T11:10:00Z', by: 'Admin User' },
    comparison: { reached_at: '2026-10-06T13:10:00Z', by: 'Yousif Dhneem' },
};

describe('duration', () => {
    it('words a span the way people say it', () => {
        expect(duration(20 * 1000)).toBe('under a minute');
        expect(duration(45 * 60000)).toBe('45m');
        expect(duration(3 * 3600000)).toBe('3h');
        expect(duration(200 * 60000)).toBe('3h 20m');
        expect(duration(52 * 3600000)).toBe('2d 4h');
        expect(duration(null)).toBe('');
    });
});

describe('stageTime', () => {
    it('gives the request its creation time and creator, with no duration', () => {
        const line = stageTime(STAGES, 'draft', HISTORY, { done: true });
        expect(line).toMatch(/05 Oct 2026/);
        expect(line).toContain('by Ali');
        expect(line).not.toContain('took');
    });

    it('times the GM signature from the request being raised', () => {
        expect(stageTime(STAGES, 'gm_approval', HISTORY, { done: true })).toMatch(/by Gm · took 30m$/);
    });

    it('finishes Select Suppliers when the RFQs go out, timed from the signature', () => {
        expect(stageTime(STAGES, 'rfq', HISTORY, { done: true })).toMatch(/by Admin User · took 1h 40m$/);
    });

    it('names the supplier whose quote moved it on', () => {
        expect(stageTime(STAGES, 'quoting', HISTORY, { done: true })).toMatch(/by Yousif Dhneem · took 1d 2h$/);
    });

    it('says how long the step in progress has been waiting', () => {
        const now = new Date('2026-10-06T15:40:00Z').getTime();
        expect(stageTime(STAGES, 'comparison', HISTORY, { current: true, now })).toBe('Waiting 2h 30m');
    });

    it('gives Complete its time and the total', () => {
        const history = { ...HISTORY, complete: { reached_at: '2026-10-07T09:00:00Z', by: null } };
        expect(stageTime(STAGES, 'complete', history, { current: true })).toMatch(/total 2d$/);
    });

    it('says nothing for a step with no recorded time', () => {
        expect(stageTime(STAGES, 'lpo', HISTORY, { done: true })).toBe('');
        expect(stageTime(STAGES, 'rfq', {}, { current: true })).toBe('');
    });
});
