import { describe, it, expect, afterEach } from 'vitest';
import { render, screen, act } from '@testing-library/react';
import useViewport from './useViewport';

function Probe() {
    const viewport = useViewport();
    return <div>viewport:{viewport}</div>;
}

function setWidth(width) {
    window.innerWidth = width;
    window.dispatchEvent(new Event('resize'));
}

describe('useViewport', () => {
    afterEach(() => setWidth(1024));

    it('reports desktop above the 768px breakpoint', () => {
        setWidth(1024);
        render(<Probe />);
        expect(screen.getByText('viewport:desktop')).toBeInTheDocument();
    });

    it('reports mobile below the 768px breakpoint', () => {
        setWidth(500);
        render(<Probe />);
        expect(screen.getByText('viewport:mobile')).toBeInTheDocument();
    });

    it('switches live on resize without remounting', () => {
        setWidth(1024);
        render(<Probe />);
        expect(screen.getByText('viewport:desktop')).toBeInTheDocument();

        act(() => setWidth(400));
        expect(screen.getByText('viewport:mobile')).toBeInTheDocument();
    });
});
