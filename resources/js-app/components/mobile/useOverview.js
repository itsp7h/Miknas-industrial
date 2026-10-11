import { useEffect, useRef, useState } from 'react';
import { apiGet } from '../../api/client';
import { echo } from '../../echo';

// Anything that can move a figure on Home or More. The events carry rows, not
// totals, so the overview is re-read rather than patched — once, after a
// burst settles, since one GRN confirm fires several of these together.
const LISTEN = {
    purchase: [
        '.purchase-request.created', '.purchase-request.updated', '.purchase-request.stage-changed',
        '.purchase-request.deleted', '.grn.saved', '.grn.deleted', '.purchase-order.saved',
        '.purchase-order.deleted', '.supplier-invoice.saved', '.supplier-invoice.deleted',
        '.supplier-payment.recorded', '.supplier.saved', '.supplier.deleted',
    ],
    inventory: ['.stock-movement.recorded', '.warehouse.saved'],
};

/**
 * `GET /dashboard/overview`, kept live: the pipeline by stage, what is waiting
 * on this person, and the counts beside the More entries. Null until loaded;
 * a failed load leaves it null and the screens show their placeholders.
 */
export default function useOverview() {
    const [overview, setOverview] = useState(null);
    const timer = useRef(null);

    useEffect(() => {
        let alive = true;
        const load = () => apiGet('/dashboard/overview')
            .then((data) => { if (alive) setOverview(data); })
            .catch(() => {});
        const reload = () => {
            clearTimeout(timer.current);
            timer.current = setTimeout(load, 400);
        };

        load();
        const channels = Object.entries(LISTEN).map(([name, events]) => {
            const ch = echo.private(name);
            events.forEach((event) => ch.listen(event, reload));

            return [ch, events];
        });

        return () => {
            alive = false;
            clearTimeout(timer.current);
            channels.forEach(([ch, events]) => events.forEach((event) => ch.stopListening(event)));
        };
    }, []);

    return overview;
}
