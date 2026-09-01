import { useMemo } from 'react';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import SupplierForm from '../../../components/purchase/supplier/SupplierForm';
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

            <Modal
                open={s.modalOpen}
                title={s.editing ? 'Edit Supplier' : 'New Supplier'}
                onClose={() => s.setModalOpen(false)}
            >
                <SupplierForm supplier={s.editing} onSaved={s.handleSaved} onCancel={() => s.setModalOpen(false)} />
            </Modal>
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
