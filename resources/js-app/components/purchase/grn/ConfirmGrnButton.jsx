import { confirmBlockedReason } from './grnStyles';

/**
 * Confirm, which receives the stock. Shown but disabled, with the reason, while
 * a document is missing (CLAUDE.md #14) — the receipt can be saved like that,
 * not completed.
 */
export default function ConfirmGrnButton({ grn, onClick, className = 'btn-success', style, children = 'Confirm' }) {
    const blocked = confirmBlockedReason(grn);

    return (
        <button
            type="button" onClick={onClick} className={className} disabled={!!blocked} title={blocked ?? undefined}
            style={blocked ? { ...style, opacity: 0.5, cursor: 'not-allowed' } : style}
        >
            {children}
        </button>
    );
}
