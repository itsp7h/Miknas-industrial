const ROLE_PILL = {
    display: 'inline-block', background: '#eff6ff', color: '#2563eb', fontSize: 11,
    fontWeight: 600, padding: '2px 8px', borderRadius: 999, marginRight: 4,
};

/** Blade's four columns, roles as blue pills. */
export default function UserTable({ users, onEditAccess }) {
    return (
        <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: 14, overflow: 'hidden' }}>
            <table className="table-base" style={{ width: '100%' }}>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Profiles</th>
                        <th />
                    </tr>
                </thead>
                <tbody>
                    {users.map((user) => (
                        <tr key={user.id}>
                            <td>{user.name}</td>
                            <td className="text-gray-500">{user.email}</td>
                            <td>
                                {(user.roles ?? []).length === 0
                                    ? <span style={{ color: '#cbd5e1', fontSize: 12 }}>No profile</span>
                                    : user.roles.map((role) => <span key={role} style={ROLE_PILL}>{role}</span>)}
                            </td>
                            <td className="text-right">
                                <button type="button" onClick={() => onEditAccess(user)} className="btn-secondary btn-sm">
                                    Edit Access
                                </button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            {users.length === 0 && (
                <p style={{ padding: 24, textAlign: 'center', color: '#94a3b8', fontSize: 13 }}>
                    No users match your search.
                </p>
            )}
        </div>
    );
}
