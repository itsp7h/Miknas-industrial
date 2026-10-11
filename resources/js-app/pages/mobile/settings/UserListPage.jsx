import { useState } from 'react';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import EditAccessModal from '../../../components/settings/user/EditAccessModal';
import NewUserModal from '../../../components/settings/user/NewUserModal';
import ResetPasswordModal from '../../../components/settings/user/ResetPasswordModal';
import useUserList from '../../../components/settings/user/useUserList';
import {
    ActionSheet, BarButton, Card, EmptyState, Hero, Loading, MobilePage, Pill, SearchField, SectionLabel,
} from '../../../components/mobile/ui';
import { C } from '../../../components/mobile/theme';

// System → Users (SteelERP-Mobile-Designs-V2): everyone who can sign in,
// with their profile, and what each profile means underneath. Tapping a user
// offers edit access, reset password and delete. Admin-only, like the route.

const ROLE_STYLE = {
    Admin: { avatar: '#2563EB', tone: 'blue' },
    Finance: { avatar: '#15803D', tone: 'green' },
    'Operation Manager': { avatar: '#B45309', tone: 'amber' },
    GM: { avatar: '#6D28D9', tone: 'violet' },
};
const roleStyle = (role) => ROLE_STYLE[role] ?? { avatar: '#475569', tone: 'slate' };

export default function UserListPage() {
    const u = useUserList();
    const [picked, setPicked] = useState(null);

    return (
        <MobilePage gap={14}>
            <Hero
                zone="system"
                back={{ to: '/app/more', label: 'More' }}
                title="Users"
                subtitle="Assign profiles and permissions for each employee."
                actions={<BarButton icon="plus" label="New user" onClick={() => u.setNewUserOpen(true)} />}
            />

            <SearchField value={u.query} onChange={u.setQuery} placeholder="Search users" />
            {u.query && <span style={{ fontSize: 13, color: C.muted, padding: '0 4px' }}>{u.filtered.length} of {u.users.length} users</span>}

            {u.loading && <Loading />}
            {!u.loading && u.filtered.length === 0 && <EmptyState icon="users" title="No users match your search" />}
            {u.filtered.length > 0 && (
                <Card>
                    {u.filtered.map((user, i) => {
                        const role = user.roles?.[0];
                        const extra = (user.permissions ?? []).length;

                        return (
                            <button
                                key={user.id} type="button" onClick={() => setPicked(user)}
                                style={{
                                    display: 'flex', alignItems: 'center', gap: 12, padding: '12px 16px', width: '100%', border: 0,
                                    borderBottom: i === u.filtered.length - 1 ? 0 : `1px solid ${C.hairline}`,
                                    background: 'none', font: 'inherit', textAlign: 'left', color: C.text, cursor: 'pointer',
                                }}
                            >
                                <span style={{
                                    width: 44, height: 44, borderRadius: 22, flexShrink: 0, background: roleStyle(role).avatar, color: '#FFFFFF',
                                    fontSize: 17, fontWeight: 600, display: 'flex', alignItems: 'center', justifyContent: 'center',
                                }}>
                                    {(user.name || '?').charAt(0).toUpperCase()}
                                </span>
                                <span style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', gap: 2 }}>
                                    <span style={{ fontSize: 16, fontWeight: 600 }}>{user.name}</span>
                                    <span style={{ fontSize: 14, color: C.muted, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{user.email}</span>
                                    {extra > 0 && <span style={{ fontSize: 12, color: C.faint }}>{extra} individual permission{extra === 1 ? '' : 's'}</span>}
                                </span>
                                {role ? <Pill tone={roleStyle(role).tone}>{role}</Pill> : <span style={{ fontSize: 12, color: C.fainter }}>No profile</span>}
                            </button>
                        );
                    })}
                </Card>
            )}

            {u.profiles.length > 0 && (
                <>
                    <SectionLabel zone="system">Profiles</SectionLabel>
                    <Card style={{ padding: '0 16px' }}>
                        {u.profiles.map((profile, i) => (
                            <div key={profile.name} style={{ padding: '13px 0', fontSize: 15, lineHeight: 1.45, borderBottom: i === u.profiles.length - 1 ? 0 : `1px solid ${C.hairline}` }}>
                                <strong style={{ fontWeight: 600 }}>{profile.name}</strong>
                                <span style={{ color: C.muted }}> · {profile.description}</span>
                            </div>
                        ))}
                    </Card>
                </>
            )}

            <ActionSheet
                open={!!picked} onClose={() => setPicked(null)} title={picked ? `${picked.name} · ${picked.email}` : ''}
                options={picked ? [
                    { label: 'Edit access', onClick: () => u.setEditing(picked) },
                    { label: 'Reset password', onClick: () => u.setResetting(picked) },
                    { label: `Delete ${picked.name}`, danger: true, onClick: () => u.setDeleting(picked) },
                ] : []}
            />

            <NewUserModal open={u.newUserOpen} profiles={u.profiles} onClose={() => u.setNewUserOpen(false)} onSave={u.createUser} />
            <EditAccessModal user={u.editing} profiles={u.profiles} grid={u.grid} onClose={() => u.setEditing(null)} onSave={u.saveAccess} />
            <ResetPasswordModal key={u.resetting?.id ?? 'none'} user={u.resetting} onClose={() => u.setResetting(null)} onSave={u.resetPassword} />
            <ConfirmModal
                open={!!u.deleting}
                title={`Delete ${u.deleting?.name ?? ''}?`}
                body="Their account is removed and they can no longer sign in. Documents they created will simply stop naming them."
                onConfirm={u.confirmDelete}
                onCancel={() => u.setDeleting(null)}
            />
        </MobilePage>
    );
}
