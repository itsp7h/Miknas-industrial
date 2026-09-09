import EditAccessModal from '../../../components/settings/user/EditAccessModal';
import NewUserModal from '../../../components/settings/user/NewUserModal';
import useUserList from '../../../components/settings/user/useUserList';

const ROLE_PILL = {
    display: 'inline-block', background: '#eff6ff', color: '#2563eb', fontSize: 11,
    fontWeight: 600, padding: '2px 8px', borderRadius: 999,
};

export default function UserListPage() {
    const u = useUserList();

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">User Management</h1>
                <p className="page-subtitle">Assign profiles and toggle individual permissions for each employee.</p>
            </div>

            <button
                type="button" onClick={() => u.setNewUserOpen(true)} className="btn-primary"
                style={{ width: '100%', justifyContent: 'center', marginBottom: 14 }}
            >
                + New User
            </button>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={u.query}
                    onChange={(e) => u.setQuery(e.target.value)}
                    placeholder="Search users…"
                    aria-label="Search users"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {u.query ? `${u.filtered.length} of ${u.users.length} users` : `${u.users.length} users`}
                </div>
            </div>

            {u.loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}

            {!u.loading && u.filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>No users match your search.</p>
            )}

            {u.filtered.map((user) => (
                <div key={user.id} style={{
                    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                    padding: 12, marginBottom: 8,
                }}>
                    <div style={{ fontWeight: 600, color: '#0f172a', fontSize: 14 }}>{user.name}</div>
                    <div style={{ fontSize: 12.5, color: '#64748b', marginTop: 2 }}>{user.email}</div>

                    <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap', marginTop: 8 }}>
                        {(user.roles ?? []).length === 0
                            ? <span style={{ color: '#cbd5e1', fontSize: 12 }}>No profile</span>
                            : user.roles.map((role) => <span key={role} style={ROLE_PILL}>{role}</span>)}
                    </div>

                    {/* The permission count matters here: the toggles themselves are
                        behind the modal, so the card says how many are on. */}
                    {(user.permissions ?? []).length > 0 && (
                        <div style={{ fontSize: 11.5, color: '#94a3b8', marginTop: 6 }}>
                            {user.permissions.length} individual permission{user.permissions.length === 1 ? '' : 's'}
                        </div>
                    )}

                    <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 10 }}>
                        <button type="button" onClick={() => u.setEditing(user)} className="btn-secondary btn-sm">Edit Access</button>
                    </div>
                </div>
            ))}

            <NewUserModal
                open={u.newUserOpen} roles={u.roles}
                onClose={() => u.setNewUserOpen(false)} onSave={u.createUser}
            />
            <EditAccessModal
                user={u.editing} roles={u.roles} permissions={u.permissions}
                onClose={() => u.setEditing(null)} onSave={u.saveAccess}
            />
        </div>
    );
}
