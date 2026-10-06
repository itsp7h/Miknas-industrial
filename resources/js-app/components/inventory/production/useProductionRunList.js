import { useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';

/** Runs, newest first, kept live from the recorded event; client-side search. */
export default function useProductionRunList() {
    const { items, upsertItem } = useLiveList({
        endpoint: '/inventory/production',
        channel: 'inventory',
        event: '.production-run.recorded',
        mergeKey: 'id',
        errorMessage: 'Failed to load production runs.',
    });
    const [query, setQuery] = useState('');
    const [formOpen, setFormOpen] = useState(false);
    const [recipesOpen, setRecipesOpen] = useState(false);
    const [viewing, setViewing] = useState(null);

    // A broadcast lands at the end of the list; the newest run belongs on top.
    const runs = [...items].sort((a, b) => b.id - a.id);

    const q = query.trim().toLowerCase();
    const filtered = q
        ? runs.filter((run) => [
            run.run_number, run.item_code, run.item_name, run.warehouse_name, run.notes, run.created_by_name,
            ...(run.lines ?? []).map((line) => line.item_name),
        ].some((field) => String(field ?? '').toLowerCase().includes(q)))
        : runs;

    function handleSaved(run) {
        upsertItem(run);
        setFormOpen(false);
    }

    return {
        runs, filtered, query, setQuery, handleSaved,
        formOpen, setFormOpen, recipesOpen, setRecipesOpen, viewing, setViewing,
    };
}
