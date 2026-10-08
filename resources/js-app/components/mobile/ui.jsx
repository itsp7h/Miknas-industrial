import { createContext, useContext, useLayoutEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import Icon from './icons';
import { C, MONO, TONES, ZONES, avatarTone, initials } from './theme';

// The mobile design's building blocks. Every redesigned mobile page is a
// <MobilePage> holding these, so the screens share one header, one card, one
// list row and one set of chips instead of each restating the styles.
//
// Inline styles throughout: these values exist nowhere in the desktop pages,
// so Tailwind's JIT would have nothing to compile them from (CLAUDE.md #1).

// ── Page frame ──────────────────────────────────────────────────────────────

/**
 * Lets MobileShell know a page draws its own hero, so the shell's fallback
 * header (for any page not yet given one) steps aside. Layout effect, not a
 * plain effect, so the fallback never paints for a frame first.
 */
export const HeroSlotContext = createContext(() => () => {});

/**
 * Lets a page take the bottom of the screen for its own action bar, as the
 * design's detail and form screens do. The tab bar steps aside while the page
 * is mounted and comes back when it leaves.
 */
export const TabBarSlotContext = createContext(() => () => {});

function useClaimHero() {
    const claim = useContext(HeroSlotContext);
    useLayoutEffect(() => claim(), [claim]);
}

const DECOR = [
    { right: -70, top: -50, width: 230, height: 230, background: 'rgba(255,255,255,0.08)' },
    { left: -50, bottom: -60, width: 160, height: 160, background: 'rgba(255,255,255,0.05)' },
];

/**
 * The coloured header every screen opens with.
 *
 * `root` is a bottom-tab page: the zone's gradient and a large title with an
 * optional eyebrow line above it and `action` beside it. `sub` is a page opened
 * from another: a back bar in the solid zone colour that stays put on scroll,
 * then the title and subtitle beneath it.
 *
 * `children` sit inside the hero under the title — the Home KPI cards, the
 * More profile card, a pipeline's progress bar.
 */
export function Hero({
    zone = 'home', variant = 'sub', title, eyebrow, subtitle, back, actions, action, titleStyle, children,
}) {
    useClaimHero();
    const z = ZONES[zone] ?? ZONES.home;
    const root = variant === 'root';

    return (
        <>
            {!root && <BackBar zone={zone} back={back} actions={actions} />}
            <div
                data-testid="mobile-hero"
                style={{
                    margin: '0 -20px',
                    // A sub page's hero tucks up under its back bar: the page's
                    // block gap would otherwise open a stripe between the two.
                    ...(root ? {} : { marginTop: -30 }),
                    padding: root
                        ? 'calc(env(safe-area-inset-top, 0px) + 36px) 20px 24px'
                        : '36px 20px 26px',
                    background: root ? z.gradient : z.solid,
                    borderRadius: '0 0 28px 28px',
                    position: 'relative', zIndex: 0, isolation: 'isolate', overflow: 'hidden',
                    display: 'flex', flexDirection: 'column', gap: children ? 18 : 6,
                    color: '#FFFFFF',
                }}
            >
                {DECOR.map((d, i) => (
                    <div key={i} aria-hidden="true" style={{ position: 'absolute', borderRadius: '50%', zIndex: -1, ...d }} />
                ))}
                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                    <div style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', gap: 4 }}>
                        {eyebrow && (
                            <span style={{ fontSize: 14, color: 'rgba(255,255,255,0.85)' }}>{eyebrow}</span>
                        )}
                        <h1 style={{
                            margin: 0, fontSize: root ? 34 : 30, fontWeight: 700, letterSpacing: '-0.02em',
                            lineHeight: 1.15, overflowWrap: 'anywhere', ...titleStyle,
                        }}>
                            {title}
                        </h1>
                        {subtitle && (
                            <span style={{ fontSize: 15, color: 'rgba(255,255,255,0.85)', lineHeight: 1.4 }}>{subtitle}</span>
                        )}
                    </div>
                    {action}
                </div>
                {children}
            </div>
        </>
    );
}

// Back goes back when there is somewhere in the app to go back to, and Home
// when the page was opened directly (a link, a bookmark, a reload).
function goBack(navigate) {
    if ((window.history.state?.idx ?? 0) > 0) navigate(-1);
    else navigate('/app');
}

function BackBar({ zone, back, actions }) {
    const navigate = useNavigate();
    const z = ZONES[zone] ?? ZONES.home;
    const label = back?.label ?? 'Back';
    const style = {
        height: 44, display: 'flex', alignItems: 'center', gap: 2, padding: '0 8px',
        textDecoration: 'none', fontSize: 17, color: '#FFFFFF', background: 'none', border: 0,
        font: 'inherit', cursor: 'pointer',
    };

    return (
        <div
            data-testid="mobile-back-bar"
            style={{
                position: 'sticky', top: 0, zIndex: 20, margin: '0 -20px',
                padding: 'calc(env(safe-area-inset-top, 0px) + 6px) 8px 0',
                minHeight: 50, boxSizing: 'border-box',
                display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                background: z.solid,
            }}
        >
            {back?.to ? (
                <Link to={back.to} style={style}>
                    <Icon name="chevronLeft" size={22} strokeWidth={2.2} />{label}
                </Link>
            ) : (
                <button type="button" onClick={() => goBack(navigate)} style={style}>
                    <Icon name="chevronLeft" size={22} strokeWidth={2.2} />{label}
                </button>
            )}
            <div style={{ display: 'flex' }}>{actions}</div>
        </div>
    );
}

/** An icon button for the back bar ("Add supplier", "Import"). */
export function BarButton({ icon, label, onClick, disabled = false, title, children }) {
    return (
        <button
            type="button"
            aria-label={label}
            title={title ?? label}
            onClick={onClick}
            disabled={disabled}
            style={{
                minWidth: 44, height: 44, border: 0, background: 'transparent', color: '#FFFFFF',
                display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 4,
                font: 'inherit', fontSize: 17, fontWeight: 600, padding: '0 8px',
                cursor: disabled ? 'not-allowed' : 'pointer', opacity: disabled ? 0.5 : 1,
            }}
        >
            {icon && <Icon name={icon} size={icon === 'plus' ? 24 : 22} strokeWidth={icon === 'plus' ? 2.2 : 1.9} />}
            {children}
        </button>
    );
}

/** The white pill beside a root hero's title ("+ LPO") or a round "+". */
export function HeroButton({ icon = 'plus', label, onClick, disabled = false, title, zone = 'home', children }) {
    const z = ZONES[zone] ?? ZONES.home;

    return (
        <button
            type="button"
            aria-label={label}
            title={title ?? label}
            onClick={onClick}
            disabled={disabled}
            style={{
                flexShrink: 0, height: 44, minWidth: 44, borderRadius: 22, border: 0,
                padding: children ? '0 18px 0 14px' : 0, background: '#FFFFFF', color: z.solid,
                display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6,
                font: 'inherit', fontSize: 16, fontWeight: 600,
                cursor: disabled ? 'not-allowed' : 'pointer', opacity: disabled ? 0.55 : 1,
            }}
        >
            <Icon name={icon} size={22} strokeWidth={2.2} />
            {children}
        </button>
    );
}

/**
 * The page body. `MobileShell` gives no padding of its own any more, so this
 * owns the 20px gutters and the gap between blocks.
 */
export function MobilePage({ children, gap = 16 }) {
    return (
        <div style={{
            padding: '0 20px 24px', display: 'flex', flexDirection: 'column', gap,
            minHeight: '100%', boxSizing: 'border-box',
        }}>
            {children}
        </div>
    );
}

/**
 * A page's own bar along the bottom (Add suppliers · View quotes). Replaces
 * the tab bar while it shows anything. Whether it does is read from what
 * actually rendered, not from the children passed: a child component may
 * decide it has nothing for this person to do, and an empty bar must give the
 * tabs back rather than sit there blank.
 */
export function BottomBar({ children }) {
    const ref = useRef(null);
    const [filled, setFilled] = useState(false);
    const claim = useContext(TabBarSlotContext);

    useLayoutEffect(() => {
        const next = !!ref.current && ref.current.textContent.trim() !== '';
        if (next !== filled) setFilled(next);
    });
    useLayoutEffect(() => (filled ? claim() : undefined), [claim, filled]);

    return (
        <div
            ref={ref}
            className="m-bottom-bar"
            data-testid="mobile-bottom-bar"
            style={{
                display: filled ? 'flex' : 'none',
                position: 'fixed', left: 0, right: 0, bottom: 0, zIndex: 46,
                padding: '12px 20px calc(12px + env(safe-area-inset-bottom, 0px))',
                background: 'rgba(255,255,255,0.96)', backdropFilter: 'blur(18px)', WebkitBackdropFilter: 'blur(18px)',
                borderTop: `1px solid ${C.line}`, gap: 10, flexWrap: 'wrap',
            }}
        >
            {children}
        </div>
    );
}

// ── Content blocks ──────────────────────────────────────────────────────────

export function SectionLabel({ zone = 'home', color, children, action }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 4px', marginBottom: -6 }}>
            <h2 style={{
                margin: 0, fontSize: 13, fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.06em',
                color: color ?? ZONES[zone]?.label ?? C.accent,
            }}>
                {children}
            </h2>
            {action}
        </div>
    );
}

