import { useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';
import { useToast } from '../../ui/Toast';

/** Live list plus client-side search, shared by both viewports. */
export default function useOutputList() {
    const { items: outputs, upsertItem } = useLiveList({
        endpoint: '/production/outputs',
        channel: 'production',
        event: '.production-output.recorded',
        mergeKey: 'id',
        errorMessage: 'Failed to load production output.',
    });
    const [query, setQuery] = useState('');
    const { showToast } = useToast();

    function handleSaved(output) {
        upsertItem(output);
        showToast('Output recorded — stock updated.', 'success');
    }

    const q = query.trim().toLowerCase();
    const filtered = q
        ? outputs.filter((output) => [
            output.production_order_number, output.item_name,
            output.warehouse_name, output.notes,
        ].some((field) => String(field ?? '').toLowerCase().includes(q)))
        : outputs;

    return { outputs, filtered, query, setQuery, handleSaved };
}
