import { useState } from 'react';
import { Link } from 'react-router-dom';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import AddProjectModal from '../../../components/settings/project/AddProjectModal';
import LocationMapModal from '../../../components/settings/project/LocationMapModal';
import { EditStrip } from '../../../components/settings/project/ProjectCard';
import ProjectImportModal from '../../../components/settings/project/ProjectImportModal';
import useProjectList from '../../../components/settings/project/useProjectList';
import mapLink from '../../../components/map/mapLink';
import {
    ActionSheet, BarButton, Chip, ChipRow, EmptyState, Hero, IconTile, Loading, MobilePage, Pill, StatStrip,
} from '../../../components/mobile/ui';
import Icon from '../../../components/mobile/icons';
import { C } from '../../../components/mobile/theme';
import { useAccess } from '../../../layouts/AccessContext';

// Projects (SteelERP-Mobile-Designs-V2): the figures, a chip per company,
// then a card per project with its site locations. Tapping a project or a
// location offers what can be done with it; Costs opens the project's costs.

const deny = (what) => `You do not have permission to ${what}`;

function ProjectBlock({ project, p, can }) {
    const [menu, setMenu] = useState(false);
    const [editing, setEditing] = useState(false);
    const [location, setLocation] = useState(null);
    const locations = project.locations ?? [];

    return (
        <section style={{ background: C.card, borderRadius: 20, padding: 16, display: 'flex', flexDirection: 'column', gap: 12 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                <button
                    type="button" onClick={() => setMenu(true)} aria-label={`Options for ${project.name}`}
                    style={{ flex: 1, minWidth: 0, display: 'flex', alignItems: 'center', gap: 12, background: 'none', border: 0, padding: 0, font: 'inherit', textAlign: 'left', color: C.text, cursor: 'pointer' }}
                >
                    <IconTile icon="folder" tone={project.is_active ? 'blue' : 'slate'} size={44} />
                    <span style={{ minWidth: 0 }}>
                        <span style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 17, fontWeight: 600 }}>
                            <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{project.name}</span>
                            {!project.is_active && <Pill tone="red">Inactive</Pill>}
                        </span>
                        <span style={{ display: 'block', fontSize: 14, color: C.muted, marginTop: 2 }}>{project.company_name ?? 'No company'}</span>
                    </span>
                </button>
                {can('projects.costs') ? (
                    <Link to={`/app/settings/projects/${project.id}/costs`} style={{ color: C.accent, fontSize: 15, fontWeight: 500, textDecoration: 'none' }}>Costs</Link>
                ) : (
                    <span aria-disabled="true" title="You do not have permission to see project costs" style={{ color: C.fainter, fontSize: 15, cursor: 'not-allowed' }}>Costs</span>
                )}
            </div>

            {editing && (
                <div style={{ margin: '0 -16px' }}>
                    <EditStrip
                        project={project} companies={p.companies}
                        onSave={async (values) => { await p.saveProject(project, values); setEditing(false); }}
                        onCancel={() => setEditing(false)}
                    />
                </div>
            )}

            {locations.map((l) => (
                <button
                    key={l.id} type="button" onClick={() => setLocation(l)}
                    style={{
                        display: 'flex', alignItems: 'flex-start', gap: 10, padding: '12px 14px', borderRadius: 14,
                        background: '#F8FAFC', border: 0, font: 'inherit', textAlign: 'left', color: l.is_active ? C.text : C.faint, cursor: 'pointer',
                    }}
                >
                    <Icon name="pin" size={20} style={{ color: l.is_active ? C.text2 : C.fainter, marginTop: 1 }} />
                    <span style={{ flex: 1, minWidth: 0, fontSize: 15, lineHeight: 1.45 }}>
                        {[l.name, l.address].filter(Boolean).join(' · ')}
                        {!l.is_active && ' · Inactive'}
                    </span>
                </button>
            ))}
            {locations.length === 0 && <span style={{ fontSize: 14, color: C.faint }}>No locations yet.</span>}

            <ActionSheet
                open={menu} onClose={() => setMenu(false)} title={project.name}
                options={[
                    { label: 'Add a location', onClick: () => p.setLocationModal({ project, location: null }), disabled: !can('projects.create'), disabledReason: deny('add locations') },
                    { label: 'Edit project', onClick: () => setEditing(true), disabled: !can('projects.edit'), disabledReason: deny('edit projects') },
                    { label: 'Delete project', danger: true, onClick: () => p.setDeleting(project), disabled: !can('projects.delete'), disabledReason: deny('delete projects') },
                ]}
            />
            <ActionSheet
                open={!!location} onClose={() => setLocation(null)} title={location ? `${location.name} · ${project.name}` : ''}
                options={location ? [
                    location.latitude != null && location.longitude != null
                        && { label: 'Open in maps', href: mapLink(location.latitude, location.longitude), newTab: true },
                    { label: 'Edit location', onClick: () => p.setLocationModal({ project, location }), disabled: !can('projects.edit'), disabledReason: deny('edit locations') },
                    { label: 'Delete location', danger: true, onClick: () => p.setDeletingLocation({ project, location }), disabled: !can('projects.delete'), disabledReason: deny('delete locations') },
                ].filter(Boolean) : []}
            />
        </section>
    );
}

