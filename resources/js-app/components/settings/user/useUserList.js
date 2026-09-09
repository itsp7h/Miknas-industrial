import { useCallback, useEffect, useState } from 'react';
import { apiGet, apiPost, apiPut } from '../../../api/client';
import { useToast } from '../../ui/Toast';

/** Users, the role list and the per-permission toggles. */
export default function useUserList() {
    const [users, setUsers] = useState([]);
    const [roles, setRoles] = useState([]);
    const [permissions, setPermissions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [query, setQuery] = useState('');
    const [newUserOpen, setNewUserOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const { showToast } = useToast();

    const load = useCallback(() => apiGet('/settings/users')
        .then((response) => {
            setUsers(response.data);
            setRoles(response.roles ?? []);
            setPermissions(response.permissions ?? []);
        })
        .catch(() => showToast('Failed to load users.', 'error'))
        .finally(() => setLoading(false)),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    []);

    useEffect(() => {
        load();
    }, [load]);

    async function createUser(payload) {
        const response = await apiPost('/settings/users', payload);
        setUsers((prev) => [...prev, response.data].sort((a, b) => a.name.localeCompare(b.name)));
        // The server says whether a setup email went out, so its own wording is
        // the honest thing to show.
        showToast(response.message, 'success');
    }

    async function saveAccess(user, { roles: nextRoles, permissions: nextPermissions }) {
        const response = await apiPut(`/settings/users/${user.id}`, {
            roles: nextRoles,
            permissions: nextPermissions,
        });
        setUsers((prev) => prev.map((row) => (row.id === user.id ? response.data : row)));
        showToast(response.message, 'success');
    }

    const q = query.trim().toLowerCase();
    const filtered = q
        ? users.filter((user) => [user.name, user.email, ...(user.roles ?? [])]
            .some((field) => String(field ?? '').toLowerCase().includes(q)))
        : users;

    return {
        users, filtered, roles, permissions, loading,
        query, setQuery,
        newUserOpen, setNewUserOpen, createUser,
        editing, setEditing, saveAccess,
    };
}
