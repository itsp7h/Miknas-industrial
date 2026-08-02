import { useEffect, useState } from 'react';

const BREAKPOINT = 768;

function readViewport() {
    return window.innerWidth < BREAKPOINT ? 'mobile' : 'desktop';
}

export default function useViewport() {
    const [viewport, setViewport] = useState(readViewport);

    useEffect(() => {
        function onResize() {
            setViewport(readViewport());
        }
        window.addEventListener('resize', onResize);
        return () => window.removeEventListener('resize', onResize);
    }, []);

    return viewport;
}
