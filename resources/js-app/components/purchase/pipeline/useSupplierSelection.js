import { useEffect, useMemo, useState } from 'react';
import { apiGet } from '../../../api/client';

const NO_OPTIONS = { suppliers: [], selected_supplier_ids: [], items: [] };

/**
 * The supplier picker's state: which of the Blade modal's two methods is in
 * play, who is chosen (globally or per item), and each supplier's channel.
 */
export default function useSupplierSelection(requestId, open) {
    const [options, setOptions] = useState(NO_OPTIONS);
    const [loading, setLoading] = useState(true);
    const [mode, setMode] = useState(null);
    const [query, setQuery] = useState('');
    const [globalIds, setGlobalIds] = useState([]);
    const [itemSuppliers, setItemSuppliers] = useState({});
    const [channels, setChannels] = useState({});

    useEffect(() => {
        if (!open || !requestId) return;
        setLoading(true);
        setMode(null);
        setQuery('');
        setGlobalIds([]);
        setItemSuppliers({});
        setChannels({});

        apiGet(`/purchase/pipeline/${requestId}/form-options`)
            .then((response) => setOptions({ ...NO_OPTIONS, ...response }))
            .catch(() => setOptions(NO_OPTIONS))
            .finally(() => setLoading(false));
    }, [open, requestId]);

    /** Already-invited suppliers are shown but cannot be picked again. */
    const isInvited = (id) => options.selected_supplier_ids.some((selected) => Number(selected) === Number(id));

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();

        return q
            ? options.suppliers.filter((supplier) => [supplier.name, supplier.email, supplier.phone]
                .some((field) => String(field ?? '').toLowerCase().includes(q)))
            : options.suppliers;
    }, [options.suppliers, query]);

    function toggleGlobal(id) {
        setGlobalIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    }

    function toggleForItem(itemId, supplierId) {
        setItemSuppliers((prev) => {
            const current = prev[itemId] ?? [];

            return {
                ...prev,
                [itemId]: current.includes(supplierId)
                    ? current.filter((x) => x !== supplierId)
                    : [...current, supplierId],
            };
        });
    }

    const setChannel = (supplierId, channel) => setChannels((prev) => ({ ...prev, [supplierId]: channel }));
    const channelFor = (supplierId) => channels[supplierId] ?? 'email';

    /** Suppliers touched in by-item mode, so their channel pickers can be shown. */
    const assignedSupplierIds = useMemo(
        () => [...new Set(Object.values(itemSuppliers).flat())],
        [itemSuppliers]
    );

    const chosenCount = mode === 'by_item' ? assignedSupplierIds.length : globalIds.length;

    function payload() {
        const used = mode === 'by_item' ? assignedSupplierIds : globalIds;
        const channelMap = Object.fromEntries(used.map((id) => [id, channelFor(id)]));

        return mode === 'by_item'
            ? { mode: 'by_item', item_suppliers: itemSuppliers, channels: channelMap }
            : { mode: 'global', supplier_ids: globalIds, channels: channelMap };
    }

    return {
        options, loading, mode, setMode,
        query, setQuery, filtered, isInvited,
        globalIds, toggleGlobal,
        itemSuppliers, toggleForItem, assignedSupplierIds,
        channelFor, setChannel,
        chosenCount, payload,
    };
}
