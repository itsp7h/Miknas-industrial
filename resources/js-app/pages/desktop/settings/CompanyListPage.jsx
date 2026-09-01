import ConfirmModal from '../../../components/ui/ConfirmModal';
import AddCompanyModal from '../../../components/settings/company/AddCompanyModal';
import CompanyCard from '../../../components/settings/company/CompanyCard';
import StatCards from '../../../components/settings/company/StatCards';
import useCompanyList from '../../../components/settings/company/useCompanyList';
import { PlusIcon } from '../../../components/settings/company/icons';

export default function CompanyListPage() {
    const c = useCompanyList();

    return (
        <div>
            <div className="mb-5" style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 12 }}>
                <div>
                    <h1 className="page-title">Companies &amp; Departments</h1>
                    <p className="page-subtitle">Manage companies and their departments.</p>
                </div>
                <button
                    type="button" onClick={() => c.setAddOpen(true)} className="btn-primary"
                    style={{ display: 'inline-flex', alignItems: 'center', gap: 6, flexShrink: 0 }}
                >
                    <PlusIcon />
                    Add Company
                </button>
            </div>

            <StatCards meta={c.meta} />

            {c.loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}

            {!c.loading && c.companies.length === 0 && (
                <div className="card card-body" style={{ textAlign: 'center', padding: '3rem', color: '#9ca3af' }}>
                    No companies yet. Add your first company above.
                </div>
            )}

            {c.companies.map((company) => (
                <CompanyCard
                    key={company.id}
                    company={company}
                    onSave={c.saveCompany}
                    onDelete={c.setDeleting}
                    onAddDepartment={c.addDepartment}
                    onSaveDepartment={c.saveDepartment}
                    onDeleteDepartment={(co, department) => c.setDeletingDepartment({ company: co, department })}
                />
            ))}

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
        </div>
    );
}