export function Card({ children, style, padded = false, ...rest }) {
    return (
        <div
            style={{
                background: C.card, borderRadius: 18, display: 'flex', flexDirection: 'column',
                padding: padded ? 16 : 0, overflow: 'hidden', ...style,
            }}
            {...rest}
        >
            {children}
        </div>
    );
}

/** A square tinted icon tile, the thing at the left of most rows. */
export function IconTile({ icon, tone = 'blue', size = 40, radius = 12, iconSize = 20 }) {
    const t = TONES[tone] ?? TONES.blue;

    return (
        <span style={{
            width: size, height: size, flexShrink: 0, borderRadius: radius, background: t.bg, color: t.fg,
            display: 'flex', alignItems: 'center', justifyContent: 'center',
        }}>
            <Icon name={icon} size={iconSize} />
        </span>
    );
}

export function Avatar({ name, size = 40, radius = 12, tone }) {
    const t = tone ? TONES[tone] : avatarTone(name);

    return (
        <span style={{
            width: size, height: size, flexShrink: 0, borderRadius: radius, background: t.bg, color: t.fg,
            fontSize: Math.round(size * 0.35), fontWeight: 600,
            display: 'flex', alignItems: 'center', justifyContent: 'center',
        }}>
            {initials(name)}
        </span>
    );
}

