import { useCallback, useEffect, useState } from 'react';
import { apiDelete, apiGet, apiPost, apiPut } from '../../../api/client';
import { useToast } from '../../ui/Toast';

/**
 * Companies and their departments. Reference data an admin edits directly, so
 * there is nothing to subscribe to — it changes when someone on this page
 * changes it.
 */
export default function useCompanyList() {
    const [companies, setCompanies] = useState([]);
    const [meta, setMeta] = useState({ total_companies: 0, total_departments: 0 });
    const [loading, setLoading] = useState(true);
    const [addOpen, setAddOpen] = useState(false);
    const [deleting, setDeleting] = useState(null);
    const [deletingDepartment, setDeletingDepartment] = useState(null);
    const { showToast } = useToast();

    const load = useCallback(() => apiGet('/settings/companies')
        .then((response) => {
            setCompanies(response.data);
            setMeta(response.meta ?? { total_companies: 0, total_departments: 0 });
        })
        .catch(() => showToast('Failed to load companies.', 'error'))
        .finally(() => setLoading(false)),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    []);

    useEffect(() => {
        load();
    }, [load]);

    /** Replaces one card in place — department writes return the whole company. */
    function replaceCompany(company) {
        setCompanies((prev) => prev.map((row) => (row.id === company.id ? company : row)));
    }

    async function addCompany(name) {
        const response = await apiPost('/settings/companies', { name });
        setCompanies((prev) => [...prev, response.data].sort((a, b) => a.name.localeCompare(b.name)));
        setMeta((prev) => ({ ...prev, total_companies: prev.total_companies + 1 }));
        showToast(`Company "${response.data.name}" added.`, 'success');
    }

    async function saveCompany(company, { name, is_active: isActive }) {
        const response = await apiPut(`/settings/companies/${company.id}`, { name, is_active: isActive });
        replaceCompany(response.data);
        showToast('Company saved.', 'success');
    }

    async function handleDelete() {
        const company = deleting;
        setDeleting(null);
        try {
            await apiDelete(`/settings/companies/${company.id}`);
            setCompanies((prev) => prev.filter((row) => row.id !== company.id));
            setMeta((prev) => ({
                total_companies: prev.total_companies - 1,
                total_departments: prev.total_departments - (company.departments?.length ?? 0),
            }));
            showToast(`Company "${company.name}" deleted.`, 'success');
        } catch (err) {
            showToast(err.message || 'Failed to delete that company.', 'error');
        }
    }

    async function addDepartment(company, name) {
        const response = await apiPost(`/settings/companies/${company.id}/departments`, { name });
        replaceCompany(response.data);
        setMeta((prev) => ({ ...prev, total_departments: prev.total_departments + 1 }));
        showToast('Department added.', 'success');
    }

    async function saveDepartment(company, department, { name, is_active: isActive }) {
        const response = await apiPut(
            `/settings/companies/${company.id}/departments/${department.id}`,
            { name, is_active: isActive }
        );
        replaceCompany(response.data);
        showToast('Department saved.', 'success');
    }

    async function handleDeleteDepartment() {
        const { company, department } = deletingDepartment;
        setDeletingDepartment(null);
        try {
            const response = await apiDelete(`/settings/companies/${company.id}/departments/${department.id}`);
            replaceCompany(response.data);
            setMeta((prev) => ({ ...prev, total_departments: prev.total_departments - 1 }));
            showToast('Department deleted.', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to delete that department.', 'error');
        }
    }

    return {
        companies, meta, loading,
        addOpen, setAddOpen, addCompany, saveCompany,
        deleting, setDeleting, handleDelete,
        addDepartment, saveDepartment,
        deletingDepartment, setDeletingDepartment, handleDeleteDepartment,
    };
}
