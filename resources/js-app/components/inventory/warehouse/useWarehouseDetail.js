import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { apiGet } from '../../../api/client';
import { useToast } from '../../ui/Toast';

/**
 * One warehouse and what is stocked in it, split the way the two item pages
 * are: what is bought and consumed, and what is made and sold.
 */
export default function useWarehouseDetail() {
    const { id } = useParams();
    const [warehouse, setWarehouse] = useState(null);
    const [lines, setLines] = useState([]);
    const [meta, setMeta] = useState({});
    const [loading, setLoading] = useState(true);
    const [query, setQuery] = useState('');
    const { showToast } = useToast();

    useEffect(() => {
        setLoading(true);
        apiGet(`/inventory/warehouses/${id}`)
            .then((response) => {
                setWarehouse(response.data);
                setLines(response.items ?? []);
                setMeta(response.meta ?? {});
            })
            .catch(() => showToast('Failed to load the warehouse.', 'error'))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    const filtered = lines.filter((line) => {
        const q = query.trim().toLowerCase();
        if (!q) return true;

        return [line.item_code, line.item_name, line.category_path, line.unit_of_measure]
            .some((field) => String(field ?? '').toLowerCase().includes(q));
    });

    return {
        warehouse, meta, loading, query, setQuery,
        rawMaterials: filtered.filter((line) => line.category === 'raw_material'),
        finishedGoods: filtered.filter((line) => line.category === 'finished_good'),
        other: filtered.filter((line) => !['raw_material', 'finished_good'].includes(line.category)),
        matchCount: filtered.length,
        totalCount: lines.length,
    };
}