/**
 * One row of a list card. Becomes a router link with `to`, a button with
 * `onClick`, and a plain row otherwise. `disabled` keeps it on screen with the
 * reason as its tooltip rather than hiding it (CLAUDE.md #14).
 */
export function ListRow({
    to, onClick, leading, title, subtitle, meta, trailing, chevron = !!(to || onClick),
    last = false, disabled = false, disabledReason, children, style, testId,
}) {
    const body = (
        <>
            {leading}
            <span style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', gap: 3 }}>
                {title !== undefined && (
                    <span style={{ fontSize: 15, fontWeight: 600, color: C.text, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {title}
                    </span>
                )}
                {subtitle && (
                    <span style={{ fontSize: 13, color: C.muted, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {subtitle}
                    </span>
                )}
                {meta}
                {children}
            </span>
            {trailing}
            {chevron && <Icon name="chevronRight" size={18} strokeWidth={2} style={{ color: C.fainter }} />}
        </>
    );
    const rowStyle = {
        display: 'flex', alignItems: 'center', gap: 12, padding: '12px 16px', minHeight: 52,
        boxSizing: 'border-box', textDecoration: 'none', color: C.text, width: '100%',
        background: 'transparent', border: 0, textAlign: 'left', font: 'inherit',
        borderBottom: last ? 0 : `1px solid ${C.hairline}`,
        ...(disabled ? { opacity: 0.5, cursor: 'not-allowed' } : { cursor: to || onClick ? 'pointer' : 'default' }),
        ...style,
    };

    if (disabled) {
        return <div aria-disabled="true" title={disabledReason} style={rowStyle} data-testid={testId}>{body}</div>;
    }
    if (to) return <Link to={to} style={rowStyle} data-testid={testId}>{body}</Link>;
    if (onClick) return <button type="button" onClick={onClick} style={rowStyle} data-testid={testId}>{body}</button>;

    return <div style={rowStyle} data-testid={testId}>{body}</div>;
}

/** Renders `items` as ListRows inside one card, marking the last row. */
export function ListCard({ items, render, empty }) {
    if (!items.length) return empty ?? null;

    return (
        <Card>
            {items.map((item, i) => render(item, i === items.length - 1, i))}
        </Card>
    );
}

export function Pill({ tone = 'slate', children, style }) {
    const t = TONES[tone] ?? TONES.slate;

    return (
        <span style={{
            flexShrink: 0, fontSize: 12, fontWeight: 600, padding: '3px 10px', borderRadius: 999,
            background: t.bg, color: t.fg, whiteSpace: 'nowrap', ...style,
        }}>
            {children}
        </span>
    );
}

/** A document number (MI-LPO-26-0007) in the design's mono face. */
export function DocNo({ children, color = C.accent, size = 13 }) {
    return <span style={{ fontFamily: MONO, fontWeight: 500, fontSize: size, color, letterSpacing: '0.01em' }}>{children}</span>;
}

/** The white strip of headline figures (Suppliers: Total · Active · …). */
export function StatStrip({ items }) {
    return (
        <div style={{
            display: 'grid', gridTemplateColumns: `repeat(${items.length}, minmax(0, 1fr))`,
            background: C.card, borderRadius: 18, padding: '12px 4px',
        }}>
            {items.map((item, i) => (
                <div
                    key={item.label}
                    style={{
                        display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 2,
                        borderRight: i < items.length - 1 ? `1px solid ${C.hairline}` : 0,
                    }}
                >
                    <span style={{ fontSize: 19, fontWeight: 700, color: item.color ?? C.text }}>{item.value}</span>
                    <span style={{ fontSize: 12, color: C.muted }}>{item.label}</span>
                </div>
            ))}
        </div>
    );
}

/** The grey track with a white sliding tab (Active · Completed). */
export function Segmented({ options, value, onChange, ariaLabel }) {
    return (
        <div role="tablist" aria-label={ariaLabel} style={{
            display: 'grid', gridTemplateColumns: `repeat(${options.length}, minmax(0, 1fr))`,
            background: '#E2E8F0', borderRadius: 12, padding: 3, gap: 3,
        }}>
            {options.map((o) => {
                const active = o.key === value;

                return (
                    <button
                        key={o.key}
                        type="button"
                        role="tab"
                        aria-selected={active}
                        onClick={() => onChange(o.key)}
                        style={{
                            height: 36, border: 0, borderRadius: 9, font: 'inherit', fontSize: 14,
                            fontWeight: active ? 600 : 500, cursor: 'pointer',
                            background: active ? '#FFFFFF' : 'transparent', color: active ? C.text : C.muted,
                            boxShadow: active ? '0 1px 3px rgba(15,23,42,0.12)' : 'none',
                            whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', padding: '0 6px',
                        }}
                    >
                        {o.label}
                    </button>
                );
            })}
        </div>
    );
}

export function SearchField({ value, onChange, placeholder = 'Search', trailing, onFilter, filterActive = false }) {
    const field = (
        <label style={{
            flexShrink: 0, boxSizing: 'border-box', height: 44, borderRadius: 12, background: C.card,
            border: `1px solid ${C.line}`, display: 'flex', alignItems: 'center', gap: 10, padding: '0 14px',
            color: C.faint,
        }}>
            <Icon name="search" size={20} />
            <input
                type="search"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                aria-label={placeholder}
                style={{
                    WebkitAppearance: 'none', appearance: 'none', flex: 1, minWidth: 0, height: '100%',
                    margin: 0, padding: 0, border: 0, outline: 'none', background: 'transparent',
                    font: 'inherit', fontSize: 16, color: C.text, boxShadow: 'none',
                }}
            />
            {trailing}
        </label>
    );

    if (!onFilter) return field;

    // The square filter button beside the field (Inventory's sort/filter).
    return (
        <div style={{ display: 'flex', gap: 10 }}>
            <div style={{ flex: 1, minWidth: 0 }}>{field}</div>
            <button
                type="button" aria-label="Sort and filter" onClick={onFilter}
                style={{
                    width: 44, height: 44, flexShrink: 0, borderRadius: 12, cursor: 'pointer',
                    border: `1px solid ${filterActive ? C.accent : C.line}`, background: filterActive ? '#EFF6FF' : C.card,
                    color: filterActive ? C.accent : C.text, display: 'flex', alignItems: 'center', justifyContent: 'center',
                }}
            >
                <Icon name="sort" size={20} />
            </button>
        </div>
    );
}

/** A horizontally scrolling row of filter chips. */
export function ChipRow({ children }) {
    return (
        <div style={{ display: 'flex', gap: 8, overflowX: 'auto', margin: '0 -20px', padding: '0 20px', scrollbarWidth: 'none' }}>
            {children}
        </div>
    );
}

export function Chip({ active = false, onClick, zone = 'purchase', children, dropdown = false }) {
    const z = ZONES[zone] ?? ZONES.purchase;

    return (
        <button
            type="button"
            aria-pressed={active}
            onClick={onClick}
            style={{
                flexShrink: 0, height: 38, padding: '0 14px', borderRadius: 999, font: 'inherit',
                fontSize: 14, fontWeight: 500, cursor: 'pointer', whiteSpace: 'nowrap',
                border: `1px solid ${active ? z.solid : C.line}`,
                background: active ? z.solid : C.card, color: active ? '#FFFFFF' : C.text,
                display: 'flex', alignItems: 'center', gap: 6,
            }}
        >
            {children}
            {dropdown && <Icon name="chevronDown" size={14} strokeWidth={2.4} />}
        </button>
    );
}

/** "14 purchase orders" — the quiet count above a list. */
export function CountLine({ children }) {
    return <span style={{ fontSize: 14, color: C.muted, padding: '0 4px' }}>{children}</span>;
}

export function EmptyState({ icon = 'search', title, children }) {
    return (
        <Card padded style={{ alignItems: 'center', textAlign: 'center', gap: 8, padding: '32px 20px' }}>
            <span style={{ color: C.fainter }}><Icon name={icon} size={28} /></span>
            <span style={{ fontSize: 15, fontWeight: 600, color: C.text }}>{title}</span>
            {children && <span style={{ fontSize: 13, color: C.muted }}>{children}</span>}
        </Card>
    );
}

export function Loading({ label = 'Loading…' }) {
    return <div style={{ padding: '32px 0', textAlign: 'center', fontSize: 14, color: C.faint }}>{label}</div>;
}

/** A label-left, value-right row in a details card. */
export function FieldRow({ label, children, last = false, onClick, chevron = false }) {
    const style = {
        display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12,
        minHeight: 50, padding: '0 16px', borderBottom: last ? 0 : `1px solid ${C.hairline}`,
        fontSize: 15, background: 'transparent', border: 0, width: '100%', font: 'inherit', textAlign: 'left',
        borderBottomStyle: 'solid', boxSizing: 'border-box', color: C.text,
        cursor: onClick ? 'pointer' : 'default',
    };
    const body = (
        <>
            <span style={{ color: C.text, flexShrink: 0 }}>{label}</span>
            <span style={{ color: C.muted, minWidth: 0, textAlign: 'right', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', display: 'flex', alignItems: 'center', gap: 6 }}>
                {children}
                {chevron && <Icon name="chevronRight" size={16} strokeWidth={2} style={{ color: C.fainter }} />}
            </span>
        </>
    );

    return onClick
        ? <button type="button" onClick={onClick} style={{ ...style, borderBottom: last ? 0 : `1px solid ${C.hairline}` }}>{body}</button>
        : <div style={{ ...style, borderBottom: last ? 0 : `1px solid ${C.hairline}` }}>{body}</div>;
}

/** The full-width primary button at the foot of a form. */
export function PrimaryButton({ children, onClick, type = 'button', disabled = false, color = C.accent, title, style }) {
    return (
        <button
            type={type}
            onClick={onClick}
            disabled={disabled}
            title={title}
            style={{
                height: 52, borderRadius: 16, border: 0, background: color, color: '#FFFFFF',
                font: 'inherit', fontSize: 16, fontWeight: 600, width: '100%',
                cursor: disabled ? 'not-allowed' : 'pointer', opacity: disabled ? 0.55 : 1, ...style,
            }}
        >
            {children}
        </button>
    );
}

/**
 * The sheet a "⋯" button opens: a short list of further actions rising from
 * the bottom. Each option is a router link (`to`), a real navigation
 * (`href` — a PDF, a print view) or a button (`onClick`).
 */
export function ActionSheet({ open, onClose, title, options }) {
    if (!open) return null;

    const rowStyle = (danger, last) => ({
        display: 'flex', alignItems: 'center', minHeight: 54, padding: '0 18px', width: '100%',
        fontSize: 16, color: danger ? C.danger : C.text, textDecoration: 'none', background: 'none',
        border: 0, borderBottom: last ? 0 : `1px solid ${C.hairline}`, font: 'inherit', textAlign: 'left',
        cursor: 'pointer', boxSizing: 'border-box',
    });

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label={title ?? 'Options'}
            onClick={onClose}
            style={{ position: 'fixed', inset: 0, zIndex: 60, background: 'rgba(15,23,42,0.4)', display: 'flex', alignItems: 'flex-end' }}
        >
            <div
                onClick={(e) => e.stopPropagation()}
                style={{
                    width: '100%', padding: '0 12px calc(12px + env(safe-area-inset-bottom, 0px))',
                    display: 'flex', flexDirection: 'column', gap: 8, boxSizing: 'border-box',
                }}
            >
                <div style={{ background: C.card, borderRadius: 18, overflow: 'hidden' }}>
                    {title && (
                        <div style={{ padding: '12px 18px', fontSize: 13, color: C.faint, textAlign: 'center', borderBottom: `1px solid ${C.hairline}` }}>
                            {title}
                        </div>
                    )}
                    {options.map((o, i) => {
                        const last = i === options.length - 1;
                        if (o.to) return <Link key={o.label} to={o.to} style={rowStyle(o.danger, last)} onClick={onClose}>{o.label}</Link>;
                        if (o.href) return <a key={o.label} href={o.href} target={o.newTab ? '_blank' : undefined} rel="noreferrer" style={rowStyle(o.danger, last)} onClick={onClose}>{o.label}</a>;

                        return (
                            <button
                                key={o.label}
                                type="button"
                                disabled={o.disabled}
                                title={o.disabledReason}
                                style={{ ...rowStyle(o.danger, last), ...(o.disabled ? { opacity: 0.5, cursor: 'not-allowed' } : {}) }}
                                onClick={() => { onClose(); o.onClick(); }}
                            >
                                {o.label}
                            </button>
                        );
                    })}
                </div>
                <button
                    type="button"
                    onClick={onClose}
                    style={{
                        minHeight: 54, borderRadius: 18, border: 0, background: C.card, color: C.accent,
                        font: 'inherit', fontSize: 16, fontWeight: 600, cursor: 'pointer',
                    }}
                >
                    Cancel
                </button>
            </div>
        </div>
    );
}
