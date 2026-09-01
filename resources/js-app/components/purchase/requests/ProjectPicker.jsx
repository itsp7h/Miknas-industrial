import { useEffect, useMemo, useRef, useState } from 'react';

/**
 * Blade's searchable project dropdown: a button showing the chosen project, a
 * panel with a search box, and one row per project with its company above the
 * name. The edit modal used a plain <select> instead, which meant no search
 * over the same list — both use this now.
 *
 * A saved project that is no longer in the active list is still shown as the
 * current value, so opening the form on an old request cannot silently blank it.
 */
export default function ProjectPicker({ projects, value, onChange }) {
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
        !term || `${project.company_name ?? ''} ${project.name}`.toLowerCase().includes(term)
    )), [projects, term]);

    const chosen = projects.find((project) => project.name === value);
    const label = chosen ? chosen.label : value;

    return (
        <div style={{ position: 'relative' }} ref={wrapper}>
            <label className="form-label" htmlFor="mpr-project">
                Project / Site Name <span className="text-red-500">*</span>
            </label>
            <button
                type="button" id="mpr-project" onClick={() => { setOpen((o) => !o); setQuery(''); }}
                aria-haspopup="listbox" aria-expanded={open}
                style={{
                    width: '100%', textAlign: 'left', background: '#fff', border: '1px solid #d1d5db',
                    borderRadius: '0.375rem', padding: '0.4rem 2rem 0.4rem 0.625rem', fontSize: '0.875rem',
                    color: '#111827', cursor: 'pointer', position: 'relative', lineHeight: 1.5, minHeight: '2.25rem',
                }}
            >
                <span style={{ color: value ? '#111827' : '#9ca3af' }}>{label || '— Select Project —'}</span>
                <span style={{
                    position: 'absolute', right: '0.5rem', top: '50%', transform: 'translateY(-50%)',
                    pointerEvents: 'none', color: '#6b7280',
                }}>▼</span>
            </button>

            {open && (
                <div style={{
                    position: 'absolute', zIndex: 9999, top: 'calc(100% + 2px)', left: 0, right: 0,
                    background: '#fff', border: '1px solid #d1d5db', borderRadius: '0.5rem',
                    boxShadow: '0 10px 25px -5px rgba(0,0,0,0.15)', overflow: 'hidden',
                }}>
                    <div style={{ padding: '0.5rem' }}>
                        <input
                            ref={searchBox} type="text" value={query} aria-label="Search projects"
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Search project or company…"
                            style={{
                                width: '100%', border: '1px solid #e2e8f0', borderRadius: '0.375rem',
                                padding: '0.375rem 0.625rem', fontSize: '0.8rem', outline: 'none', boxSizing: 'border-box',
                            }}
                        />
                    </div>
                    <ul role="listbox" style={{ maxHeight: '13rem', overflowY: 'auto', margin: 0, padding: '0 0 0.25rem 0', listStyle: 'none' }}>
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
                                <span style={{ fontSize: '0.68rem', color: '#6b7280', display: 'block', lineHeight: 1.2 }}>
                                    {project.company_name ?? ''}
                                </span>
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
