import { useMemo } from 'react';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import SupplierModal from '../../../components/purchase/supplier/SupplierModal';
import SupplierStatCards from '../../../components/purchase/supplier/SupplierStatCards';
import SupplierToolbar from '../../../components/purchase/supplier/SupplierToolbar';
import SupplierTable from '../../../components/purchase/supplier/SupplierTable';
import SupplierSearch from '../../../components/purchase/supplier/SupplierSearch';
import useSupplierList from '../../../components/purchase/supplier/useSupplierList';
import { matchesQuery } from '../../../components/purchase/supplier/supplierStats';

export default function SupplierListPage() {
    const s = useSupplierList();

    // Client-side filtering over the whole list, per CLAUDE.md gotcha #6.
    const filtered = useMemo(
        () => s.suppliers.filter((supplier) => matchesQuery(supplier, s.query)),
        [s.suppliers, s.query]
    );

    return (
        <div>
            <div className="page-header">
                <div>
                    <h1 className="page-title">Suppliers</h1>
                    <p className="page-subtitle">Manage your supplier directory</p>
                </div>
                <SupplierToolbar
                    fileInputRef={s.fileInputRef}
                    onImport={s.handleImport}
                    onCreate={s.openCreate}
                />
            </div>

            <SupplierStatCards suppliers={s.suppliers} />

            <SupplierSearch
                query={s.query}
                onChange={s.setQuery}
                shown={filtered.length}
                total={s.suppliers.length}
            />

            <SupplierTable suppliers={filtered} onEdit={s.openEdit} onDelete={s.setDeleting} />

            {/* Its own dialog, on the same shell as the MPR modal — not the
                small generic ui/Modal a sixteen-field form was cramped into. */}
            {s.modalOpen && (
                <SupplierModal
                    supplier={s.editing}
                    onSaved={s.handleSaved}
                    onCancel={() => s.setModalOpen(false)}
                />
            )}
            <ConfirmModal
                open={!!s.deleting}
                title="Delete supplier?"
                body={s.deleting ? `This will permanently remove "${s.deleting.name}".` : ''}
                onConfirm={s.handleDeleteConfirmed}
                onCancel={() => s.setDeleting(null)}
            />
        </div>
    );
}
