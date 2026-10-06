import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { apiGet } from '../../../api/client';
import { echo } from '../../../echo';
import { useToast } from '../../ui/Toast';

/**
 * What one project has been charged: the consumable lines of its confirmed
 * GRNs. A GRN confirmed anywhere refetches it, so the list grows while open.
 */
export default function useProjectCosts() {
    const { id } = useParams();
    const [project, setProject] = useState(null);
    const [lines, setLines] = useState([]);
    const [meta, setMeta] = useState({ total: 0, line_count: 0, grn_count: 0 });
    const [loading, setLoading] = useState(true);
    const [query, setQuery] = useState('');
    const { showToast } = useToast();

    const load = useCallback((quiet = false) => {
        if (!quiet) setLoading(true);

        return apiGet(`/settings/projects/${id}/costs`)
            .then((response) => {
                setProject(response.data);
                setLines(response.lines ?? []);
                setMeta(response.meta ?? {});
            })
            .catch(() => showToast('Failed to load the project costs.', 'error'))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    useEffect(() => {
        load();
    }, [load]);

    useEffect(() => {
        const channel = echo.private('purchase');
        const handler = (grn) => {
            if (grn?.status === 'confirmed') load(true);
        };
        channel.listen('.grn.saved', handler);

        return () => channel.stopListening('.grn.saved', handler);
    }, [load]);

    const q = query.trim().toLowerCase();
    const filtered = !q ? lines : lines.filter((line) => (
        [line.grn_number, line.po_number, line.supplier_name, line.item_code, line.item_name]
            .some((field) => String(field ?? '').toLowerCase().includes(q))
    ));

    return {
        project, meta, loading, query, setQuery,
        lines: filtered,
        matchCount: filtered.length,
        totalCount: lines.length,
        filteredTotal: filtered.reduce((sum, line) => sum + Number(line.amount || 0), 0),
    };
}
