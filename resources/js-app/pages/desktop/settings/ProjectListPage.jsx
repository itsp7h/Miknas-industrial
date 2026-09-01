import ConfirmModal from '../../../components/ui/ConfirmModal';
import AddProjectModal from '../../../components/settings/project/AddProjectModal';
import LocationMapModal from '../../../components/settings/project/LocationMapModal';
import ProjectCard from '../../../components/settings/project/ProjectCard';
import ProjectImportModal from '../../../components/settings/project/ProjectImportModal';
import ProjectStatCards from '../../../components/settings/project/ProjectStatCards';
import useProjectList from '../../../components/settings/project/useProjectList';
import { DownloadIcon, PlusIcon, UploadIcon } from '../../../components/settings/project/icons';

export default function ProjectListPage() {
    const p = useProjectList();

    return (
        <div>
            <div className="mb-5" style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 12 }}>
                <div>
                    <h1 className="page-title">Projects</h1>
                    <p className="page-subtitle">Manage projects and their locations.</p>
                </div>
                <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexShrink: 0 }}>
                    <a
                        href="/api/v1/settings/projects/template" className="btn-secondary"
                        style={{ display: 'inline-flex', alignItems: 'center', gap: 6, textDecoration: 'none' }}
                    >
                        <DownloadIcon />
                        Template
                    </a>
                    <button
                        type="button" onClick={() => p.setImportOpen(true)} className="btn-secondary"
                        style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}
                    >
                        <UploadIcon />
                        Import Excel
                    </button>
                    <button
                        type="button" onClick={() => p.setAddOpen(true)} className="btn-primary"
                        style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}
                    >
                        <PlusIcon />
                        Add Project
                    </button>
                </div>
            </div>

            <ProjectStatCards meta={p.meta} />

            {p.loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}

            {!p.loading && p.projects.length === 0 && (
                <div className="card card-body" style={{ textAlign: 'center', padding: '3rem', color: '#9ca3af' }}>
                    No projects yet. Click &quot;Add Project&quot; to create one.
                </div>
            )}

            {p.projects.map((project) => (
                <ProjectCard
                    key={project.id}
                    project={project}
                    companies={p.companies}
                    onSave={p.saveProject}
                    onDelete={p.setDeleting}
                    onAddLocation={(target) => p.setLocationModal({ project: target, location: null })}
                    onEditLocation={(target, location) => p.setLocationModal({ project: target, location })}
                    onDeleteLocation={(target, location) => p.setDeletingLocation({ project: target, location })}
                />
            ))}

            <AddProjectModal
                open={p.addOpen} companies={p.companies}
                onClose={() => p.setAddOpen(false)} onSave={p.addProject}
            />
            <ProjectImportModal open={p.importOpen} onClose={() => p.setImportOpen(false)} onImport={p.handleImport} />
            <LocationMapModal
                open={!!p.locationModal}
                project={p.locationModal?.project}
                location={p.locationModal?.location}
                onClose={() => p.setLocationModal(null)}
                onSave={p.saveLocation}
            />
            <ConfirmModal
                open={!!p.deleting}
                title="Delete this project?"
                body={p.deleting
                    ? `"${p.deleting.name}" and its ${p.deleting.locations?.length ?? 0} location(s) will be permanently removed.`
                    : ''}
                onConfirm={p.handleDelete}
                onCancel={() => p.setDeleting(null)}
            />
            <ConfirmModal
                open={!!p.deletingLocation}
                title="Delete this location?"
                body={p.deletingLocation
                    ? `"${p.deletingLocation.location.name}" will be permanently removed from ${p.deletingLocation.project.name}.`
                    : ''}
                onConfirm={p.handleDeleteLocation}
                onCancel={() => p.setDeletingLocation(null)}
            />
        </div>
    );
}
