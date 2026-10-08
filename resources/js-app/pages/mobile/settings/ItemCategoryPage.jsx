import { useState } from 'react';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import useItemCategories from '../../../components/settings/itemCategory/useItemCategories';
import {
    ActionSheet, Card, Hero, IconTile, ListRow, MobilePage, PrimaryButton, Segmented, SectionLabel,
} from '../../../components/mobile/ui';
import { C } from '../../../components/mobile/theme';
import { useAccess } from '../../../layouts/AccessContext';

// System → Item categories (SteelERP-Mobile-Designs-V2): the add / rename
// form on top, then the categories under the type they belong to, in their
// order. Tapping one offers rename and delete.

const deny = (what) => `You do not have permission to ${what} item categories`;

export default function ItemCategoryPage() {
    const c = useItemCategories();
    const { can } = useAccess();
    const [picked, setPicked] = useState(null);
    const allowed = c.editing ? can('item-categories.edit') : can('item-categories.create');

    function rename(category) {
        c.openEdit(category);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    return (
        <MobilePage gap={16}>
            <Hero
                zone="system"
                back={{ to: '/app/more', label: 'More' }}
                title="Item categories"
                subtitle={'The sections items are filed under, like “Raw Materials / Chemical Materials”.'}
            />

            <Card padded style={{ gap: 14 }}>
                <h2 style={{ margin: 0, fontSize: 17, fontWeight: 600 }}>
                    {c.editing ? `Rename ${c.editing.name}` : 'Add a category'}
                </h2>
                <div>
                    <input
                        id="ic-name" aria-label="Name" className="form-input" placeholder="e.g. Chemical Materials"
                        value={c.values.name} onChange={(e) => c.setField('name', e.target.value)}
                    />
                    {c.errors.name && <p style={{ margin: '6px 0 0', fontSize: 13, color: '#DC2626' }}>{c.errors.name}</p>}
                </div>
                <div>
                    <span className="form-label">Belongs to</span>
                    <Segmented
                        ariaLabel="Belongs to"
                        value={c.values.parent_type}
                        onChange={(value) => c.setField('parent_type', value)}
                        options={c.parentTypes.map((t) => ({ key: t.value, label: t.label }))}
                    />
                </div>
                <div>
                    <label className="form-label" htmlFor="ic-order">Order (optional)</label>
                    <input
                        id="ic-order" type="number" min="0" inputMode="numeric" className="form-input" placeholder="Last"
                        value={c.values.sort_order} onChange={(e) => c.setField('sort_order', e.target.value)}
                    />
                </div>
                <PrimaryButton
                    onClick={c.save} disabled={c.saving || !allowed} color="#15803D"
                    title={allowed ? undefined : deny(c.editing ? 'rename' : 'add')}
                >
                    {c.saving ? 'Saving…' : (c.editing ? 'Save' : 'Add category')}
                </PrimaryButton>
                {c.editing && (
                    <button type="button" onClick={c.openNew} style={{ background: 'none', border: 0, color: C.accent, font: 'inherit', fontSize: 15, cursor: 'pointer' }}>
                        Cancel rename
                    </button>
                )}
            </Card>

            {c.parentTypes.map((type) => {
                const rows = c.categories.filter((x) => x.parent_type === type.value);
                if (!rows.length) return null;

                return (
                    <section key={type.value} style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                        <SectionLabel zone="system">{type.label} · {rows.length}</SectionLabel>
                        <Card>
                            {rows.map((x, i) => (
                                <ListRow
                                    key={x.id}
                                    onClick={() => setPicked(x)}
                                    leading={<IconTile icon="folder" tone="green" />}
                                    title={x.name}
                                    subtitle={`${x.items_count === 1 ? '1 item' : `${x.items_count ?? 0} items`}${x.sort_order != null ? ` · order ${x.sort_order}` : ''}`}
                                    last={i === rows.length - 1}
                                />
                            ))}
                        </Card>
                    </section>
                );
            })}
            {c.categories.length === 0 && <span style={{ fontSize: 14, color: C.muted, padding: '0 4px' }}>No categories yet.</span>}

            <ActionSheet
                open={!!picked} onClose={() => setPicked(null)} title={picked?.path}
                options={picked ? [
                    { label: 'Rename or reorder', onClick: () => rename(picked), disabled: !can('item-categories.edit'), disabledReason: deny('rename') },
                    { label: 'Delete category', danger: true, onClick: () => c.setDeleting(picked), disabled: !can('item-categories.delete'), disabledReason: deny('delete') },
                ] : []}
            />
            <ConfirmModal
                open={!!c.deleting}
                title="Delete this category?"
                body={c.deleting ? `"${c.deleting.path}" will be removed. A category still holding items cannot be deleted — move them first.` : ''}
                onConfirm={c.confirmDelete}
                onCancel={() => c.setDeleting(null)}
            />
        </MobilePage>
    );
}
