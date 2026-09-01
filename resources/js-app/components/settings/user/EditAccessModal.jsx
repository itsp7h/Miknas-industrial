import { useEffect, useState } from 'react';
import Modal from '../../ui/Modal';
import PermissionToggle from './PermissionToggle';

const SECTION_LABEL = {
    fontSize: 11, fontWeight: 700, color: '#64748b', textTransform: 'uppercase',
    letterSpacing: '.05em', marginBottom: 10,
};

/**
 * Blade's access modal: the roles as checkboxes, then each individual permission
 * as a toggle. Both lists are seeded from the user each time it opens.
 */
export default function EditAccessModal({ user, roles, permissions, onClose, onSave }) {
    const [selectedRoles, setSelectedRoles] = useState([]);
    const [selectedPermissions, setSelectedPermissions] = useState([]);
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        setSelectedRoles(user?.roles ?? []);
        setSelectedPermissions(user?.permissions ?? []);
        setError('');
    }, [user]);

    function toggle(list, setList, value, on) {
        setList(on ? [...list, value] : list.filter((item) => item !== value));
    }

    async function save() {
        setSaving(true);
        setError('');
        try {
            await onSave(user, { roles: selectedRoles, permissions: selectedPermissions });
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
            <div style={SECTION_LABEL}>Profiles</div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginBottom: 20 }}>
                {roles.map((role) => (
                    <label key={role} style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, color: '#374151' }}>
                        <input
                            type="checkbox" value={role}
                            checked={selectedRoles.includes(role)}
                            onChange={(e) => toggle(selectedRoles, setSelectedRoles, role, e.target.checked)}
                        />
                        {role}
                    </label>
                ))}
            </div>

            <div style={SECTION_LABEL}>Individual Permissions</div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                {permissions.map((permission) => (
                    <PermissionToggle
                        key={permission.name}
                        name={permission.name}
                        label={permission.label}
                        checked={selectedPermissions.includes(permission.name)}
                        onChange={(name, on) => toggle(selectedPermissions, setSelectedPermissions, name, on)}
                    />
                ))}
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
