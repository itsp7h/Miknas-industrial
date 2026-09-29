import { useEffect, useRef, useState } from 'react';

// The print page's sheet: 21cm at CSS's 96 px per inch.
const SHEET_WIDTH = 794;

/**
 * The LPO itself — the same server-rendered sheet Print opens and the PDF is
 * drawn from (`purchase/orders/{id}/print?embed=1`), so what the page shows is
 * what the supplier receives. It used to be a React lookalike that had drifted:
 * no logo, stamp, signature, VAT, Ref or Ship To.
 *
 * The frame is always the sheet's width and is scaled down to fit a narrower
 * column, so a phone shows the whole page small rather than a sideways scroll.
 * Its height follows the document once it has loaded (same origin).
 */
export default function LpoDocumentFrame({ order }) {
    const box = useRef(null);
    const frame = useRef(null);
    const [width, setWidth] = useState(SHEET_WIDTH);
    const [height, setHeight] = useState(1123);

    useEffect(() => {
        const node = box.current;
        if (!node || typeof ResizeObserver === 'undefined') return undefined;
        const observer = new ResizeObserver(([entry]) => setWidth(entry.contentRect.width));
        observer.observe(node);

        return () => observer.disconnect();
    }, []);

    function fitHeight() {
        const doc = frame.current?.contentDocument;
        if (doc?.documentElement) setHeight(doc.documentElement.scrollHeight);
    }

    const scale = width > 0 ? Math.min(1, width / SHEET_WIDTH) : 1;

    return (
        <div ref={box} style={{ maxWidth: SHEET_WIDTH, margin: '0 auto 24px' }}>
            <div style={{ height: height * scale, overflow: 'hidden' }}>
                <iframe
                    ref={frame}
                    title={`LPO ${order.po_number ?? ''}`.trim()}
                    src={`/purchase/orders/${order.id}/print?embed=1`}
                    onLoad={fitHeight}
                    scrolling="no"
                    style={{
                        width: SHEET_WIDTH, height, border: 0, display: 'block',
                        transform: scale < 1 ? `scale(${scale})` : undefined, transformOrigin: 'top left',
                    }}
                />
            </div>
        </div>
    );
}
