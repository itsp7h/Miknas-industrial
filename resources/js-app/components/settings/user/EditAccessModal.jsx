import { useEffect, useState } from 'react';
import Modal from '../../ui/Modal';
import AccessGrid from './AccessGrid';
import ProfilePicker from './ProfilePicker';

const SECTION_LABEL = {
    fontSize: 11, fontWeight: 700, color: '#64748b', textTransform: 'uppercase',
    letterSpacing: '.05em', marginBottom: 10,
};

/**
 * Pick the person's profile, then adjust any square of the grid.
 *
 * The profile is a starting point, not a cage: whatever someone holds, an Admin
 * can grant or remove any single tab-and-action from them here.
 */
export default function EditAccessModal({ user, profiles, grid, onClose, onSave }) {
    const [profile, setProfile] = useState('');
    const [selectedPermissions, setSelectedPermissions] = useState([]);
    const [advancedOpen, setAdvancedOpen] = useState(false);
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        // Someone assigned two roles under the old form keeps the first; the
        // save then writes back the one profile, which is the point.
        setProfile(user?.roles?.[0] ?? '');
        setSelectedPermissions(user?.permissions ?? []);
        setAdvancedOpen((user?.permissions ?? []).length > 0);
        setError('');
    }, [user]);

    /** Picking a profile lays its squares down; from there they are editable. */
    function chooseProfile(name) {
        setProfile(name);
        const chosen = profiles.find((option) => option.name === name);
        setSelectedPermissions(chosen ? [...chosen.permissions] : []);
    }

    async function save() {
        setSaving(true);
        setError('');
        try {
            await onSave(user, {
                roles: profile ? [profile] : [],
                permissions: selectedPermissions,
            });
            onClose();
        } catch (err) {
            // The server refuses an admin removing their own Admin role; that
            // message is the whole point of showing it here.
            setError(err?.message || 'Failed to update access.');
        } finally {
            setSaving(false);
        }
    }

    return (
        <Modal open={!!user} title={`Edit Access — ${user?.name ?? ''}`} onClose={onClose}>
            <div style={SECTION_LABEL}>Profile</div>
            <ProfilePicker profiles={profiles} value={profile} onChange={chooseProfile} />

            <div style={{ marginTop: 18, borderTop: '1px solid #e2e8f0', paddingTop: 14 }}>
                <button
                    type="button"
                    onClick={() => setAdvancedOpen((open) => !open)}
                    style={{
                        background: 'none', border: 'none', padding: 0, cursor: 'pointer',
                        fontSize: 12.5, color: '#2563eb', fontWeight: 600,
                    }}
                >
                    {advancedOpen ? '▾' : '▸'} Tab access
                    {selectedPermissions.length > 0 && ` (${selectedPermissions.length} granted)`}
                </button>

                {advancedOpen && (
                    <>
                        <p style={{ fontSize: 12, color: '#94a3b8', margin: '8px 0 12px' }}>
                            What this person can reach, and what they can do there. The profile
                            above fills this in; every square is yours to change.
                        </p>
                        <AccessGrid grid={grid} value={selectedPermissions} onChange={setSelectedPermissions} />
                    </>
                )}
            </div>

            {error && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 14 }}>{error}</p>}

            <div className="mt-6 flex items-center justify-end gap-3">
                <button type="button" onClick={onClose} className="btn-secondary">Cancel</button>
                <button type="button" onClick={save} className="btn-primary" disabled={saving}>
                    {saving ? 'Saving…' : 'Save'}
                </button>
            </div>
        </Modal>
    );
}
