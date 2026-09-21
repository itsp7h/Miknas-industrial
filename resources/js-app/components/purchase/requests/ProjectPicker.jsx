import { useEffect, useMemo, useRef, useState } from 'react';

/**
 * The project within the chosen company, beside CompanyPicker.
 *
 * A request belongs to a company *and* to a project — Miknas Industrial, and
 * Forkoll within it — so the two are separate fields rather than one standing
 * in for the other.
 *
 * The list is the chosen company's projects. With no company chosen there is
 * nothing to offer, so the control says so rather than listing every project
 * in the system and letting someone file against another company's site.
 *
 * A saved project that is no longer in the active list is still shown as the
 * current value, so opening the form on an old request cannot silently blank it.
 */
export default function ProjectPicker({ projects, disabled = false, value, onChange }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const wrapper = useRef(null);
    const searchBox = useRef(null);

    useEffect(() => {
        if (!open) return undefined;
        const onDocClick = (event) => {
            if (wrapper.current && !wrapper.current.contains(event.target)) setOpen(false);
        };
        document.addEventListener('click', onDocClick);

        return () => document.removeEventListener('click', onDocClick);
    }, [open]);

    useEffect(() => {
        if (open && searchBox.current) searchBox.current.focus();
    }, [open]);

    const term = query.trim().toLowerCase();
    const matches = useMemo(() => projects.filter((project) => (
        !term || project.name.toLowerCase().includes(term)
    )), [projects, term]);

    const placeholder = disabled ? '— Choose a company first —' : '— Select Project —';

    return (
        <div style={{ position: 'relative' }} ref={wrapper}>
            <label className="form-label" htmlFor="mpr-project">Project</label>
            <button
                type="button" id="mpr-project" disabled={disabled}
                onClick={() => { setOpen((o) => !o); setQuery(''); }}
                aria-haspopup="listbox" aria-expanded={open}
                style={{
                    width: '100%', textAlign: 'left', background: disabled ? '#f8fafc' : '#fff',
                    border: '1px solid #d1d5db', borderRadius: '0.375rem',
                    padding: '0.4rem 2rem 0.4rem 0.625rem', fontSize: '0.875rem',
                    color: '#111827', cursor: disabled ? 'not-allowed' : 'pointer',
                    position: 'relative', lineHeight: 1.5, minHeight: '2.25rem',
                }}
            >
                <span style={{ color: value ? '#111827' : '#9ca3af' }}>{value || placeholder}</span>
                <span style={{
                    position: 'absolute', right: '0.5rem', top: '50%', transform: 'translateY(-50%)',
                    pointerEvents: 'none', color: '#6b7280',
                }}>▼</span>
            </button>

            {open && !disabled && (
                <div style={{
                    position: 'absolute', zIndex: 9999, top: 'calc(100% + 2px)', left: 0, right: 0,
                    background: '#fff', border: '1px solid #d1d5db', borderRadius: '0.5rem',
                    boxShadow: '0 10px 25px -5px rgba(0,0,0,0.15)', overflow: 'hidden',
                }}>
                    <div style={{ padding: '0.5rem' }}>
                        <input
                            ref={searchBox} type="text" value={query} aria-label="Search projects"
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Search project…"
                            style={{
                                width: '100%', border: '1px solid #e2e8f0', borderRadius: '0.375rem',
                                padding: '0.375rem 0.625rem', fontSize: '0.8rem', outline: 'none', boxSizing: 'border-box',
                            }}
                        />
                    </div>
                    <ul role="listbox" style={{ maxHeight: '13rem', overflowY: 'auto', margin: 0, padding: '0 0 0.25rem 0', listStyle: 'none' }}>
                        {/* Clearing it is a real choice: a request need not be
                            for a project, and there must be a way back out. */}
                        {value && (
                            <li
                                role="option" aria-selected={false} className="mpr-proj-opt"
                                onClick={() => { onChange(''); setOpen(false); }}
                                style={{
                                    padding: '0.45rem 0.875rem', cursor: 'pointer', fontSize: '0.8rem',
                                    color: '#9ca3af', lineHeight: 1.4, borderLeft: '3px solid transparent',
                                }}
                            >
                                — No project —
                            </li>
                        )}
                        {matches.map((project) => (
                            <li
                                key={project.id} role="option" aria-selected={project.name === value}
                                className="mpr-proj-opt"
                                onClick={() => { onChange(project.name); setOpen(false); }}
                                style={{
                                    padding: '0.45rem 0.875rem', cursor: 'pointer', fontSize: '0.8rem',
                                    color: '#111827', lineHeight: 1.4, borderLeft: '3px solid transparent',
                                }}
                            >
                                {project.name}
                            </li>
                        ))}
                        {matches.length === 0 && (
                            <li style={{ padding: '0.625rem 0.875rem', fontSize: '0.8rem', color: '#9ca3af' }}>
                                No projects found.
                            </li>
                        )}
                    </ul>
                </div>
            )}
        </div>
    );
}
