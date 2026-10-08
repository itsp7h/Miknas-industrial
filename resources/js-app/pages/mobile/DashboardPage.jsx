import { Link } from 'react-router-dom';
import NotificationBell from '../../components/NotificationBell';
import Icon from '../../components/mobile/icons';
import { Card, Hero, IconTile, ListRow, MobilePage, SectionLabel } from '../../components/mobile/ui';
import { C, TONES, ZONES } from '../../components/mobile/theme';
import { stageMeta } from '../../components/mobile/stages';
import useOverview from '../../components/mobile/useOverview';
import useDashboardSummary from '../../components/dashboard/useDashboardSummary';
import { formatTopBarDate } from '../../layouts/TopBar';
import { useAccess } from '../../layouts/AccessContext';
import { money } from '../../currency';

// Home, from SteelERP-Mobile-Designs-V2: a greeting with the bell and the
// account, two headline cards, the pipeline split by stage, four quick actions
// and the things waiting on this person.

const QUICK_ACTIONS = [
    {
        label: 'New request', icon: 'plus', to: '/app/purchase/pipeline?new=1', solid: true,
        permission: 'pipeline.create', denied: 'You do not have permission to create purchase requests',
    },
    {
        label: 'Receive goods', icon: 'download', tone: 'green', to: '/app/purchase/grns?new=1',
        permission: 'goods-receipts.create', denied: 'You do not have permission to receive goods',
    },
    {
        label: 'Adjust stock', icon: 'swap', tone: 'teal', to: '/app/inventory/movements?new=1',
        permission: 'stock-movements.create', denied: 'You do not have permission to adjust stock',
    },
    {
        label: 'New LPO', icon: 'clipboard', tone: 'indigo', to: '/app/purchase/orders?new=1',
        permission: 'purchase-orders.create', denied: 'You do not have permission to create purchase orders',
    },
];

function KpiCard({ to, icon, tone, label, value, caption }) {
    const t = TONES[tone];
    const body = (
        <>
            <span style={{
                width: 36, height: 36, borderRadius: 11, background: t.bg, color: t.fg,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
            }}>
                <Icon name={icon} size={20} />
            </span>
            <span style={{ fontSize: 13, color: C.muted }}>{label}</span>
            <span style={{ fontSize: 22, fontWeight: 700, letterSpacing: '-0.01em', overflowWrap: 'anywhere' }}>{value}</span>
            <span style={{ fontSize: 12, color: C.faint }}>{caption}</span>
        </>
    );
    const style = {
        background: C.card, borderRadius: 20, padding: 16, display: 'flex', flexDirection: 'column',
        gap: 10, textDecoration: 'none', color: C.text, minWidth: 0,
    };

    return to ? <Link to={to} style={style}>{body}</Link> : <div style={style}>{body}</div>;
}

function StageCard({ pipeline }) {
    const stages = pipeline.stages.filter((s) => s.count > 0);

    return (
        <Link to="/app/purchase/pipeline" style={{
            background: C.card, borderRadius: 20, padding: 16, display: 'flex', flexDirection: 'column',
            gap: 14, textDecoration: 'none', color: C.text,
        }}>
            <span style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <span style={{ fontSize: 16, fontWeight: 600 }}>Requests by stage</span>
                <Icon name="chevronRight" size={18} strokeWidth={2} style={{ color: C.fainter }} />
            </span>
            {stages.length === 0 ? (
                <span style={{ fontSize: 14, color: C.muted }}>No requests in progress.</span>
            ) : (
                <>
                    <span style={{ display: 'flex', gap: 3, height: 10, borderRadius: 5, overflow: 'hidden' }}>
                        {stages.map((s) => (
                            <span key={s.key} style={{ flex: s.count, background: stageMeta(s.key).color }} />
                        ))}
                    </span>
                    <span style={{
                        display: 'grid', gridTemplateColumns: 'repeat(3, minmax(0, 1fr))', rowGap: 8, columnGap: 8,
                        fontSize: 13, color: C.text2,
                    }}>
                        {stages.map((s) => (
                            <span key={s.key} style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                <span style={{ width: 8, height: 8, flexShrink: 0, borderRadius: 4, background: stageMeta(s.key).color }} />
                                {stageMeta(s.key).label} {s.count}
                            </span>
                        ))}
                    </span>
                </>
            )}
        </Link>
    );
}

function QuickAction({ action, can }) {
    const t = action.solid ? { bg: C.accent, fg: '#FFFFFF' } : TONES[action.tone];
    const allowed = can(action.permission);
    const body = (
        <>
            <span style={{
                width: 60, height: 60, borderRadius: 18, background: t.bg, color: t.fg,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
            }}>
                <Icon name={action.icon} size={24} strokeWidth={action.solid ? 2 : 1.8} />
            </span>
            {action.label}
        </>
    );
    const style = {
        display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 8, textDecoration: 'none',
        color: C.text, fontSize: 12, fontWeight: 500, textAlign: 'center',
    };

    // Shown disabled with the reason, not hidden (CLAUDE.md #14).
    if (!allowed) {
        return (
            <div aria-disabled="true" title={action.denied} style={{ ...style, opacity: 0.45, cursor: 'not-allowed' }}>
                {body}
            </div>
        );
    }

    return <Link to={action.to} style={style}>{body}</Link>;
}

