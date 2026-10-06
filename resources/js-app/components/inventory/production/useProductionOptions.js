import { useEffect, useState } from 'react';
import { apiGet } from '../../../api/client';
import { echo } from '../../../echo';

const NONE = { finished_goods: [], raw_materials: [], warehouses: [], stock: [] };

/**
 * The run form's and recipe editor's reference data. Fetched when one of them
 * mounts, so what is on hand is as of opening the form, and kept current when
 * someone else saves a recipe meanwhile.
 */
export default function useProductionOptions() {
    const [options, setOptions] = useState(NONE);
    const [loaded, setLoaded] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        apiGet('/inventory/production/form-options')
            .then((response) => setOptions({ ...NONE, ...response }))
            .catch(() => setError('Could not load products, materials and warehouses.'))
            .finally(() => setLoaded(true));
    }, []);

    useEffect(() => {
        const channel = echo.private('inventory');
        channel.listen('.recipe.saved', (payload) => setRecipe(payload.product_id, payload.recipe));

        return () => channel.stopListening('.recipe.saved');
    }, []);

    function setRecipe(productId, recipe) {
        setOptions((prev) => ({
            ...prev,
            finished_goods: prev.finished_goods.map((product) => (
                String(product.id) === String(productId) ? { ...product, recipe } : product
            )),
        }));
    }

    return { options, loaded, error, setRecipe };
}