export default function ProjectListPage() {
    const p = useProjectList();
    const { can } = useAccess();
    const [company, setCompany] = useState(null);
    const [tools, setTools] = useState(false);
    const companyNames = [...new Set(p.projects.map((x) => x.company_name).filter(Boolean))].sort();
    const shown = company ? p.projects.filter((x) => x.company_name === company) : p.projects;
    const m = p.meta ?? {};

    return (
        <MobilePage gap={14}>
            <Hero
                zone="system"
                back={{ to: '/app/more', label: 'More' }}
                title="Projects"
                subtitle="Projects and their site locations"
                actions={(
                    <>
                        <BarButton icon="upload" label="Import" onClick={() => setTools(true)} />
                        <BarButton
                            icon="plus" label="Add project" onClick={() => p.setAddOpen(true)}
                            disabled={!can('projects.create')} title={can('projects.create') ? 'Add project' : deny('add projects')}
                        />
                    </>
                )}
            />

            <StatStrip items={[
                { label: 'Projects', value: m.total_projects ?? '—' },
                { label: 'Active', value: m.active_projects ?? '—', color: '#15803D' },
                { label: 'Locations', value: m.total_locations ?? '—', color: '#6D28D9' },
                { label: 'Companies', value: m.total_companies ?? '—' },
            ]} />

            {companyNames.length > 1 && (
                <ChipRow>
                    <Chip zone="system" active={!company} onClick={() => setCompany(null)}>All</Chip>
                    {companyNames.map((name) => (
                        <Chip key={name} zone="system" active={company === name} onClick={() => setCompany(name)}>{name}</Chip>
                    ))}
                </ChipRow>
            )}

            {p.loading && <Loading />}
            {!p.loading && shown.length === 0 && <EmptyState icon="folder" title="No projects yet" />}
            {shown.map((project) => <ProjectBlock key={project.id} project={project} p={p} can={can} />)}

            <ActionSheet
                open={tools} onClose={() => setTools(false)} title="Projects"
                options={[
                    { label: 'Import from Excel', onClick: () => p.setImportOpen(true), disabled: !can('projects.import'), disabledReason: deny('import projects') },
                    can('projects.import')
                        ? { label: 'Download import template', href: '/api/v1/settings/projects/template' }
                        : { label: 'Download import template', disabled: true, disabledReason: deny('import projects'), onClick: () => {} },
                ]}
            />

            <AddProjectModal open={p.addOpen} companies={p.companies} onClose={() => p.setAddOpen(false)} onSave={p.addProject} />
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
                body={p.deleting ? `"${p.deleting.name}" and its ${p.deleting.locations?.length ?? 0} location(s) will be permanently removed.` : ''}
                onConfirm={p.handleDelete}
                onCancel={() => p.setDeleting(null)}
            />
            <ConfirmModal
                open={!!p.deletingLocation}
                title="Delete this location?"
                body={p.deletingLocation ? `"${p.deletingLocation.location.name}" will be permanently removed from ${p.deletingLocation.project.name}.` : ''}
                onConfirm={p.handleDeleteLocation}
                onCancel={() => p.setDeletingLocation(null)}
            />
        </MobilePage>
    );
}
