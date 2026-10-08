import ConfirmModal from '../../../components/ui/ConfirmModal';
import EmailPanel from '../../../components/settings/integrations/EmailPanel';
import MailAccountModal from '../../../components/settings/integrations/MailAccountModal';
import WhatsAppPanel from '../../../components/settings/integrations/WhatsAppPanel';
import useIntegrations from '../../../components/settings/integrations/useIntegrations';
import { Hero, Loading, MobilePage, Segmented } from '../../../components/mobile/ui';

// System → Integrations (SteelERP-Mobile-Designs-V2): WhatsApp and Email
// under a segmented control. The panels are the desktop's own, in their
// stacked (`compact`) layout — their forms, tests and saves are not
// duplicated for the phone.
export default function IntegrationsPage() {
    const i = useIntegrations();

    return (
        <MobilePage gap={16}>
            <Hero
                zone="system"
                back={{ to: '/app/more', label: 'More' }}
                title="Integrations"
                subtitle="Third-party services SteelERP talks to"
            />

            <Segmented
                ariaLabel="Integration"
                value={i.tab}
                onChange={i.setTab}
                options={[{ key: 'whatsapp', label: 'WhatsApp' }, { key: 'email', label: 'Email' }]}
            />

            {i.loading && <Loading />}

            {!i.loading && i.tab === 'whatsapp' && (
                <WhatsAppPanel
                    whatsapp={i.whatsapp} onSave={i.saveWhatsapp} onTest={i.testWhatsapp}
                    onSendTest={i.sendWhatsappTest} compact
                />
            )}
            {!i.loading && i.tab === 'email' && (
                <EmailPanel
                    accounts={i.accounts}
                    onAdd={() => i.setAccountModal({ account: null })}
                    onEdit={(account) => i.setAccountModal({ account })}
                    onToggle={i.toggleAccount}
                    onDelete={i.setDeleting}
                    onSendTest={i.sendTestEmail}
                    compact
                />
            )}

            <MailAccountModal
                open={!!i.accountModal} account={i.accountModal?.account}
                onClose={() => i.setAccountModal(null)} onSave={i.saveAccount} onTest={i.testAccount}
            />
            <ConfirmModal
                open={!!i.deleting}
                title="Delete this mail account?"
                body={i.deleting
                    ? `"${i.deleting.label}" will be permanently removed. Anything sending through Mail::mailer('${i.deleting.name}') will stop working.`
                    : ''}
                onConfirm={i.handleDelete}
                onCancel={() => i.setDeleting(null)}
            />
        </MobilePage>
    );
}
