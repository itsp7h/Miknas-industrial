import { useEffect, useMemo, useState } from 'react';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import Button from '../../../components/ui/Button';
import BomForm from '../../../components/production/BomForm';
import { qty } from '../../../components/production/statuses';
import { apiDelete, apiGet } from '../../../api/client';
import { useToast } from '../../../components/ui/Toast';

export default function BomListPage() {
    const [rows, setRows] = useState([]);
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const { showToast } = useToast();

    function load() {
        return apiGet('/production/bom')
            .then((response) => setRows(response.data))
            .catch(() => showToast('Failed to load the bill of materials.', 'error'));
    }

    useEffect(() => {
        load();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return rows;
        return rows.filter((r) =>
            [r.product_name, r.raw_material_name].some((field) => String(field ?? '').toLowerCase().includes(q))
        );
    }, [rows, query]);

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

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <h1 style={{ fontSize: 18, fontWeight: 700 }}>Bill of Materials</h1>
                <Button onClick={() => { setEditing(null); setModalOpen(true); }}>Add</Button>
            </div>

            <div style={{ marginBottom: 12 }}>
                <input type="search" value={query} onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search bill of materials…" aria-label="Search bill of materials"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full" />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {query ? `${filtered.length} of ${rows.length} lines` : `${rows.length} lines`}
                </div>
            </div>

            {filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {query ? 'No BOM lines match that search.' : 'No bill of materials defined yet.'}
                </p>
            )}

            {filtered.map((row) => (
                <div key={row.id} style={{ border: '1px solid #e2e8f0', borderRadius: 8, padding: 12, marginBottom: 8 }}>
                    <div onClick={() => { setEditing(row); setModalOpen(true); }}>
                        <div style={{ fontWeight: 600 }}>{row.product_name ?? '—'}</div>
                        <div style={{ fontSize: 13, color: '#64748b' }}>
                            needs {qty(row.quantity_required)} {row.unit_of_measure} of {row.raw_material_name ?? '—'}
                        </div>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 8 }}>
                        <Button variant="link-danger" onClick={() => setDeleting(row)}>Delete</Button>
                    </div>
                </div>
            ))}

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
        </div>
    );
}
