import { Link, useNavigate } from 'react-router-dom';
import { Hero, HeroButton, Segmented } from '../../mobile/ui';
import { C, MONO, TONES } from '../../mobile/theme';
import useOverview from '../../mobile/useOverview';
import { useAccess } from '../../../layouts/AccessContext';
import Icon from '../../mobile/icons';

// The Purchasing tab (SteelERP-Mobile-Designs-V2): orders, receipts and
// supplier invoices under one header, switched by a segmented control.
//
// Each list keeps its own URL (/app/purchase/orders, /grns, /invoices), so a
// link or a bookmark still lands on the right one; the control navigates
// between them rather than holding the three lists in one page's state.

const TABS = [
    { key: 'orders', label: 'Orders', to: '/app/purchase/orders', permission: 'purchase-orders.view', count: 'purchase_orders' },
    { key: 'grns', label: 'Receipts', to: '/app/purchase/grns', permission: 'goods-receipts.view', count: 'grns' },
    { key: 'invoices', label: 'Invoices', to: '/app/purchase/invoices', permission: 'supplier-invoices.view', count: 'supplier_invoices' },
];

/**
 * `action` is the "+ LPO" / "+ GRN" / "+ Invoice" button: { label, short,
 * onClick, allowed, denied }. Shown disabled with the reason when not allowed.
 */
export function PurchasingHeader({ tab, action }) {
    const navigate = useNavigate();
    const { can } = useAccess();
    const overview = useOverview();
    const tabs = TABS.filter((t) => can(t.permission));

    return (
        <>
            <Hero
                zone="purchase"
                variant="root"
                eyebrow="Orders, receipts & invoices"
                title="Purchasing"
                action={action && (
                    <HeroButton
                        zone="purchase"
                        label={action.label}
                        title={action.allowed ? action.label : action.denied}
                        disabled={!action.allowed}
                        onClick={action.onClick}
                    >
                        {action.short}
                    </HeroButton>
                )}
            />
            {tabs.length > 1 && (
                <Segmented
                    ariaLabel="Purchasing"
                    value={tab}
                    onChange={(key) => navigate(TABS.find((t) => t.key === key).to)}
                    options={tabs.map((t) => {
                        const n = overview?.counts?.[t.count];

                        return { key: t.key, label: n === undefined ? t.label : `${t.label} ${n}` };
                    })}
                />
            )}
        </>
    );
}

/**
 * One document in a Purchasing list: its number, who it is with, a detail
 * line, and the amount and status on the right. `footer` holds a GRN's
 * paperwork warning and its Confirm button.
 */
export function DocRow({ to, onClick, number, title, sub, amount, status, statusTone = 'slate', footer, last }) {
    const style = {
        display: 'flex', flexDirection: 'column', gap: 10, padding: '14px 16px', textDecoration: 'none',
        color: C.text, borderBottom: last ? 0 : `1px solid ${C.hairline}`, background: 'none', border: 0,
        borderBottomStyle: 'solid', width: '100%', textAlign: 'left', font: 'inherit', cursor: 'pointer',
        boxSizing: 'border-box',
    };
    const body = (
        <>
            <span style={{ display: 'flex', alignItems: 'flex-start', gap: 12 }}>
                <span style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', gap: 4 }}>
                    <span style={{ fontFamily: MONO, fontSize: 13, color: C.accent }}>{number}</span>
                    <span style={{ fontSize: 16, fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{title}</span>
                    <span style={{ fontSize: 13, color: C.muted }}>{sub}</span>
                </span>
                <span style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 6, flexShrink: 0 }}>
                    {amount && <span style={{ fontSize: 15, fontWeight: 600 }}>{amount}</span>}
                    <span style={{
                        fontSize: 12, fontWeight: 600, padding: '3px 9px', borderRadius: 999,
                        background: TONES[statusTone].bg, color: TONES[statusTone].fg,
                    }}>
                        {status}
                    </span>
                </span>
            </span>
            {footer}
        </>
    );
    const rowStyle = { ...style, borderBottom: last ? 0 : `1px solid ${C.hairline}` };

    if (to) return <Link to={to} style={rowStyle}>{body}</Link>;

    // Not a <button>: a GRN row carries its own Confirm button, and a button
    // cannot hold another.
    return (
        <div
            role="button"
            tabIndex={0}
            onClick={onClick}
            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onClick(); } }}
            style={rowStyle}
        >
            {body}
        </div>
    );
}

/** The red "Needs …" / green "All documents attached" strip under a draft GRN. */
export function Warning({ ok, children }) {
    return (
        <span style={{
            display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, fontWeight: 600, padding: '5px 10px',
            borderRadius: 8, color: ok ? '#166534' : '#991B1B', background: ok ? '#DCFCE7' : '#FEE2E2',
            minWidth: 0,
        }}>
            <Icon name={ok ? 'checkCircle' : 'warning'} size={14} strokeWidth={2.2} />
            {children}
        </span>
    );
}
