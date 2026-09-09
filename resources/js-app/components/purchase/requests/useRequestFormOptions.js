import { useEffect, useState } from 'react';
import { apiGet } from '../../../api/client';

// Kept across opens so the modal renders its dropdowns immediately the second
// time, but still refetched on every open — a project added in Settings should
// not need a page reload to show up here.
let cached = null;

export default function useRequestFormOptions(active) {
    const [options, setOptions] = useState(cached);
    const [error, setError] = useState('');

    useEffect(() => {
        if (!active) return;

        apiGet('/purchase/requests/form-options')
            .then((response) => { cached = response; setOptions(response); })
            .catch(() => setError('Could not load projects and departments.'));
    }, [active]);

    return { options, error };
}
