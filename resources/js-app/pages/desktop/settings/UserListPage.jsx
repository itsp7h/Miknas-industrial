import EditAccessModal from '../../../components/settings/user/EditAccessModal';
import NewUserModal from '../../../components/settings/user/NewUserModal';
import UserTable from '../../../components/settings/user/UserTable';
import useUserList from '../../../components/settings/user/useUserList';

export default function UserListPage() {
    const u = useUserList();

    return (
        <div>
            <div className="mb-5">
                <h1 className="page-title">User Management</h1>
                <p className="page-subtitle">Assign profiles and toggle individual permissions for each employee.</p>
            </div>

            <div style={{ marginBottom: 16, display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 16 }}>
                <input
                    type="text"
                    value={u.query}
                    onChange={(e) => u.setQuery(e.target.value)}
                    placeholder="Search users…"
                    aria-label="Search users"
                    style={{
                        width: '100%', maxWidth: 320, padding: '9px 12px',
                        border: '1.5px solid #e2e8f0', borderRadius: 8, fontSize: 14, outline: 'none',
                    }}
                />
                <div style={{ display: 'flex', alignItems: 'center', gap: 16, flexShrink: 0 }}>
                    <span style={{ fontSize: 12, color: '#94a3b8', fontWeight: 500 }}>
                        {u.query ? `${u.filtered.length} of ${u.users.length}` : u.users.length}
                    </span>
                    <button type="button" onClick={() => u.setNewUserOpen(true)} className="btn-primary btn-sm">+ New User</button>
                </div>
            </div>

            {u.loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}

            {!u.loading && <UserTable users={u.filtered} onEditAccess={u.setEditing} />}

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
