import { useState } from 'react';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import RequesterModal from '../../../components/settings/requester/RequesterModal';
import useRequesters from '../../../components/settings/requester/useRequesters';
import {
    ActionSheet, Avatar, BarButton, Card, CountLine, EmptyState, Hero, MobilePage, SearchField,
} from '../../../components/mobile/ui';
import Icon from '../../../components/mobile/icons';
import { C } from '../../../components/mobile/theme';
import { useAccess } from '../../../layouts/AccessContext';

// System → Requested by (SteelERP-Mobile-Designs-V2): the people a purchase
// request can be raised for, each with the companies they belong to and a
// one-tap call or WhatsApp. Tapping a person offers edit / remove.

const deny = (what) => `You do not have permission to ${what} people here`;
const digits = (phone) => String(phone ?? '').replace(/[^\d]/g, '');

const ROUND = {
    width: 44, height: 44, borderRadius: 22, display: 'flex', alignItems: 'center', justifyContent: 'center',
    textDecoration: 'none', flexShrink: 0,
};

export default function RequesterPage() {
    const r = useRequesters();
    const { can } = useAccess();
    const [query, setQuery] = useState('');
    const [picked, setPicked] = useState(null);

    const q = query.trim().toLowerCase();
    const shown = q
        ? r.requesters.filter((p) => [p.name, ...p.companies.map((c) => c.name), ...(p.phones ?? [])].join(' ').toLowerCase().includes(q))
        : r.requesters;

    return (
        <MobilePage gap={14}>
            <Hero
                zone="system"
                back={{ to: '/app/more', label: 'More' }}
                title="Requested by"
                subtitle="People a purchase request can be raised for. The MPR form only offers the chosen company's people."
                actions={(
                    <BarButton
                        icon="plus" label="Add person" onClick={r.openNew}
                        disabled={!can('requesters.create')} title={can('requesters.create') ? 'Add person' : deny('add')}
                    />
                )}
            />

            <SearchField value={query} onChange={setQuery} placeholder="Search people" />
            <CountLine>
                {q ? `${shown.length} of ${r.requesters.length}` : r.requesters.length} {r.requesters.length === 1 ? 'person' : 'people'}
            </CountLine>

            {shown.length === 0 ? (
                <EmptyState icon="users" title={r.requesters.length ? 'No one matches' : 'No one yet'}>
                    {!r.requesters.length && 'Add the people who raise purchase requests.'}
                </EmptyState>
            ) : (
                <Card>
                    {shown.map((person, i) => {
                        const phone = person.phones?.[0];

                        return (
                            <div key={person.id} style={{
                                display: 'flex', alignItems: 'center', gap: 12, padding: '14px 16px',
                                borderBottom: i === shown.length - 1 ? 0 : `1px solid ${C.hairline}`,
                            }}>
                                <button
                                    type="button" onClick={() => setPicked(person)} aria-label={`Options for ${person.name}`}
                                    style={{ flex: 1, minWidth: 0, display: 'flex', alignItems: 'center', gap: 12, background: 'none', border: 0, padding: 0, font: 'inherit', textAlign: 'left', color: C.text, cursor: 'pointer' }}
                                >
                                    <Avatar name={person.name} size={44} radius={22} />
                                    <span style={{ minWidth: 0, display: 'flex', flexDirection: 'column', gap: 4 }}>
                                        <span style={{ fontSize: 16, fontWeight: 600 }}>{person.name}</span>
                                        {phone && <span style={{ fontSize: 14, color: C.muted }}>{person.phones.join(' · ')}</span>}
                                        <span style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                                            {person.companies.map((company) => (
                                                <span key={company.id} style={{ fontSize: 12, fontWeight: 600, padding: '3px 9px', borderRadius: 999, background: '#DBEAFE', color: '#1E40AF' }}>
                                                    {company.name}
                                                </span>
                                            ))}
                                        </span>
                                    </span>
                                </button>
                                {phone && (
                                    <>
                                        <a href={`tel:${phone.replace(/[^\d+]/g, '')}`} aria-label={`Call ${person.name}`} style={{ ...ROUND, background: C.hairline, color: C.text }}>
                                            <Icon name="phone" size={20} />
                                        </a>
                                        <a href={`https://wa.me/${digits(phone)}`} target="_blank" rel="noreferrer" aria-label={`WhatsApp ${person.name}`} style={{ ...ROUND, background: '#DCFCE7', color: '#15803D' }}>
                                            <Icon name="whatsapp" size={20} />
                                        </a>
                                    </>
                                )}
                            </div>
                        );
                    })}
                </Card>
            )}

            <ActionSheet
                open={!!picked} onClose={() => setPicked(null)} title={picked?.name}
                options={picked ? [
                    { label: 'Edit person', onClick: () => r.openEdit(picked), disabled: !can('requesters.edit'), disabledReason: deny('edit') },
                    { label: 'Remove person', danger: true, onClick: () => r.setDeleting(picked), disabled: !can('requesters.delete'), disabledReason: deny('remove') },
                ] : []}
            />

            <RequesterModal r={r} compact />
            <ConfirmModal
                open={!!r.deleting}
                title="Remove this person?"
                body={r.deleting
                    ? `"${r.deleting.name}" will no longer be offered as Requested By on new purchase requests. Requests already raised keep their name.`
                    : ''}
                onConfirm={r.confirmDelete}
                onCancel={() => r.setDeleting(null)}
            />
        </MobilePage>
    );
}
