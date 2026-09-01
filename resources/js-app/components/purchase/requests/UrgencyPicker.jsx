import { useEffect, useRef, useState } from 'react';

/**
 * Blade's colour-coded urgency dropdown: five presets, each with its own tint
 * and caption, plus a "Specific Date" row that reveals a date input. Whatever
 * is chosen is stored as a plain string in `required_date_text` — a preset name
 * or a date — which is exactly what the column has always held.
 */
export const PRESETS = [
    { value: 'Urgent', color: '#dc2626', tint: '#fef2f2', caption: 'Needed immediately' },
    { value: '3 Days', color: '#ea580c', tint: '#fff7ed', caption: 'Within 3 days' },
    { value: '1 Week', color: '#d97706', tint: '#fffbeb', caption: 'Within this week' },
    { value: '2 Weeks', color: '#2563eb', tint: '#eff6ff', caption: 'Within 2 weeks' },
    { value: '1 Month', color: '#16a34a', tint: '#f0fdf4', caption: 'Within a month' },
];

const DATE_COLOR = '#7c3aed';
const DATE_TINT = '#f5f3ff';

function CalendarIcon({ size = 14, color = DATE_COLOR, width = 2 }) {
    return (
        <svg
            style={{ width: size, height: size, stroke: color, flexShrink: 0 }}
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
        >
            <path
                strokeLinecap="round" strokeLinejoin="round" strokeWidth={width}
                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
            />
        </svg>
    );
}

