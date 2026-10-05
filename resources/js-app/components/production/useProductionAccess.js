import { useAccess } from '../../layouts/AccessContext';

const DISABLED = { opacity: 0.5, cursor: 'not-allowed' };

/**
 * What the signed-in person may do across the Production tabs, plus `gate()`,
 * which turns one of those answers into a button's props.
 *
 * An action someone may not take stays on the page, disabled, with the reason
 * as its tooltip (CLAUDE.md #14). The API checks again regardless.
 */
export default function useProductionAccess() {
    const { can } = useAccess();

    return {
        orders: {
            create: can('production-orders.create'),
            edit: can('production-orders.edit'),
            delete: can('production-orders.delete'),
            run: can('production-orders.run'),
        },
        bom: {
            create: can('bom.create'),
            edit: can('bom.edit'),
            delete: can('bom.delete'),
        },
        issues: { create: can('material-issues.create') },
        outputs: { create: can('production-outputs.create') },
    };
}

/**
 * `{ disabled, title, style }` for a button, given whether it is allowed.
 * `style` is the button's own, which the disabled look is laid over.
 */
export function gate(allowed, action, style) {
    if (allowed) return style ? { style } : {};

    return {
        disabled: true,
        title: `You do not have permission to ${action}`,
        style: { ...style, ...DISABLED },
    };
}
