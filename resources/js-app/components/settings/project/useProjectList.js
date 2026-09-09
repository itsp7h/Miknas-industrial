import { useCallback, useEffect, useState } from 'react';
import { apiDelete, apiGet, apiPost, apiPostForm, apiPut } from '../../../api/client';
import { useToast } from '../../ui/Toast';

const EMPTY_META = { total_projects: 0, active_projects: 0, total_locations: 0, total_companies: 0 };

/** Projects, their locations, the company picker list, and the Excel import. */
export default function useProjectList() {
    const [projects, setProjects] = useState([]);
    const [companies, setCompanies] = useState([]);
    const [meta, setMeta] = useState(EMPTY_META);
    const [loading, setLoading] = useState(true);
    const [addOpen, setAddOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [locationModal, setLocationModal] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const [deletingLocation, setDeletingLocation] = useState(null);
    const { showToast } = useToast();

    const load = useCallback((quiet = false) => {
        if (!quiet) setLoading(true);

        return apiGet('/settings/projects')
            .then((response) => {
                setProjects(response.data);
                setCompanies(response.companies ?? []);
                setMeta(response.meta ?? EMPTY_META);
            })
            .catch(() => showToast('Failed to load projects.', 'error'))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    /**
     * Every write returns the whole project and the stats are derived from the
     * list, so a reload keeps the four stat boxes honest without counting by
     * hand the way the Blade page did.
     */
    const refresh = () => load(true);

    async function addProject({ name, company_id: companyId }) {
        await apiPost('/settings/projects', { name, company_id: companyId || null });
        await refresh();
        showToast(`Project "${name}" added.`, 'success');
    }

    async function saveProject(project, values) {
        await apiPut(`/settings/projects/${project.id}`, {
            name: values.name,
            company_id: values.company_id || null,
            is_active: values.is_active,
        });
        await refresh();
        showToast('Project saved.', 'success');
    }

    async function handleDelete() {
        const project = deleting;
        setDeleting(null);
        try {
            await apiDelete(`/settings/projects/${project.id}`);
            await refresh();
            showToast(`Project "${project.name}" deleted.`, 'success');
        } catch (err) {
            showToast(err.message || 'Failed to delete that project.', 'error');
        }
    }

    async function saveLocation(project, location, values) {
        const path = location
            ? `/settings/projects/${project.id}/locations/${location.id}`
            : `/settings/projects/${project.id}/locations`;

        if (location) await apiPut(path, values);
        else await apiPost(path, values);

        await refresh();
        showToast(location ? 'Location updated.' : `Location "${values.name}" added.`, 'success');
    }

    async function handleDeleteLocation() {
        const { project, location } = deletingLocation;
        setDeletingLocation(null);
        try {
            await apiDelete(`/settings/projects/${project.id}/locations/${location.id}`);
            await refresh();
            showToast(`Location "${location.name}" deleted.`, 'success');
        } catch (err) {
            showToast(err.message || 'Failed to delete that location.', 'error');
        }
    }

    async function handleImport(file) {
        const formData = new FormData();
        formData.append('file', file);
        try {
            const result = await apiPostForm('/settings/projects/import', formData);
            // The import can create companies, projects and departments at once,
            // so the server's own summary is the honest thing to show.
            showToast(result.message, 'success');
            setImportOpen(false);
            await refresh();
        } catch (err) {
            showToast(err.message || 'Import failed.', 'error');
        }
    }

    return {
        projects, companies, meta, loading,
        addOpen, setAddOpen, addProject, saveProject,
        deleting, setDeleting, handleDelete,
        locationModal, setLocationModal, saveLocation,
        deletingLocation, setDeletingLocation, handleDeleteLocation,
        importOpen, setImportOpen, handleImport,
    };
}