/** Blade formatted a picked date as "02 Sep 2026" for the pill. */
export function dateLabel(value) {
    const parsed = new Date(`${value}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) return value;

    return parsed.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function Pill({ value }) {
    const preset = PRESETS.find((p) => p.value === value);
    const color = preset ? preset.color : DATE_COLOR;
    const tint = preset ? preset.tint : DATE_TINT;

    return (
        <span style={{
            display: 'inline-flex', alignItems: 'center', gap: '0.4rem', background: tint,
            border: `1px solid ${color}20`, padding: '0.15rem 0.6rem 0.15rem 0.4rem', borderRadius: 999,
        }}>
            {preset
                ? <span style={{ width: '0.45rem', height: '0.45rem', borderRadius: '50%', background: color, flexShrink: 0 }} />
                : <CalendarIcon size="0.7rem" width={2.5} />}
            <span style={{ fontSize: '0.78rem', fontWeight: 600, color }}>
                {preset ? preset.value : dateLabel(value)}
            </span>
        </span>
    );
}

export default function UrgencyPicker({ value, onChange }) {
    const [open, setOpen] = useState(false);
    // The date row stays revealed while a specific date is the current value,
    // the way Blade restored it on repopulation.
    const [showDate, setShowDate] = useState(() => !!value && !PRESETS.some((p) => p.value === value));
    const wrapper = useRef(null);

    useEffect(() => {
        if (!open) return undefined;
        const onDocClick = (event) => {
            if (wrapper.current && !wrapper.current.contains(event.target)) setOpen(false);
        };
        document.addEventListener('click', onDocClick);

        return () => document.removeEventListener('click', onDocClick);
    }, [open]);

    const chosen = PRESETS.find((p) => p.value === value);
    const borderColor = value ? (chosen ? chosen.color : DATE_COLOR) : '#d1d5db';

    return (
        <div style={{ position: 'relative' }} ref={wrapper}>
            <label className="form-label" htmlFor="mpr-urgency">Required Date / Urgency</label>
            <button
                type="button" id="mpr-urgency" onClick={() => setOpen((o) => !o)}
                aria-haspopup="listbox" aria-expanded={open}
                style={{
                    width: '100%', textAlign: 'left', background: '#fff',
                    border: `1.5px ${value ? 'solid' : 'dashed'} ${borderColor}`,
                    borderRadius: '0.5rem', padding: '0.45rem 2rem 0.45rem 0.75rem', fontSize: '0.8rem',
                    cursor: 'pointer', position: 'relative', minHeight: '2.35rem', display: 'flex',
                    alignItems: 'center', transition: 'border-color 0.15s',
                }}
            >
                <span style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', flex: 1 }}>
                    {value
                        ? <Pill value={value} />
                        : <span style={{ color: '#9ca3af', fontSize: '0.8rem' }}>— Select urgency —</span>}
                </span>
                <span style={{
                    position: 'absolute', right: '0.6rem', top: '50%', transform: 'translateY(-50%)',
                    color: '#9ca3af', fontSize: '0.7rem',
                }}>▼</span>
            </button>

            {open && (
                <div
                    role="listbox"
                    style={{
                        position: 'absolute', zIndex: 10000, top: 'calc(100% + 4px)', left: 0, right: 0,
                        background: '#fff', border: '1px solid #e2e8f0', borderRadius: '0.75rem',
                        boxShadow: '0 12px 32px -4px rgba(0,0,0,0.18)', overflow: 'hidden',
                    }}
                >
                    <ul style={{ listStyle: 'none', margin: 0, padding: '0.375rem' }}>
                        {PRESETS.map((preset) => (
                            <li
                                key={preset.value} role="option" aria-selected={value === preset.value}
                                onClick={() => { onChange(preset.value); setShowDate(false); setOpen(false); }}
                                className="mpr-urgency-opt"
                                style={{
                                    display: 'flex', alignItems: 'center', gap: '0.625rem',
                                    padding: '0.55rem 0.75rem', borderRadius: '0.5rem', cursor: 'pointer',
                                    marginBottom: '0.125rem',
                                }}
                            >
                                <span style={{
                                    flexShrink: 0, width: '2rem', height: '2rem', borderRadius: '0.4rem',
                                    background: preset.tint, display: 'flex', alignItems: 'center', justifyContent: 'center',
                                }}>
                                    <span style={{ width: '0.55rem', height: '0.55rem', borderRadius: '50%', background: preset.color }} />
                                </span>
                                <div>
                                    <div style={{ fontSize: '0.8rem', fontWeight: 700, color: preset.color, lineHeight: 1.2 }}>
                                        {preset.value}
                                    </div>
                                    <div style={{ fontSize: '0.68rem', color: '#9ca3af' }}>{preset.caption}</div>
                                </div>
                            </li>
                        ))}
                        <li style={{ height: 1, background: '#f1f5f9', margin: '0.25rem 0' }} />
                        <li
                            onClick={() => setShowDate((s) => !s)}
                            className="mpr-urgency-opt"
                            style={{
                                display: 'flex', alignItems: 'center', gap: '0.625rem',
                                padding: '0.55rem 0.75rem', borderRadius: '0.5rem', cursor: 'pointer',
                            }}
                        >
                            <span style={{
                                flexShrink: 0, width: '2rem', height: '2rem', borderRadius: '0.4rem',
                                background: DATE_TINT, display: 'flex', alignItems: 'center', justifyContent: 'center',
                            }}>
                                <CalendarIcon size="0.875rem" />
                            </span>
                            <div style={{ flex: 1 }}>
                                <div style={{ fontSize: '0.8rem', fontWeight: 700, color: DATE_COLOR, lineHeight: 1.2 }}>
                                    Specific Date
                                </div>
                                <div style={{ fontSize: '0.68rem', color: '#9ca3af' }}>Pick an exact date</div>
                            </div>
                        </li>
                        {showDate && (
                            <li style={{ padding: '0 0.75rem 0.5rem' }}>
                                <input
                                    type="date" aria-label="Specific required date"
                                    value={chosen ? '' : (value ?? '')}
                                    onChange={(e) => { if (e.target.value) { onChange(e.target.value); setOpen(false); } }}
                                    style={{
                                        width: '100%', fontSize: '0.8rem', border: `1.5px solid ${DATE_COLOR}`,
                                        borderRadius: '0.4rem', padding: '0.35rem 0.5rem', outline: 'none',
                                        color: DATE_COLOR, boxSizing: 'border-box',
                                    }}
                                />
                            </li>
                        )}
                    </ul>
                </div>
            )}
        </div>
    );
}
