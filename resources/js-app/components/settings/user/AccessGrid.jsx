const GROUP_TONE = {
    Purchase: { band: '#fffbeb', border: '#fef3c7', text: '#92400e' },
    Inventory: { band: '#ecfdf5', border: '#d1fae5', text: '#065f46' },
    System: { band: '#f1f5f9', border: '#e2e8f0', text: '#334155' },
};

const CELL = { textAlign: 'center', padding: '6px 4px' };

/**
 * Every tab against the actions it offers, as a grid of checkboxes.
 *
 * A profile is a starting point. This is what makes it one: an Admin can hand
 * any single square to anybody — view the pipeline but not edit it, edit raw
 * materials but never delete one — whatever profile that person holds.
 *
 * A tab only shows the columns it actually has. A report has nothing to create,
 * and a stock movement is a ledger line that is posted, never rewritten.
 */
export default function AccessGrid({ grid, value, onChange }) {
    const columns = ['view', 'create', 'edit', 'delete'];

    const groups = grid.reduce((acc, tab) => {
        (acc[tab.group] ??= []).push(tab);

        return acc;
    }, {});

    function toggle(name, on) {
        onChange(on ? [...value, name] : value.filter((item) => item !== name));
    }

    function toggleRow(tab, on) {
        const names = tab.actions.map((action) => action.name);

        onChange(on
            ? [...new Set([...value, ...names])]
            : value.filter((item) => !names.includes(item)));
    }

    return (
        <div>
            {Object.entries(groups).map(([group, tabs]) => {
                const tone = GROUP_TONE[group] ?? GROUP_TONE.System;

                return (
                    <div key={group} style={{ marginBottom: 14 }}>
                        <div style={{
                            padding: '6px 10px', background: tone.band,
                            border: `1px solid ${tone.border}`, borderRadius: 8,
                            fontSize: 11, fontWeight: 700, color: tone.text,
                            textTransform: 'uppercase', letterSpacing: '.05em', marginBottom: 6,
                        }}>
                            {group}
                        </div>

                        <table style={{ width: '100%', fontSize: 12.5, borderCollapse: 'collapse' }}>
                            <thead>
                                <tr style={{ color: '#94a3b8', fontSize: 10.5, textTransform: 'uppercase', letterSpacing: '.04em' }}>
                                    <th style={{ textAlign: 'left', padding: '4px 6px', fontWeight: 600 }}>Tab</th>
                                    {columns.map((column) => (
                                        <th key={column} style={{ ...CELL, width: 56, fontWeight: 600 }}>{column}</th>
                                    ))}
                                    <th style={{ ...CELL, width: 40, fontWeight: 600 }}>All</th>
                                </tr>
                            </thead>
                            <tbody>
                                {tabs.map((tab) => {
                                    const byAction = Object.fromEntries(tab.actions.map((a) => [a.action, a]));
                                    const allOn = tab.actions.every((a) => value.includes(a.name));

                                    return (
                                        <tr key={tab.tab} style={{ borderTop: '1px solid #f1f5f9' }}>
                                            <td style={{ padding: '5px 6px', color: '#0f172a' }}>{tab.label}</td>
                                            {columns.map((column) => {
                                                const action = byAction[column];

                                                return (
                                                    <td key={column} style={CELL}>
                                                        {action ? (
                                                            <input
                                                                type="checkbox"
                                                                aria-label={`${tab.label} ${action.label}`}
                                                                checked={value.includes(action.name)}
                                                                onChange={(e) => toggle(action.name, e.target.checked)}
                                                            />
                                                        ) : (
                                                            // Not a thing this tab can do, rather than a
                                                            // square someone forgot to tick.
                                                            <span style={{ color: '#e2e8f0' }}>—</span>
                                                        )}
                                                    </td>
                                                );
                                            })}
                                            <td style={CELL}>
                                                <input
                                                    type="checkbox"
                                                    aria-label={`${tab.label} all`}
                                                    checked={allOn}
                                                    onChange={(e) => toggleRow(tab, e.target.checked)}
                                                />
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>

                        {/* Things CRUD cannot express — approving, awarding — hang
                            off the tab they belong to rather than floating loose. */}
                        {tabs.filter((tab) => tab.extra.length > 0).map((tab) => (
                            <div key={`${tab.tab}-extra`} style={{ padding: '8px 6px 0' }}>
                                <div style={{ fontSize: 11, color: '#94a3b8', marginBottom: 5 }}>
                                    {tab.label} — beyond create, edit and delete
                                </div>
                                <div style={{ display: 'flex', flexDirection: 'column', gap: 5 }}>
                                    {tab.extra.map((action) => (
                                        <label key={action.name} style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12.5, color: '#374151' }}>
                                            <input
                                                type="checkbox"
                                                checked={value.includes(action.name)}
                                                onChange={(e) => toggle(action.name, e.target.checked)}
                                            />
                                            {action.label}
                                        </label>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                );
            })}
        </div>
    );
}