function actionRow(action, last) {
    switch (action.kind) {
        case 'gm_signature':
            return (
                <ListRow
                    key={action.kind}
                    to={`/app/purchase/pipeline/${action.request_id}`}
                    leading={<IconTile icon="pen" tone="rose" />}
                    title={action.count > 1 ? `${action.count} requests need your signature` : 'GM signature needed'}
                    subtitle={[action.reference, action.detail].filter(Boolean).join(' · ')}
                    last={last}
                />
            );
        case 'draft_grns':
            return (
                <ListRow
                    key={action.kind}
                    to={action.count > 1 ? '/app/purchase/grns' : `/app/purchase/grns/${action.grn_id}`}
                    leading={<IconTile icon="fileAlert" tone="amber" />}
                    title={action.count > 1 ? `${action.count} draft GRNs to confirm` : 'A draft GRN to confirm'}
                    subtitle={action.missing.length
                        ? `${action.reference} is missing ${joinWords(action.missing)}`
                        : `${action.reference} is ready to confirm`}
                    last={last}
                />
            );
        case 'unpaid_invoices':
            return (
                <ListRow
                    key={action.kind}
                    to="/app/purchase/invoices"
                    leading={<IconTile icon="cash" tone="red" />}
                    title={`${action.count} unpaid supplier invoice${action.count === 1 ? '' : 's'}`}
                    subtitle={`${money(action.outstanding)} outstanding`}
                    last={last}
                />
            );
        default:
            return null;
    }
}

function joinWords(words) {
    if (words.length < 2) return words.join('');

    return `${words.slice(0, -1).join(', ')} & ${words[words.length - 1]}`;
}

function StockNote({ count }) {
    const ok = count === 0;

    const body = (
        <>
            <Icon name={ok ? 'checkCircle' : 'warning'} size={20} strokeWidth={2} />
            {ok
                ? 'All items are above minimum stock levels.'
                : `${count} stock line${count === 1 ? ' is' : 's are'} below minimum.`}
        </>
    );
    const style = {
        display: 'flex', alignItems: 'center', gap: 10, padding: '12px 16px', borderRadius: 16,
        background: ok ? '#DCFCE7' : '#FEF3C7', color: ok ? '#14532D' : '#78350F',
        fontSize: 14, fontWeight: 500, textDecoration: 'none',
    };

    return ok ? <div style={style}>{body}</div> : <Link to="/app/inventory/reports/low-stock" style={style}>{body}</Link>;
}

export default function DashboardPage({ currentUserId, userName }) {
    const summary = useDashboardSummary(currentUserId);
    const overview = useOverview();
    const { can } = useAccess();
    const pipeline = overview?.pipeline;
    const first = (userName || 'there').split(' ')[0];

    return (
        <MobilePage gap={22}>
            <Hero
                zone="home"
                variant="root"
                eyebrow={formatTopBarDate(new Date())}
                title={`Welcome back, ${first}`}
                titleStyle={{ fontSize: 26 }}
                action={(
                    <div style={{ display: 'flex', gap: 10, alignSelf: 'flex-start' }}>
                        <NotificationBell currentUserId={currentUserId} variant="hero" />
                        <Link
                            to="/app/more"
                            aria-label="Account"
                            style={{
                                width: 44, height: 44, borderRadius: 22, background: '#FFFFFF',
                                color: ZONES.home.solid, fontWeight: 600, fontSize: 16, textDecoration: 'none',
                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                            }}
                        >
                            {(userName || 'U').charAt(0).toUpperCase()}
                        </Link>
                    </div>
                )}
            >
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, minmax(0, 1fr))', gap: 12 }}>
                    <KpiCard
                        to={can('valuation.view') ? '/app/inventory/reports/valuation' : null}
                        icon="box"
                        tone="green"
                        label="Inventory value"
                        value={summary ? money(summary.inventory_value) : '—'}
                        caption="All warehouses"
                    />
                    <KpiCard
                        to={pipeline ? '/app/purchase/pipeline' : null}
                        icon="clock"
                        tone="amber"
                        label="Purchase pipeline"
                        value={pipeline ? `${pipeline.active} active` : (summary ? `${summary.purchase_pending} active` : '—')}
                        caption={pipeline ? `${pipeline.completed} completed` : 'Requests in progress'}
                    />
                </div>
            </Hero>

            {pipeline && <StageCard pipeline={pipeline} />}

            <SectionLabel zone="home">Quick actions</SectionLabel>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, minmax(0, 1fr))', gap: 8 }}>
                {QUICK_ACTIONS.map((action) => <QuickAction key={action.label} action={action} can={can} />)}
            </div>

            {overview && (overview.actions.length > 0 || overview.low_stock !== null) && (
                <>
                    <SectionLabel zone="home">Needs your action</SectionLabel>
                    {overview.actions.length > 0 && (
                        <Card style={{ borderRadius: 20 }}>
                            {overview.actions.map((a, i) => actionRow(a, i === overview.actions.length - 1))}
                        </Card>
                    )}
                    {overview.low_stock !== null && <StockNote count={overview.low_stock} />}
                </>
            )}
        </MobilePage>
    );
}
