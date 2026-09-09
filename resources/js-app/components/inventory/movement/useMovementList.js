import { useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';
import { typeLabel } from './movementStyles';

/**
 * Stock movements are an append-only ledger — the Blade table offered no edit or
 * delete, and neither does this. New rows arrive from the recorded event.
 */
export default function useMovementList() {
    const { items: movements, upsertItem } = useLiveList({
        endpoint: '/inventory/movements',
        channel: 'inventory',
        event: '.stock-movement.recorded',
        mergeKey: 'id',
        errorMessage: 'Failed to load stock movements.',
    });
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);

    function handleSaved(movement) {
        upsertItem(movement);
        setModalOpen(false);
    }

    const filtered = movements.filter((movement) => {
        const q = query.trim().toLowerCase();
        if (!q) return true;

        return [movement.item_code, movement.item_name, movement.warehouse_name,
            typeLabel(movement.type), movement.reference, movement.notes]
            .some((field) => String(field ?? '').toLowerCase().includes(q));
    });

    return { movements, filtered, query, setQuery, modalOpen, setModalOpen, handleSaved };
}
