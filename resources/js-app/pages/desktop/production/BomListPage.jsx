import { useMemo, useState } from 'react';
import Card from '../../../components/ui/Card';
import Table from '../../../components/ui/Table';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import Button from '../../../components/ui/Button';
import BomForm from '../../../components/production/BomForm';
import { qty } from '../../../components/production/statuses';
import { apiDelete, apiGet } from '../../../api/client';
import { useToast } from '../../../components/ui/Toast';
import { useEffect } from 'react';

export default function BomListPage() {
    const [rows, setRows] = useState([]);
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const { showToast } = useToast();

    // A bill of materials is reference data — it changes when someone edits it,
    // not from activity elsewhere, so there is nothing to subscribe to.
    function load() {
        return apiGet('/production/bom')
            .then((response) => setRows(response.data))
            .catch(() => showToast('Failed to load the bill of materials.', 'error'));
    }

    useEffect(() => {
        load();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    async function handleSaved() {
        setModalOpen(false);
        await load();
        showToast('BOM line saved.', 'success');
    }

    async function handleDeleteConfirmed() {
        const entry = deleting;
        setDeleting(null);
        try {
            await apiDelete(`/production/bom/${entry.id}`);
            setRows((prev) => prev.filter((row) => row.id !== entry.id));
            showToast('BOM line removed.', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to remove that line.', 'error');
        }
    }

    const columns = useMemo(() => [
        { key: 'product_name', label: 'Product', render: (row) => row.product_name ?? '—' },
        { key: 'raw_material_name', label: 'Raw Material', render: (row) => row.raw_material_name ?? '—' },
        { key: 'quantity_required', label: 'Quantity', render: (row) => qty(row.quantity_required) },
        { key: 'unit_of_measure', label: 'Unit' },
        {
            key: 'actions',
            label: '',
            render: (row) => (
                <div style={{ display: 'flex', gap: 8 }}>
                    <Button variant="link" onClick={() => { setEditing(row); setModalOpen(true); }}>Edit</Button>
                    <Button variant="link-danger" onClick={() => setDeleting(row)}>Delete</Button>
                </div>
            ),
        },
    ], []);

    return (
        <Card title="Bill of Materials">
            <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 12 }}>
                <Button onClick={() => { setEditing(null); setModalOpen(true); }}>Add BOM Line</Button>
            </div>

            <Table columns={columns} rows={rows} rowKey={(row) => row.id} searchPlaceholder="Search bill of materials…" />

            <Modal open={modalOpen} title={editing ? 'Edit BOM Line' : 'Add BOM Line'} onClose={() => setModalOpen(false)}>
                <BomForm entry={editing} onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!deleting}
                title="Remove this BOM line?"
                body={deleting ? `${deleting.raw_material_name} will no longer be required for ${deleting.product_name}.` : ''}
                onConfirm={handleDeleteConfirmed}
                onCancel={() => setDeleting(null)}
            />
        </Card>
    );
}
