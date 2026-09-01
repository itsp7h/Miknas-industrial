import { useCallback, useEffect, useState } from 'react';
import { apiDelete, apiGet } from '../../../api/client';
import { useToast } from '../../ui/Toast';

const num = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/**
 * Groups the flat list into one entry per product, in arrival order — the API
 * returns lines already sorted by product name, so a plain sweep is enough.
 */
export function groupByProduct(rows) {
    const groups = [];
    const byId = new Map();

    rows.forEach((row) => {
        const key = row.product_id ?? `unknown-${row.id}`;
        if (!byId.has(key)) {
            const group = {
                key,
                productName: row.product_name ?? '',
                productCode: row.product_code ?? '',
                lines: [],
            };
            byId.set(key, group);
            groups.push(group);
        }
        byId.get(key).lines.push(row);
    });

    return groups;
}

export { num };

/** Load, search, group, edit-open and delete — shared by both viewports. */
export default function useBomList() {
    const [rows, setRows] = useState([]);
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const { showToast } = useToast();

    // A bill of materials is reference data — it changes when someone edits it,
    // not from activity elsewhere, so there is nothing to subscribe to.
    const load = useCallback(() => apiGet('/production/bom')
        .then((response) => setRows(response.data))
        .catch(() => showToast('Failed to load the bill of materials.', 'error')),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    []);

    useEffect(() => {
        load();
    }, [load]);

    function openCreate() {
        setEditing(null);
        setModalOpen(true);
    }

    function openEdit(entry) {
        setEditing(entry);
        setModalOpen(true);
    }

    async function handleSaved() {
        setModalOpen(false);
        await load();
        showToast('BOM entry saved.', 'success');
    }

    async function handleDeleteConfirmed() {
        const entry = deleting;
        setDeleting(null);
        try {
            await apiDelete(`/production/bom/${entry.id}`);
            setRows((prev) => prev.filter((row) => row.id !== entry.id));
            showToast('BOM entry removed.', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to remove that entry.', 'error');
        }
    }

    const q = query.trim().toLowerCase();
    const filtered = q
        ? rows.filter((row) => [row.product_name, row.product_code, row.raw_material_name, row.unit_of_measure]
            .some((field) => String(field ?? '').toLowerCase().includes(q)))
        : rows;

    return {
        rows, filtered, groups: groupByProduct(filtered),
        query, setQuery,
        modalOpen, setModalOpen,
        editing, openCreate, openEdit, handleSaved,
        deleting, setDeleting, handleDeleteConfirmed,
    };
}
