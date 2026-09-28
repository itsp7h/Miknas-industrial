import Modal from '../../ui/Modal';

/**
 * The add/edit form for one person on System → Requested By, as a dialog —
 * the same `ui/Modal` the other Settings pages add through. It is opened only
 * by an action the viewer is allowed to take (the page disables the rest,
 * CLAUDE.md #14), so nothing in here needs a disabled state of its own.
 *
 * Its values live in `useRequesters`, set once when it opens, so a live
 * update to the list underneath never overwrites what is being typed.
 */
export default function RequesterModal({ r, compact = false }) {
    return (
        <Modal
            open={r.formOpen}
            title={r.editing ? `Edit ${r.editing.name}` : 'Add a person'}
            onClose={r.closeForm}
        >
            <form
                onSubmit={(e) => { e.preventDefault(); r.save(); }}
                onKeyDown={(e) => { if (e.key === 'Escape') r.closeForm(); }}
            >
                <div style={{ marginBottom: 12 }}>
                    <label htmlFor="rq-name" className="form-label">
                        Name <span className="text-red-500">*</span>
                    </label>
                    <input
                        id="rq-name"
                        className="form-input"
                        style={{ width: '100%' }}
                        value={r.values.name}
                        onChange={(e) => r.setName(e.target.value)}
                        placeholder="e.g. Ali Hassan"
                        autoFocus
                    />
                    {r.errors.name && <p className="text-sm text-red-600 mt-1">{r.errors.name}</p>}
                </div>

                <fieldset style={{ border: 0, padding: 0, margin: '0 0 12px' }}>
                    <legend className="form-label">
                        Contact numbers <span style={{ color: '#9ca3af', fontWeight: 400 }}>(optional)</span>
                    </legend>
                    {r.values.phones.map((phone, index) => (
                        <div key={index} style={{ marginBottom: 8 }}>
                            <div style={{ display: 'flex', gap: 8 }}>
                                <input
                                    type="tel"
                                    inputMode="tel"
                                    aria-label={`Contact number ${index + 1}`}
                                    className="form-input"
                                    style={{ flex: 1, minWidth: 0 }}
                                    placeholder="+973 3312 3456"
                                    value={phone}
                                    onChange={(e) => r.setPhone(index, e.target.value)}
                                />
                                {(r.values.phones.length > 1 || phone !== '') && (
                                    <button
                                        type="button"
                                        aria-label={`Remove contact number ${index + 1}`}
                                        onClick={() => r.removePhone(index)}
                                        className="btn-secondary btn-sm"
                                    >
                                        ×
                                    </button>
                                )}
                            </div>
                            {r.errors.phoneRows?.[index] && (
                                <p className="text-sm text-red-600 mt-1">{r.errors.phoneRows[index]}</p>
                            )}
                        </div>
                    ))}
                    {r.errors.phones && <p className="text-sm text-red-600 mt-1">{r.errors.phones}</p>}
                    <button
                        type="button"
                        onClick={r.addPhone}
                        style={{ fontSize: 13, color: '#2563eb', background: 'none', border: 0, padding: 0, cursor: 'pointer' }}
                    >
                        + Add number
                    </button>
                </fieldset>

                <fieldset style={{ border: 0, padding: 0, margin: 0 }}>
                    <legend className="form-label">
                        Companies <span className="text-red-500">*</span>
                    </legend>
                    {r.companies.length === 0 && (
                        <p style={{ fontSize: 13, color: '#64748b' }}>No active companies — add one under System → Companies.</p>
                    )}
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: compact ? 8 : 10 }}>
                        {r.companies.map((company) => (
                            <label
                                key={company.id}
                                style={{
                                    display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 14, color: '#334155',
                                    border: '1px solid #e2e8f0', borderRadius: 8, padding: '6px 10px', cursor: 'pointer',
                                }}
                            >
                                <input
                                    type="checkbox"
                                    checked={r.values.company_ids.includes(company.id)}
                                    onChange={() => r.toggleCompany(company.id)}
                                />
                                {company.name}
                            </label>
                        ))}
                    </div>
                    {r.errors.company_ids && <p className="text-sm text-red-600 mt-1">{r.errors.company_ids}</p>}
                </fieldset>

                <div className="mt-6 flex items-center justify-end gap-3">
                    <button type="button" onClick={r.closeForm} className="btn-secondary">Cancel</button>
                    <button type="submit" className="btn-primary" disabled={r.saving}>
                        {r.saving ? 'Saving…' : (r.editing ? 'Save' : 'Add Person')}
                    </button>
                </div>
            </form>
        </Modal>
    );
}
