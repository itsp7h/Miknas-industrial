import { useState } from 'react';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import AddCompanyModal from '../../../components/settings/company/AddCompanyModal';
import { InlineEditor } from '../../../components/settings/company/CompanyCard';
import CompanyImages from '../../../components/settings/company/CompanyImages';
import useCompanyList from '../../../components/settings/company/useCompanyList';
import {
    ActionSheet, Avatar, BarButton, EmptyState, Hero, Loading, MobilePage, Pill,
} from '../../../components/mobile/ui';
import Icon from '../../../components/mobile/icons';
import { C } from '../../../components/mobile/theme';
import { useAccess } from '../../../layouts/AccessContext';

// Companies & departments (SteelERP-Mobile-Designs-V2): one card per company
// with its logo and stamp, its departments as rows, and "Add department" at
// the foot. A company's ⋯ and a department's row open what can be done to it;
// edits happen in place, as on desktop.

const deny = (what) => `You do not have permission to ${what}`;

function CompanyBlock({ company, c, can }) {
    const [menu, setMenu] = useState(false);
    const [editing, setEditing] = useState(false);
    const [adding, setAdding] = useState(false);
    const [department, setDepartment] = useState(null); // the one whose sheet is open
    const [editingDepartmentId, setEditingDepartmentId] = useState(null);
    const departments = company.departments ?? [];

    return (
        <section style={{ background: C.card, borderRadius: 20, overflow: 'hidden' }}>
            <div style={{ padding: 16, display: 'flex', flexDirection: 'column', gap: 12 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                    {company.logo ? (
                        <img
                            src={company.logo} alt={`${company.name} logo`}
                            style={{ width: 46, height: 46, objectFit: 'contain', borderRadius: 12, border: `1px solid ${C.line}`, background: '#FFFFFF', flexShrink: 0 }}
                        />
                    ) : (
                        <span style={{ border: `1px solid ${C.line}`, borderRadius: 12 }}>
                            <Avatar name={company.name} size={44} tone="slate" />
                        </span>
                    )}
                    <div style={{ flex: 1, minWidth: 0 }}>
                        <div style={{ fontSize: 17, fontWeight: 600, display: 'flex', alignItems: 'center', gap: 8 }}>
                            <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{company.name}</span>
                            {!company.is_active && <Pill tone="red">Inactive</Pill>}
                        </div>
                        <div style={{ fontSize: 14, color: C.muted, marginTop: 2 }}>
                            {departments.length} department{departments.length === 1 ? '' : 's'}
                            {company.project_count !== undefined && ` · ${company.project_count} project${company.project_count === 1 ? '' : 's'}`}
                        </div>
                    </div>
                    <button
                        type="button" aria-label={`Options for ${company.name}`} onClick={() => setMenu(true)}
                        style={{ width: 40, height: 40, borderRadius: 20, border: 0, background: C.hairline, color: C.text, display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer' }}
                    >
                        <Icon name="more" size={20} />
                    </button>
                </div>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    <CompanyImages company={company} canEdit={can('companies.edit')} onUpload={c.uploadImage} onRemove={c.removeImage} pills />
                </div>
            </div>

            {editing && (
                <InlineEditor
                    value={company.name} active={company.is_active} placeholder="Company name"
                    onSave={async (values) => { await c.saveCompany(company, values); setEditing(false); }}
                    onCancel={() => setEditing(false)}
                />
            )}

            {departments.map((d) => (
                <div key={d.id}>
                    <button
                        type="button" onClick={() => setDepartment(d)}
                        style={{
                            display: 'flex', alignItems: 'center', gap: 12, width: '100%', minHeight: 52, padding: '0 16px',
                            border: 0, borderTop: `1px solid ${C.hairline}`, background: 'none', font: 'inherit', textAlign: 'left',
                            color: C.text, cursor: 'pointer',
                        }}
                    >
                        <Icon name="tree" size={20} style={{ color: d.is_active ? '#7C3AED' : C.fainter }} />
                        <span style={{ flex: 1, minWidth: 0, fontSize: 16, color: d.is_active ? C.text : C.faint }}>{d.name}</span>
                        {!d.is_active && <Pill tone="red">Inactive</Pill>}
                        <Icon name="chevronRight" size={18} strokeWidth={2} style={{ color: C.fainter }} />
                    </button>
                    {editingDepartmentId === d.id && (
                        <InlineEditor
                            value={d.name} active={d.is_active} placeholder="Department name" compact
                            onSave={async (values) => { await c.saveDepartment(company, d, values); setEditingDepartmentId(null); }}
                            onCancel={() => setEditingDepartmentId(null)}
                        />
                    )}
                </div>
            ))}

            {adding ? (
                <InlineEditor
                    value="" placeholder="Department name…" compact
                    onSave={async ({ name }) => { await c.addDepartment(company, name); setAdding(false); }}
                    onCancel={() => setAdding(false)}
                />
            ) : (
                <button
                    type="button" onClick={() => setAdding(true)} disabled={!can('companies.create')}
                    title={can('companies.create') ? undefined : deny('add departments')}
                    style={{
                        display: 'flex', alignItems: 'center', gap: 12, width: '100%', minHeight: 52, padding: '0 16px',
                        border: 0, borderTop: `1px solid ${C.hairline}`, background: 'none', font: 'inherit',
                        color: C.accent, fontSize: 16, fontWeight: 600, cursor: 'pointer',
                        opacity: can('companies.create') ? 1 : 0.5,
                    }}
                >
                    <Icon name="plus" size={20} strokeWidth={2.2} />Add department
                </button>
            )}

            <ActionSheet
                open={menu} onClose={() => setMenu(false)} title={company.name}
                options={[
                    { label: 'Rename / set active', onClick: () => setEditing(true), disabled: !can('companies.edit'), disabledReason: deny('edit companies') },
                    { label: 'Delete company', danger: true, onClick: () => c.setDeleting(company), disabled: !can('companies.delete'), disabledReason: deny('delete companies') },
                ]}
            />
            <ActionSheet
                open={!!department} onClose={() => setDepartment(null)}
                title={department ? `${department.name} · ${company.name}` : ''}
                options={department ? [
                    { label: 'Rename / set active', onClick: () => setEditingDepartmentId(department.id), disabled: !can('companies.edit'), disabledReason: deny('edit departments') },
                    { label: 'Delete department', danger: true, onClick: () => c.setDeletingDepartment({ company, department }), disabled: !can('companies.delete'), disabledReason: deny('delete departments') },
                ] : []}
            />
        </section>
    );
}

export default function CompanyListPage() {
    const c = useCompanyList();
    const { can } = useAccess();
    const companies = c.meta?.total_companies ?? c.companies.length;
    const departments = c.meta?.total_departments ?? 0;

    return (
        <MobilePage gap={16}>
            <Hero
                zone="system"
                back={{ to: '/app/more', label: 'More' }}
                title="Companies"
                subtitle={`${companies} compan${companies === 1 ? 'y' : 'ies'} · ${departments} department${departments === 1 ? '' : 's'}`}
                actions={(
                    <BarButton
                        icon="plus" label="Add company" onClick={() => c.setAddOpen(true)}
                        disabled={!can('companies.create')} title={can('companies.create') ? 'Add company' : deny('add companies')}
                    />
                )}
            />

            {c.loading && <Loading />}
            {!c.loading && c.companies.length === 0 && <EmptyState icon="building" title="No companies yet" />}
            {c.companies.map((company) => <CompanyBlock key={company.id} company={company} c={c} can={can} />)}

            <AddCompanyModal open={c.addOpen} onClose={() => c.setAddOpen(false)} onSave={c.addCompany} />
            <ConfirmModal
                open={!!c.deleting}
                title="Delete this company?"
                body={c.deleting
                    ? `"${c.deleting.name}" and its ${c.deleting.departments?.length ?? 0} department(s) will be permanently removed. A company that still owns projects cannot be deleted.`
                    : ''}
                onConfirm={c.handleDelete}
                onCancel={() => c.setDeleting(null)}
            />
            <ConfirmModal
                open={!!c.deletingDepartment}
                title="Delete this department?"
                body={c.deletingDepartment
                    ? `"${c.deletingDepartment.department.name}" will be permanently removed from ${c.deletingDepartment.company.name}.`
                    : ''}
                onConfirm={c.handleDeleteDepartment}
                onCancel={() => c.setDeletingDepartment(null)}
            />
        </MobilePage>
    );
}
